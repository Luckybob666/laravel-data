<?php

namespace App\Filament\Resources\RawUploadRecordResource\Pages;

use App\Filament\Resources\RawUploadRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRawUploadRecords extends ListRecords
{
    protected static string $resource = RawUploadRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
