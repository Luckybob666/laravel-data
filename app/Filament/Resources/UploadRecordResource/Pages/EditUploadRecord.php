<?php

namespace App\Filament\Resources\UploadRecordResource\Pages;

use App\Filament\Resources\UploadRecordResource;
use App\Models\ActivityLog;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUploadRecord extends EditRecord
{
    protected static string $resource = UploadRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // 只允许编辑特定字段
        return array_intersect_key($data, array_flip(['country', 'industry', 'remarks']));
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // 只保存允许编辑的字段
        return array_intersect_key($data, array_flip(['country', 'industry', 'remarks']));
    }

    protected function afterSave(): void
    {
        // 记录活动日志
        ActivityLog::log(
            'update',
            "更新上传记录：{$this->record->original_filename}",
            [
                'upload_record_id' => $this->record->id,
                'filename' => $this->record->original_filename,
            ],
            $this->record
        );
    }
}
