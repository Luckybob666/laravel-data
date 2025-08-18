<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class LargeFileProcessor
{
    private $batchSize = 2000; // 增加批量大小
    private $chunkSize = 5000; // 增加分块大小
    private $memoryLimit = '20G'; // 增加到20GB

    public function __construct($batchSize = 2000, $chunkSize = 5000, $memoryLimit = '20G')
    {
        $this->batchSize = $batchSize;
        $this->chunkSize = $chunkSize;
        $this->memoryLimit = $memoryLimit;
    }

    /**
     * 设置内存限制
     */
    public function setMemoryLimit()
    {
        ini_set('memory_limit', $this->memoryLimit);
        ini_set('max_execution_time', 7200); // 2小时
    }

    /**
     * 流式处理Excel文件
     */
    public function processExcelFile($filePath, $dataType, $uploadRecordId, $callback = null)
    {
        $this->setMemoryLimit();

        $processor = new class($dataType, $uploadRecordId, $this->batchSize, $callback) 
            implements ToArray, WithChunkReading, WithHeadingRow {
            
            private $dataType;
            private $uploadRecordId;
            private $batchSize;
            private $callback;
            private $batchData = [];
            private $successCount = 0;
            private $duplicateCount = 0;
            private $processedRows = 0;

            public function __construct($dataType, $uploadRecordId, $batchSize, $callback)
            {
                $this->dataType = $dataType;
                $this->uploadRecordId = $uploadRecordId;
                $this->batchSize = $batchSize;
                $this->callback = $callback;
            }

            public function array(array $array)
            {
                foreach ($array as $row) {
                    $this->processRow($row);
                }
            }

            public function chunkSize(): int
            {
                return 5000; // 增加分块大小
            }

            private function processRow($row)
            {
                // 获取手机号码（第一列）
                $phone = null;
                $otherData = [];
                
                // 处理不同的数据格式
                if (isset($row['phone'])) {
                    $phone = trim($row['phone']);
                    unset($row['phone']);
                    $otherData = $row;
                } else {
                    // 如果没有phone字段，取第一列作为手机号
                    $values = array_values($row);
                    if (!empty($values)) {
                        $phone = trim($values[0]);
                        $otherData = array_slice($values, 1);
                    }
                }

                if (empty($phone)) {
                    return;
                }

                // 检查手机号码是否已存在
                $existingRecord = $this->checkExistingRecord($phone);
                if ($existingRecord) {
                    $this->duplicateCount++;
                    return;
                }

                // 添加到批量数据
                $this->batchData[] = [
                    'phone' => $phone,
                    'data' => json_encode($otherData),
                    'upload_record_id' => $this->uploadRecordId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $this->successCount++;
                $this->processedRows++;

                // 当达到批量大小时，执行批量插入
                if (count($this->batchData) >= $this->batchSize) {
                    $this->insertBatch();
                }
            }

            private function checkExistingRecord($phone)
            {
                if ($this->dataType === 'raw') {
                    return \App\Models\RawDataRecord::where('phone', $phone)->first();
                } else {
                    return \App\Models\DataRecord::where('phone', $phone)->first();
                }
            }

            private function insertBatch()
            {
                if (!empty($this->batchData)) {
                    $tableName = $this->dataType === 'raw' ? 'raw_data_records' : 'data_records';
                    DB::table($tableName)->insert($this->batchData);
                    $this->batchData = [];
                    
                    // 记录进度
                    Log::info("批量插入完成", [
                        'upload_record_id' => $this->uploadRecordId,
                        'data_type' => $this->dataType,
                        'processed_rows' => $this->processedRows,
                        'memory_usage' => memory_get_usage(true) / 1024 / 1024 / 1024 . 'GB'
                    ]);

                    // 执行回调函数
                    if ($this->callback && is_callable($this->callback)) {
                        call_user_func($this->callback, $this->processedRows, $this->successCount, $this->duplicateCount);
                    }
                }
            }

            public function getStats()
            {
                // 插入剩余的数据
                $this->insertBatch();
                
                return [
                    'success_count' => $this->successCount,
                    'duplicate_count' => $this->duplicateCount,
                    'processed_rows' => $this->processedRows,
                ];
            }
        };

        // 导入文件
        Excel::import($processor, $filePath);

        // 返回处理统计
        return $processor->getStats();
    }

    /**
     * 流式导出数据
     */
    public function exportData($records, $format = 'xlsx')
    {
        $this->setMemoryLimit();

        $exportClass = new class($records) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
            private $records;
            private $allColumns = [];

            public function __construct($records)
            {
                $this->records = $records;
                $this->prepareColumns();
            }

            private function prepareColumns()
            {
                // 获取所有可能的列标题
                foreach ($this->records as $record) {
                    if ($record->data) {
                        foreach ($record->data as $key => $value) {
                            $this->allColumns[$key] = true;
                        }
                    }
                }
            }

            public function array(): array
            {
                $exportData = [];
                
                // 准备数据行
                foreach ($this->records as $record) {
                    $row = [$record->phone];

                    // 添加JSON数据列
                    foreach (array_keys($this->allColumns) as $column) {
                        $row[] = $record->data[$column] ?? '';
                    }

                    $exportData[] = $row;
                }

                return $exportData;
            }

            public function headings(): array
            {
                $headers = ['手机号码'];
                foreach (array_keys($this->allColumns) as $column) {
                    $headers[] = $column;
                }
                return $headers;
            }
        };

        return $exportClass;
    }

    /**
     * 获取内存使用情况
     */
    public function getMemoryUsage()
    {
        return [
            'memory_usage' => memory_get_usage(true) / 1024 / 1024,
            'memory_peak' => memory_get_peak_usage(true) / 1024 / 1024,
            'memory_limit' => ini_get('memory_limit'),
        ];
    }

    /**
     * 清理内存
     */
    public function cleanup()
    {
        gc_collect_cycles();
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }
}
