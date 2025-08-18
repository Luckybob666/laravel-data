<?php

namespace App\Filament\Resources\RawUploadRecordResource\Pages;

use App\Filament\Resources\RawUploadRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRawUploadRecord extends EditRecord
{
    protected static string $resource = RawUploadRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
