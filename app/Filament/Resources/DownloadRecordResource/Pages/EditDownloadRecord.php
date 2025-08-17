<?php

namespace App\Filament\Resources\DownloadRecordResource\Pages;

use App\Filament\Resources\DownloadRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDownloadRecord extends EditRecord
{
    protected static string $resource = DownloadRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
