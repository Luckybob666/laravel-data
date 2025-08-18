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
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ProcessFileUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $uploadRecordId;
    protected $dataType;
    protected $batchSize = 500; // 减少批量大小，降低内存使用

    /**
     * Create a new job instance.
     */
    public function __construct($uploadRecordId, $dataType = 'refined')
    {
        $this->uploadRecordId = $uploadRecordId;
        $this->dataType = $dataType;
        $this->timeout = 3600; // 增加到60分钟超时
        $this->tries = 3; // 重试3次
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // 增加内存限制到20GB
            ini_set('memory_limit', '20G');
            ini_set('max_execution_time', 7200); // 2小时超时
            
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

            // 使用流式读取器，增加批量大小
            $reader = Excel::import(new class($this->dataType, $uploadRecord->id) implements ToArray, WithChunkReading, WithHeadingRow {
                private $dataType;
                private $uploadRecordId;
                private $batchData = [];
                private $successCount = 0;
                private $duplicateCount = 0;
                private $processedRows = 0;
                private $batchSize = 2000; // 增加批量大小到2000

                public function __construct($dataType, $uploadRecordId)
                {
                    $this->dataType = $dataType;
                    $this->uploadRecordId = $uploadRecordId;
                }

                public function array(array $array)
                {
                    foreach ($array as $row) {
                        $this->processRow($row);
                    }
                }

                public function chunkSize(): int
                {
                    return 5000; // 增加分块大小到5000
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
                        return RawDataRecord::where('phone', $phone)->first();
                    } else {
                        return DataRecord::where('phone', $phone)->first();
                    }
                }

                private function insertBatch()
                {
                    if (!empty($this->batchData)) {
                        $tableName = $this->dataType === 'raw' ? 'raw_data_records' : 'data_records';
                        DB::table($tableName)->insert($this->batchData);
                        $this->batchData = [];
                        
                        // 记录进度
                        \Log::info("批量插入完成", [
                            'upload_record_id' => $this->uploadRecordId,
                            'data_type' => $this->dataType,
                            'processed_rows' => $this->processedRows,
                            'memory_usage' => memory_get_usage(true) / 1024 / 1024 / 1024 . 'GB'
                        ]);
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
            }, $fullPath);

            // 获取处理统计
            $stats = $reader->getStats();

            // 更新上传记录
            $uploadRecord->update([
                'total_count' => $stats['processed_rows'] + $stats['duplicate_count'],
                'success_count' => $stats['success_count'],
                'duplicate_count' => $stats['duplicate_count'],
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
                "大文件上传处理完成（{$dataTypeText}），上传ID：{$uploadRecord->id}，总条数：{$stats['processed_rows']}，成功：{$stats['success_count']}，重复：{$stats['duplicate_count']}",
                [
                    'upload_record_id' => $uploadRecord->id,
                    'data_type' => $this->dataType,
                    'total_count' => $stats['processed_rows'] + $stats['duplicate_count'],
                    'success_count' => $stats['success_count'],
                    'duplicate_count' => $stats['duplicate_count'],
                    'processing_time' => microtime(true) - LARAVEL_START,
                ],
                $uploadRecord
            );

            \Log::info("大文件处理完成", [
                'upload_record_id' => $uploadRecord->id,
                'data_type' => $this->dataType,
                'total_count' => $stats['processed_rows'] + $stats['duplicate_count'],
                'success_count' => $stats['success_count'],
                'duplicate_count' => $stats['duplicate_count'],
                'final_memory_usage' => memory_get_usage(true) / 1024 / 1024 / 1024 . 'GB'
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
}
