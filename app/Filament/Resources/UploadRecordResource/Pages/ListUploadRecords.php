<?php

namespace App\Filament\Resources\UploadRecordResource\Pages;

use App\Filament\Resources\UploadRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUploadRecords extends ListRecords
{
    protected static string $resource = UploadRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
