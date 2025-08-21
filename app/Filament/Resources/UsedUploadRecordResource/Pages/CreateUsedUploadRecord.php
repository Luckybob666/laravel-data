<?php

namespace App\Filament\Resources\UsedUploadRecordResource\Pages;

use App\Filament\Resources\UsedUploadRecordResource;
use App\Jobs\ProcessFileUpload;
use App\Models\ActivityLog;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;

class CreateUsedUploadRecord extends CreateRecord
{
    protected static string $resource = UsedUploadRecordResource::class;

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
            if (!str_starts_with($filePath, 'used_uploads/')) {
                $filePath = 'used_uploads/' . $filename;
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
        // 分发队列任务处理文件
        ProcessFileUpload::dispatch($this->record->id, 'used');

        // 记录活动日志
        ActivityLog::log(
            'upload',
            "Filament上传已用数据文件：{$this->record->original_filename}",
            [
                'upload_record_id' => $this->record->id,
                'filename' => $this->record->original_filename,
                'data_type' => 'used',
            ],
            $this->record
        );

        // 显示成功消息
        Notification::make()
            ->title('文件上传成功')
            ->body('文件已上传，正在后台处理中...')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        // 创建完成后跳转到列表页面
        return static::getResource()::getUrl('index');
    }
}
