<?php

namespace App\Filament\Resources\UsedUploadRecordResource\Pages;

use App\Filament\Resources\UsedUploadRecordResource;
use App\Models\ActivityLog;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUsedUploadRecord extends EditRecord
{
    protected static string $resource = UsedUploadRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // 只允许编辑特定字段
        return array_intersect_key($data, array_flip(['country', 'industry', 'domain', 'remarks']));
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // 只保存允许编辑的字段
        return array_intersect_key($data, array_flip(['country', 'industry', 'domain', 'remarks']));
    }

    protected function afterSave(): void
    {
        // 记录活动日志
        ActivityLog::log(
            'update',
            "更新已用数据上传记录：上传ID {$this->record->id}",
            [
                'upload_record_id' => $this->record->id,
            ],
            $this->record
        );
    }
}
