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
    private $batchSize = 1000; // 减少批量大小以降低内存使用
    private $existingPhones = [];
    private $phoneBatch = [];
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

        // 添加到手机号批次
        $this->phoneBatch[] = $phone;

        // 当手机号批次达到一定大小时，批量检查重复
        if (count($this->phoneBatch) >= 500) {
            $this->checkExistingPhonesBatch();
        }

        // 检查手机号码是否已存在（包括当前批次中的重复）
        if (in_array($phone, $this->existingPhones) || $this->isDuplicateInCurrentBatch($phone)) {
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

        $this->successCount++;
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
        if (isset($row['phone'])) {
            unset($row['phone']);
            return $row;
        } else {
            // 如果没有phone字段，取除第一列外的其他数据
            $values = array_values($row);
            $otherData = array_slice($values, 1);
            
            // 检查是否有字符串键（表示有标题行）
            $hasStringKeys = false;
            foreach ($row as $key => $value) {
                if (is_string($key) && !is_numeric($key)) {
                    $hasStringKeys = true;
                    break;
                }
            }
            
            if ($hasStringKeys) {
                // 有标题行，保持原有的键值对格式
                unset($row[0]); // 移除第一列（手机号）
                return $row;
            } else {
                // 没有标题行，使用column1、column2等作为键
                $result = [];
                foreach ($otherData as $index => $value) {
                    $key = 'column' . ($index + 1);
                    $result[$key] = $value;
                }
                return $result;
            }
        }
    }

    /**
     * 批量检查已存在的手机号
     */
    private function checkExistingPhonesBatch()
    {
        if (empty($this->phoneBatch)) {
            return;
        }

        // 批量查询已存在的手机号
        $existingPhones = $this->getExistingPhonesBatch($this->phoneBatch);
        
        // 将已存在的手机号添加到缓存中
        foreach ($existingPhones as $phone) {
            $this->existingPhones[] = $phone;
        }

        // 清空手机号批次
        $this->phoneBatch = [];
    }

    /**
     * 批量查询已存在的手机号
     */
    private function getExistingPhonesBatch($phones)
    {
        return match($this->dataType) {
            'raw' => RawDataRecord::whereIn('phone', $phones)->pluck('phone')->toArray(),
            'used' => UsedDataRecord::whereIn('phone', $phones)->pluck('phone')->toArray(),
            default => DataRecord::whereIn('phone', $phones)->pluck('phone')->toArray(),
        };
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
                
                // 更新成功计数
                $this->successCount = $this->successCount - (count($this->batchData) - $insertedCount);
                $this->duplicateCount += (count($this->batchData) - $insertedCount);
                
                $this->batchData = [];
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
                DB::table($tableName)->insertOrIgnore([$data]);
                $successCount++;
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
        $this->successCount = $this->successCount - (count($this->batchData) - $successCount);
        $this->duplicateCount += $duplicateCount;
        
        $this->batchData = [];
    }

    /**
     * 获取处理统计
     */
    public function getStats()
    {
        // 检查剩余的手机号批次
        $this->checkExistingPhonesBatch();
        
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
        $this->existingPhones = [];
        $this->phoneBatch = [];
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
        // 检查当前批次数据中是否已有相同手机号
        foreach ($this->batchData as $data) {
            if ($data['phone'] === $phone) {
                return true;
            }
        }
        return false;
    }
}
