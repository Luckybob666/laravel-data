<?php

namespace App\Jobs;

use App\Models\DataRecord;
use App\Models\RawDataRecord;
use App\Models\UsedDataRecord;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\DownloadRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ProcessFileDownload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filters;
    protected $format;
    protected $userId;
    protected $dataType;
    protected $records;

    /**
     * Create a new job instance.
     */
    public function __construct($filters = [], $format = 'xlsx', $userId = null, $dataType = 'refined', $records = null)
    {
        $this->filters = $filters;
        $this->format = $format;
        $this->userId = $userId;
        $this->dataType = $dataType;
        $this->records = $records;
        $this->timeout = 3600; // 60分钟超时
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
            
            // 如果传入了记录集合，直接使用；否则构建查询
            if ($this->records) {
                $records = $this->records;
            } else {
                // 根据数据类型选择模型
                $model = match($this->dataType) {
                    'raw' => RawDataRecord::class,
                    'used' => UsedDataRecord::class,
                    default => DataRecord::class,
                };
                $query = $model::query();

                // 如果指定了上传记录ID，则只导出该记录的数据
                if (!empty($this->filters['upload_record_id'])) {
                    $query->where('upload_record_id', $this->filters['upload_record_id']);
                } else {
                                    // 应用其他过滤器
                if (!empty($this->filters['phone'])) {
                    $query->where('phone', 'like', '%' . $this->filters['phone'] . '%');
                }

                // 根据数据类型使用正确的关系进行过滤
                if (!empty($this->filters['country'])) {
                    if ($this->dataType === 'used') {
                        // 已使用数据使用 UsedUploadRecord 关系
                        $query->whereHas('uploadRecord', function ($q) {
                            $q->where('country', 'like', '%' . $this->filters['country'] . '%');
                        });
                    } else {
                        // 精数据和粗数据使用 UploadRecord 或 RawUploadRecord 关系
                        $query->whereHas('uploadRecord', function ($q) {
                            $q->where('country', 'like', '%' . $this->filters['country'] . '%');
                        });
                    }
                }

                if (!empty($this->filters['industry'])) {
                    if ($this->dataType === 'used') {
                        // 已使用数据使用 UsedUploadRecord 关系
                        $query->whereHas('uploadRecord', function ($q) {
                            $q->where('industry', 'like', '%' . $this->filters['industry'] . '%');
                        });
                    } else {
                        // 精数据和粗数据使用 UploadRecord 或 RawUploadRecord 关系
                        $query->whereHas('uploadRecord', function ($q) {
                            $q->where('industry', 'like', '%' . $this->filters['industry'] . '%');
                        });
                    }
                }

                    if (!empty($this->filters['date_from'])) {
                        $query->whereDate('created_at', '>=', $this->filters['date_from']);
                    }

                    if (!empty($this->filters['date_to'])) {
                        $query->whereDate('created_at', '<=', $this->filters['date_to']);
                    }
                }

                $records = $query->get();
            }

            // 使用流式导出类
            $exportClass = new class($records) implements FromArray, WithHeadings {
                private $records;
                private $allColumns = [];

                public function __construct($records)
                {
                    $this->records = $records;
                    $this->prepareColumns();
                }

                private function prepareColumns()
                {
                    // 获取所有可能的列标题（从data字段中获取原始表头）
                    foreach ($this->records as $record) {
                        if ($record->data && is_array($record->data)) {
                            foreach ($record->data as $key => $value) {
                                $this->allColumns[$key] = true;
                            }
                        }
                    }
                    // 确保列的顺序是固定的，但不要按字母排序，保持原始顺序
                    $this->allColumns = array_keys($this->allColumns);
                }

                public function array(): array
                {
                    $exportData = [];
                    
                    // 准备数据行
                    foreach ($this->records as $record) {
                        $row = [];
                        
                        // 添加手机号码作为第一列
                        $row[] = $record->phone;
                        
                        // 按照固定的列顺序添加JSON数据
                        foreach ($this->allColumns as $column) {
                            $row[] = $record->data[$column] ?? '';
                        }

                        $exportData[] = $row;
                    }

                    return $exportData;
                }

                public function headings(): array
                {
                    $headers = ['手机号码'];
                    foreach ($this->allColumns as $column) {
                        $headers[] = $column;
                    }
                    return $headers;
                }
            };

            // 生成文件名
            $dataTypePrefix = match($this->dataType) {
                'raw' => 'raw_data',
                'used' => 'used_data',
                default => 'data',
            };
            $filename = $dataTypePrefix . '_export_' . now()->format('Y-m-d_H-i-s') . '.' . $this->format;
            $filePath = 'exports/' . $filename;

            // 导出文件
            if ($this->format === 'csv') {
                Excel::store($exportClass, $filePath, 'public', \Maatwebsite\Excel\Excel::CSV);
            } else {
                Excel::store($exportClass, $filePath, 'public', \Maatwebsite\Excel\Excel::XLSX);
            }

            // 记录活动日志
            $dataTypeText = match($this->dataType) {
                'raw' => '粗数据',
                'used' => '已用数据',
                default => '精数据',
            };
            ActivityLog::log(
                'download',
                "导出{$dataTypeText}文件 {$filename}，共 {$records->count()} 条记录",
                [
                    'filename' => $filename,
                    'data_type' => $this->dataType,
                    'record_count' => $records->count(),
                    'filters' => $this->filters,
                    'format' => $this->format,
                ]
            );

            if ($this->userId) {
                try {
                    $downloadRecord = DownloadRecord::create([
                        'filename' => $filename,
                        'file_path' => $filePath,
                        'download_url' => url('storage/' . $filePath),
                        'record_count' => $records->count(),
                        'format' => $this->format,
                        'upload_record_id' => $this->filters['upload_record_id'] ?? null,
                        'user_id' => $this->userId,
                        'filters' => $this->filters,
                        'status' => DownloadRecord::STATUS_COMPLETED,
                    ]);

                } catch (\Exception $e) {
                    \Log::error('创建下载记录失败', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            } else {
                \Log::warning('userId为空，跳过创建下载记录');
            }

        } catch (\Exception $e) {
            // 记录错误日志
            $dataTypeText = match($this->dataType) {
                'raw' => '粗数据',
                'used' => '已用数据',
                default => '精数据',
            };
            ActivityLog::log(
                'download',
                "导出{$dataTypeText}失败：" . $e->getMessage(),
                [
                    'error' => $e->getMessage(),
                    'data_type' => $this->dataType,
                    'filters' => $this->filters,
                    'format' => $this->format,
                ]
            );

            throw $e;
        }
    }
}
