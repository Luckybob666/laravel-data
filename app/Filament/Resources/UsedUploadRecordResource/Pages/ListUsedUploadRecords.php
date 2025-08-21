<?php

namespace App\Filament\Resources\UsedUploadRecordResource\Pages;

use App\Filament\Resources\UsedUploadRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsedUploadRecords extends ListRecords
{
    protected static string $resource = UsedUploadRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
