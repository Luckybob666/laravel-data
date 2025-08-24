<?php

namespace App\Jobs;

use App\Models\DataRecord;
use App\Models\RawDataRecord;
use App\Models\UsedDataRecord;
use App\Models\UploadRecord;
use App\Models\RawUploadRecord;
use App\Models\UsedUploadRecord;
use App\Models\ActivityLog;
use App\Helpers\LargeFileProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
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
            $model = match($this->dataType) {
                'raw' => RawUploadRecord::class,
                'used' => UsedUploadRecord::class,
                default => UploadRecord::class,
            };
            $uploadRecord = $model::findOrFail($this->uploadRecordId);
            
            // 更新状态为处理中
            $statusClass = match($this->dataType) {
                'raw' => RawUploadRecord::class,
                'used' => UsedUploadRecord::class,
                default => UploadRecord::class,
            };
            $uploadRecord->update(['status' => $statusClass::STATUS_PROCESSING]);

            $filePath = $uploadRecord->file_path;
            $fullPath = storage_path('app/public/' . $filePath);

            if (!file_exists($fullPath)) {
                throw new \Exception("文件不存在：{$fullPath}");
            }

            // 创建大文件处理器
            $processor = new LargeFileProcessor($this->dataType, $uploadRecord->id);

            // 创建导入处理器实例
            $importHandler = new ExcelImportHandler($processor);

            // 使用流式读取器，增加批量大小
            Excel::import($importHandler, $fullPath);

            // 获取处理统计
            $stats = $processor->getStats();

            // 清理内存
            $processor->cleanup();

            // 更新上传记录
            $uploadRecord->update([
                'total_count' => $stats['total_rows'], // 使用总行数
                'success_count' => $stats['success_count'],
                'duplicate_count' => $stats['duplicate_count'],
                'status' => $statusClass::STATUS_COMPLETED,
            ]);

            // 处理完成后删除原始文件
            if (Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }

            // 记录活动日志
            $dataTypeText = match($this->dataType) {
                'raw' => '粗数据',
                'used' => '已用数据',
                default => '精数据',
            };
            ActivityLog::log(
                'upload',
                "大文件上传处理完成（{$dataTypeText}），上传ID：{$uploadRecord->id}，总条数：{$stats['total_rows']}，成功：{$stats['success_count']}，重复：{$stats['duplicate_count']}",
                [
                    'upload_record_id' => $uploadRecord->id,
                    'data_type' => $this->dataType,
                    'total_count' => $stats['total_rows'],
                    'success_count' => $stats['success_count'],
                    'duplicate_count' => $stats['duplicate_count'],
                    'processing_time' => microtime(true) - LARAVEL_START,
                ],
                $uploadRecord
            );

            \Log::info("大文件处理完成", [
                'upload_record_id' => $uploadRecord->id,
                'data_type' => $this->dataType,
                'total_count' => $stats['total_rows'],
                'success_count' => $stats['success_count'],
                'duplicate_count' => $stats['duplicate_count'],
                'final_memory_usage' => memory_get_usage(true) / 1024 / 1024 / 1024 . 'GB'
            ]);

        } catch (\Exception $e) {
            // 获取上传记录（如果可能的话）
            try {
                $model = match($this->dataType) {
                    'raw' => RawUploadRecord::class,
                    'used' => UsedUploadRecord::class,
                    default => UploadRecord::class,
                };
                $uploadRecord = $model::find($this->uploadRecordId);
                if ($uploadRecord) {
                    // 更新状态为失败
                    $statusClass = match($this->dataType) {
                        'raw' => RawUploadRecord::class,
                        'used' => UsedUploadRecord::class,
                        default => UploadRecord::class,
                    };
                    $uploadRecord->update([
                        'status' => $statusClass::STATUS_FAILED,
                        'error_message' => $e->getMessage(),
                    ]);

                    // 记录错误日志
                    $dataTypeText = match($this->dataType) {
                        'raw' => '粗数据',
                        'used' => '已用数据',
                        default => '精数据',
                    };
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

/**
 * Excel 导入处理器类
 */
class ExcelImportHandler implements ToArray, WithChunkReading
{
    private $processor;
    private $hasHeaders = false;
    private $headers = [];

    public function __construct(LargeFileProcessor $processor)
    {
        $this->processor = $processor;
    }

    public function array(array $array)
    {
        if (empty($array)) {
            return;
        }

        // 检查第一行是否包含标题（通过检查是否包含非数字键或空值）
        $firstRow = $array[0];
        $this->hasHeaders = $this->detectHeaders($firstRow);
        
        if ($this->hasHeaders) {
            // 如果有标题行，保存标题并跳过第一行
            $this->headers = array_values($firstRow);
            $dataRows = array_slice($array, 1);
        } else {
            // 如果没有标题行，所有行都是数据
            $dataRows = $array;
        }

        foreach ($dataRows as $row) {
            if ($this->hasHeaders) {
                // 将数据行转换为以标题为键的数组
                $processedRow = [];
                foreach (array_values($row) as $index => $value) {
                    if (isset($this->headers[$index])) {
                        $processedRow[$this->headers[$index]] = $value;
                    }
                    // 移除创建column1、column2等的逻辑，只保留有标题的列
                }
                $this->processor->processRow($processedRow);
            } else {
                // 没有标题行，直接处理
                $this->processor->processRow($row);
            }
        }
    }

    /**
     * 检测第一行是否包含标题
     */
    private function detectHeaders($firstRow)
    {
        // 检查是否大部分值都是字符串且不是纯数字
        $stringCount = 0;
        $totalCount = count($firstRow);
        
        foreach ($firstRow as $value) {
            if (is_string($value) && !is_numeric($value) && !empty(trim($value))) {
                $stringCount++;
            }
        }
        
        // 如果超过50%的值是字符串，认为有标题行
        return ($stringCount / $totalCount) > 0.5;
    }

    public function chunkSize(): int
    {
        return 3000; // 减少分块大小以降低内存使用
    }
}
