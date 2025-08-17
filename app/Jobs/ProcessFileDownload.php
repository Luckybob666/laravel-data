<?php

namespace App\Jobs;

use App\Models\DataRecord;
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
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ProcessFileDownload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $filters;
    protected $format;
    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct($filters = [], $format = 'xlsx', $userId = null)
    {
        $this->filters = $filters;
        $this->format = $format;
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // 构建查询
            $query = DataRecord::query();

            // 如果指定了上传记录ID，则只导出该记录的数据
            if (!empty($this->filters['upload_record_id'])) {
                $query->where('upload_record_id', $this->filters['upload_record_id']);
            } else {
                // 应用其他过滤器
                if (!empty($this->filters['phone'])) {
                    $query->where('phone', 'like', '%' . $this->filters['phone'] . '%');
                }

                if (!empty($this->filters['country'])) {
                    $query->where('country', 'like', '%' . $this->filters['country'] . '%');
                }

                if (!empty($this->filters['industry'])) {
                    $query->where('industry', 'like', '%' . $this->filters['industry'] . '%');
                }

                if (!empty($this->filters['date_from'])) {
                    $query->whereDate('created_at', '>=', $this->filters['date_from']);
                }

                if (!empty($this->filters['date_to'])) {
                    $query->whereDate('created_at', '<=', $this->filters['date_to']);
                }
            }

            $records = $query->get();

            // 准备导出数据
            $exportData = [];
            $headers = []; // 不添加表头

            // 获取所有可能的列标题（从data字段中获取原始表头）
            $allColumns = [];
            foreach ($records as $record) {
                if ($record->data) {
                    foreach ($record->data as $key => $value) {
                        $allColumns[$key] = true;
                    }
                }
            }

            // 准备表头（使用data字段中的原始表头）
            $headers = ['手机号码'];
            foreach (array_keys($allColumns) as $column) {
                $headers[] = $column;
            }

            // 添加表头到导出数据
            $exportData[] = $headers;

            // 准备数据行
            foreach ($records as $record) {
                $row = [$record->phone];

                // 添加JSON数据列
                foreach (array_keys($allColumns) as $column) {
                    $row[] = $record->data[$column] ?? '';
                }

                $exportData[] = $row;
            }

            // 创建导出类
            $exportClass = new class($exportData) implements FromArray {
                private $data;

                public function __construct($data)
                {
                    $this->data = $data;
                }

                public function array(): array
                {
                    return $this->data;
                }
            };

            // 生成文件名
            $filename = 'data_export_' . now()->format('Y-m-d_H-i-s') . '.' . $this->format;
            $filePath = 'exports/' . $filename;

            // 导出文件
            if ($this->format === 'csv') {
                Excel::store($exportClass, $filePath, 'public', \Maatwebsite\Excel\Excel::CSV);
            } else {
                Excel::store($exportClass, $filePath, 'public', \Maatwebsite\Excel\Excel::XLSX);
            }

            // 记录活动日志
            ActivityLog::log(
                'download',
                "导出数据文件 {$filename}，共 {$records->count()} 条记录",
                [
                    'filename' => $filename,
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
            ActivityLog::log(
                'download',
                "导出数据失败：" . $e->getMessage(),
                [
                    'error' => $e->getMessage(),
                    'filters' => $this->filters,
                    'format' => $this->format,
                ]
            );

            throw $e;
        }
    }
}
