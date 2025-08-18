<?php

namespace App\Jobs;

use App\Models\DataRecord;
use App\Models\RawDataRecord;
use App\Models\UploadRecord;
use App\Models\RawUploadRecord;
use App\Models\ActivityLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ProcessFileUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $uploadRecordId;
    protected $dataType;
    protected $batchSize = 1000; // 批量插入大小

    /**
     * Create a new job instance.
     */
    public function __construct($uploadRecordId, $dataType = 'refined')
    {
        $this->uploadRecordId = $uploadRecordId;
        $this->dataType = $dataType;
        $this->timeout = 1800; // 30分钟超时
        $this->tries = 3; // 重试3次
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // 根据数据类型选择模型
            $model = $this->dataType === 'raw' ? RawUploadRecord::class : UploadRecord::class;
            $uploadRecord = $model::findOrFail($this->uploadRecordId);
            
            // 更新状态为处理中
            $statusClass = $this->dataType === 'raw' ? RawUploadRecord::class : UploadRecord::class;
            $uploadRecord->update(['status' => $statusClass::STATUS_PROCESSING]);

            $filePath = $uploadRecord->file_path;
            $fullPath = storage_path('app/public/' . $filePath);

            if (!file_exists($fullPath)) {
                throw new \Exception("文件不存在：{$fullPath}");
            }

            // 使用流式读取，减少内存使用
            $data = Excel::toArray(new class implements \Maatwebsite\Excel\Concerns\ToArray {
                public function array(array $array) {
                    return $array;
                }
            }, $fullPath)[0];

            $totalCount = count($data);
            $successCount = 0;
            $duplicateCount = 0;

            // 智能判断是否有表头
            $hasHeader = false;
            $headers = [];
            if (!empty($data)) {
                $firstRow = array_values($data[0]);
                // 如果第一行第一列不是数字，且其他列也不是纯数字，则认为是表头
                if (!is_numeric($firstRow[0]) && !preg_match('/^\d+$/', $firstRow[0])) {
                    $hasHeader = true;
                    $headers = $firstRow;
                }
            }

            \Log::info("开始处理大文件", [
                'upload_record_id' => $uploadRecord->id,
                'data_type' => $this->dataType,
                'file_path' => $fullPath,
                'total_rows' => $totalCount,
                'has_header' => $hasHeader,
                'headers' => $headers,
                'memory_usage' => memory_get_usage(true) / 1024 / 1024 . 'MB'
            ]);

            // 批量处理数据
            $batchData = [];
            $processedRows = 0;

            foreach ($data as $index => $row) {
                // 如果有表头，跳过第一行
                if ($hasHeader && $index === 0) {
                    continue;
                }

                $rowData = array_values($row);
                if (empty($rowData[0])) continue; // 跳过空行

                $phone = trim($rowData[0]);
                if (empty($phone)) continue;

                // 检查手机号码是否已存在（根据数据类型选择表）
                $existingRecord = $this->checkExistingRecord($phone);
                if ($existingRecord) {
                    $duplicateCount++;
                    continue;
                }

                // 提取其他列数据
                $otherData = array_slice($rowData, 1);
                $jsonData = [];
                
                if ($hasHeader && !empty($headers)) {
                    // 如果有表头，使用原始字段名
                    foreach ($otherData as $colIndex => $value) {
                        if (!empty($value) && isset($headers[$colIndex + 1])) {
                            $fieldName = trim($headers[$colIndex + 1]);
                            if (!empty($fieldName)) {
                                $jsonData[$fieldName] = $value;
                            }
                        }
                    }
                } else {
                    // 如果没有表头，使用默认的列名
                    foreach ($otherData as $colIndex => $value) {
                        if (!empty($value)) {
                            $jsonData["column_" . ($colIndex + 2)] = $value;
                        }
                    }
                }

                // 添加到批量数据
                $batchData[] = [
                    'phone' => $phone,
                    'data' => json_encode($jsonData),
                    'upload_record_id' => $uploadRecord->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $successCount++;
                $processedRows++;

                // 当达到批量大小时，执行批量插入
                if (count($batchData) >= $this->batchSize) {
                    $this->insertBatch($batchData);
                    $batchData = [];
                    
                    // 记录进度
                    \Log::info("批量插入完成", [
                        'upload_record_id' => $uploadRecord->id,
                        'data_type' => $this->dataType,
                        'processed_rows' => $processedRows,
                        'total_rows' => $totalCount,
                        'memory_usage' => memory_get_usage(true) / 1024 / 1024 . 'MB'
                    ]);
                }
            }

            // 插入剩余的数据
            if (!empty($batchData)) {
                $this->insertBatch($batchData);
            }

            // 更新上传记录
            $statusClass = $this->dataType === 'raw' ? RawUploadRecord::class : UploadRecord::class;
            $uploadRecord->update([
                'total_count' => $totalCount,
                'success_count' => $successCount,
                'duplicate_count' => $duplicateCount,
                'status' => $statusClass::STATUS_COMPLETED,
            ]);

            // 处理完成后删除原始文件
            if (Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }

            // 记录活动日志
            $dataTypeText = $this->dataType === 'raw' ? '粗数据' : '精数据';
            ActivityLog::log(
                'upload',
                "大文件上传处理完成（{$dataTypeText}），上传ID：{$uploadRecord->id}，总条数：{$totalCount}，成功：{$successCount}，重复：{$duplicateCount}",
                [
                    'upload_record_id' => $uploadRecord->id,
                    'data_type' => $this->dataType,
                    'total_count' => $totalCount,
                    'success_count' => $successCount,
                    'duplicate_count' => $duplicateCount,
                    'processing_time' => microtime(true) - LARAVEL_START,
                ],
                $uploadRecord
            );

            \Log::info("大文件处理完成", [
                'upload_record_id' => $uploadRecord->id,
                'data_type' => $this->dataType,
                'total_count' => $totalCount,
                'success_count' => $successCount,
                'duplicate_count' => $duplicateCount,
                'final_memory_usage' => memory_get_usage(true) / 1024 / 1024 . 'MB'
            ]);

        } catch (\Exception $e) {
            // 获取上传记录（如果可能的话）
            try {
                $model = $this->dataType === 'raw' ? RawUploadRecord::class : UploadRecord::class;
                $uploadRecord = $model::find($this->uploadRecordId);
                if ($uploadRecord) {
                    // 更新状态为失败
                    $statusClass = $this->dataType === 'raw' ? RawUploadRecord::class : UploadRecord::class;
                    $uploadRecord->update([
                        'status' => $statusClass::STATUS_FAILED,
                        'error_message' => $e->getMessage(),
                    ]);

                    // 记录错误日志
                    $dataTypeText = $this->dataType === 'raw' ? '粗数据' : '精数据';
                    ActivityLog::log(
                        'upload',
                        "大文件上传处理失败（{$dataTypeText}），上传ID：{$uploadRecord->id}，错误：" . $e->getMessage(),
                        [
                            'upload_record_id' => $uploadRecord->id,
                            'data_type' => $this->dataType,
                            'error' => $e->getMessage(),
                        ],
                        $uploadRecord
                    );
                }
            } catch (\Exception $logException) {
                // 如果记录日志也失败，至少记录到系统日志
                \Log::error('大文件上传失败: ' . $e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * 检查记录是否已存在
     */
    private function checkExistingRecord($phone)
    {
        if ($this->dataType === 'raw') {
            return RawDataRecord::where('phone', $phone)->first();
        } else {
            return DataRecord::where('phone', $phone)->first();
        }
    }

    /**
     * 批量插入数据
     */
    private function insertBatch(array $data): void
    {
        $tableName = $this->dataType === 'raw' ? 'raw_data_records' : 'data_records';
        DB::table($tableName)->insert($data);
    }
}
