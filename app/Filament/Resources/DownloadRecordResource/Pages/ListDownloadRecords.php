<?php

namespace App\Filament\Resources\DownloadRecordResource\Pages;

use App\Filament\Resources\DownloadRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDownloadRecords extends ListRecords
{
    protected static string $resource = DownloadRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
