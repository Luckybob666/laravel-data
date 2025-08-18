<?php

namespace App\Filament\Resources\RawUploadRecordResource\Pages;

use App\Filament\Resources\RawUploadRecordResource;
use Filament\Resources\Pages\CreateRecord;
use App\Jobs\ProcessFileUpload;
use App\Models\ActivityLog;
use App\Models\RawUploadRecord;
use Illuminate\Support\Facades\Storage;

class CreateRawUploadRecord extends CreateRecord
{
    protected static string $resource = RawUploadRecordResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 设置默认值
        $data['user_id'] = auth()->id();
        $data['status'] = 'pending';
        $data['total_count'] = 0;
        $data['success_count'] = 0;
        $data['duplicate_count'] = 0;

        // 处理文件上传
        if (isset($data['file']) && !empty($data['file'])) {
            // 获取文件路径
            $filePath = $data['file'];
            
            // 从路径中提取文件名
            $filename = basename($filePath);
            
            // 确保文件路径是完整的存储路径
            if (!str_starts_with($filePath, 'raw_uploads/')) {
                $filePath = 'raw_uploads/' . $filename;
            }
            
            // 更新数据
            $data['filename'] = $filename;
            $data['original_filename'] = $filename;
            $data['file_path'] = $filePath;
        } else {
            // 如果没有文件，设置默认值
            $data['filename'] = 'no_file_' . time();
            $data['original_filename'] = 'no_file';
            $data['file_path'] = null;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        
        // 如果有文件上传，处理文件
        if ($record->file_path) {
            // 分发队列任务处理粗数据文件
            ProcessFileUpload::dispatch($record->id, 'raw');
            
            // 记录活动日志
            ActivityLog::log(
                'upload',
                "Filament上传粗数据文件：{$record->original_filename}",
                [
                    'upload_record_id' => $record->id,
                    'filename' => $record->original_filename,
                    'data_type' => 'raw',
                ],
                $record
            );
        }
    }
}
