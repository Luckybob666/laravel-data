<?php

namespace App\Filament\Resources\UploadRecordResource\Pages;

use App\Filament\Resources\UploadRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Filament\Tables\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

class ListUploadRecords extends ListRecords
{
    protected static string $resource = UploadRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->bulkActions([
                BulkAction::make('show_statistics')
                    ->label('显示统计')
                    ->icon('heroicon-o-chart-bar')
                    ->color('info')
                    ->action(function (Collection $records) {
                        $totalCount = $records->sum('total_count');
                        $successCount = $records->sum('success_count');
                        $duplicateCount = $records->sum('duplicate_count');
                        
                        Notification::make()
                            ->title('选中记录统计')
                            ->body("总上传数量：{$totalCount}\n成功数量：{$successCount}\n重复数量：{$duplicateCount}")
                            ->info()
                            ->persistent()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
                
                ...$table->getBulkActions(),
            ]);
    }
}
