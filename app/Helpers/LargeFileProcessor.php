<?php

namespace App\Helpers;

use App\Models\DataRecord;
use App\Models\RawDataRecord;
use App\Models\UsedDataRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LargeFileProcessor
{
    private $dataType;
    private $uploadRecordId;
    private $batchSize = 3000; // 增大批量以提升吞吐
    private $currentBatchPhones = [];
    private $batchData = [];
    private $successCount = 0;
    private $duplicateCount = 0;
    private $processedRows = 0;
    private $totalRows = 0; // 新增：总行数计数器

    public function __construct($dataType, $uploadRecordId)
    {
        $this->dataType = $dataType;
        $this->uploadRecordId = $uploadRecordId;
    }

    /**
     * 处理单行数据
     */
    public function processRow($row)
    {
        // 增加总行数计数
        $this->totalRows++;

        // 获取手机号码
        $phone = $this->extractPhone($row);
        if (empty($phone)) {
            return;
        }

        // 仅去重当前批次，避免全量预查询带来的高开销
        if ($this->isDuplicateInCurrentBatch($phone)) {
            $this->duplicateCount++;
            return;
        }

        // 添加到批量数据
        $this->batchData[] = [
            'phone' => $phone,
            'data' => json_encode($this->extractOtherData($row)),
            'upload_record_id' => $this->uploadRecordId,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $this->processedRows++;

        // 当达到批量大小时，执行批量插入
        if (count($this->batchData) >= $this->batchSize) {
            $this->insertBatch();
        }
    }

    /**
     * 提取手机号码
     */
    private function extractPhone($row)
    {
        if (isset($row['phone'])) {
            return trim($row['phone']);
        } else {
            // 如果没有phone字段，取第一列作为手机号
            $values = array_values($row);
            return !empty($values) ? trim($values[0]) : null;
        }
    }

    /**
     * 提取其他数据
     */
    private function extractOtherData($row)
    {
        // 检查是否有字符串键（表示有标题行）
        $hasStringKeys = false;
        foreach ($row as $key => $value) {
            if (is_string($key) && !is_numeric($key)) {
                $hasStringKeys = true;
                break;
            }
        }
        
        if ($hasStringKeys) {
            // 有标题行，保持原有的键值对格式，但移除手机号字段
            if (isset($row['phone'])) {
                unset($row['phone']);
            } else {
                // 如果没有phone字段，移除第一列（手机号）
                $keys = array_keys($row);
                if (!empty($keys)) {
                    unset($row[$keys[0]]);
                }
            }
            return $row;
        } else {
            // 没有标题行，取除第一列外的其他数据，使用column1、column2等作为键
            $values = array_values($row);
            $otherData = array_slice($values, 1);
            
            $result = [];
            foreach ($otherData as $index => $value) {
                $key = 'column' . ($index + 1);
                $result[$key] = $value;
            }
            return $result;
        }
    }

    /**
     * 批量插入数据
     */
    private function insertBatch()
    {
        if (!empty($this->batchData)) {
            $tableName = match($this->dataType) {
                'raw' => 'raw_data_records',
                'used' => 'used_data_records',
                default => 'data_records',
            };
            
            try {
                // 使用insertOrIgnore避免重复键错误
                $insertedCount = DB::table($tableName)->insertOrIgnore($this->batchData);
                
                // Log::info("批量插入完成", [
                //     'upload_record_id' => $this->uploadRecordId,
                //     'data_type' => $this->dataType,
                //     'processed_rows' => $this->processedRows,
                //     'batch_size' => count($this->batchData),
                //     'inserted_count' => $insertedCount,
                //     'ignored_count' => count($this->batchData) - $insertedCount,
                //     'memory_usage' => $this->formatMemoryUsage()
                // ]);
                
                // 更新计数（以数据库写入结果为准）
                $this->successCount += $insertedCount;
                $this->duplicateCount += (count($this->batchData) - $insertedCount);
                
                $this->batchData = [];
                $this->currentBatchPhones = [];
            } catch (\Exception $e) {
                // Log::error("批量插入失败", [
                //     'upload_record_id' => $this->uploadRecordId,
                //     'data_type' => $this->dataType,
                //     'error' => $e->getMessage(),
                //     'batch_size' => count($this->batchData),
                //     'first_phone' => $this->batchData[0]['phone'] ?? 'N/A',
                //     'last_phone' => end($this->batchData)['phone'] ?? 'N/A'
                // ]);
                
                // 如果批量插入失败，尝试逐条插入
                $this->insertOneByOne();
            }
        }
    }

    /**
     * 逐条插入数据（作为批量插入失败的后备方案）
     */
    private function insertOneByOne()
    {
        $tableName = match($this->dataType) {
            'raw' => 'raw_data_records',
            'used' => 'used_data_records',
            default => 'data_records',
        };
        $successCount = 0;
        $duplicateCount = 0;
        
        foreach ($this->batchData as $data) {
            try {
                $affected = DB::table($tableName)->insertOrIgnore([$data]);
                if ($affected === 1) {
                    $successCount++;
                } else {
                    $duplicateCount++;
                }
            } catch (\Exception $e) {
                Log::warning("单条插入失败", [
                    'phone' => $data['phone'],
                    'error' => $e->getMessage()
                ]);
                $duplicateCount++;
            }
        }
        
        // Log::info("逐条插入完成", [
        //     'upload_record_id' => $this->uploadRecordId,
        //     'data_type' => $this->dataType,
        //     'success_count' => $successCount,
        //     'duplicate_count' => $duplicateCount
        // ]);
        
        // 更新计数
        $this->successCount += $successCount;
        $this->duplicateCount += $duplicateCount;
        
        $this->batchData = [];
        $this->currentBatchPhones = [];
    }

    /**
     * 获取处理统计
     */
    public function getStats()
    {
        // 插入剩余的数据
        $this->insertBatch();
        
        return [
            'total_rows' => $this->totalRows, // 总行数（包括空行）
            'success_count' => $this->successCount,
            'duplicate_count' => $this->duplicateCount,
            'processed_rows' => $this->processedRows, // 有效处理的行数（有手机号的）
        ];
    }

    /**
     * 格式化内存使用量
     */
    private function formatMemoryUsage()
    {
        $memory = memory_get_usage(true);
        if ($memory >= 1024 * 1024 * 1024) {
            return round($memory / 1024 / 1024 / 1024, 2) . 'GB';
        } else {
            return round($memory / 1024 / 1024, 2) . 'MB';
        }
    }

    /**
     * 清理内存
     */
    public function cleanup()
    {
        $this->currentBatchPhones = [];
        $this->batchData = [];
        
        // 强制垃圾回收
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }

    /**
     * 检查当前批次中是否有重复的手机号
     */
    private function isDuplicateInCurrentBatch($phone)
    {
        if (isset($this->currentBatchPhones[$phone])) {
            return true;
        }
        $this->currentBatchPhones[$phone] = true;
        return false;
    }
}
