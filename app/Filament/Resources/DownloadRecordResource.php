<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DownloadRecordResource\Pages;
use App\Models\DownloadRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Storage;

class DownloadRecordResource extends Resource
{
    protected static ?string $model = DownloadRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationLabel = '下载记录';

    protected static ?string $modelLabel = '下载记录';

    protected static ?string $pluralModelLabel = '下载记录';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('filename')
                    ->label('文件名')
                    ->disabled(),
                Forms\Components\TextInput::make('download_url')
                    ->label('下载链接')
                    ->disabled(),
                Forms\Components\TextInput::make('record_count')
                    ->label('记录数量')
                    ->disabled(),
                Forms\Components\TextInput::make('format')
                    ->label('文件格式')
                    ->disabled(),
                Forms\Components\TextInput::make('status')
                    ->label('状态')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('filename')
                    ->label('文件名')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('upload_record_id')
                    ->label('来源上传ID')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => $state ? "上传ID: {$state}" : '全部数据'),
                TextColumn::make('record_count')
                    ->label('记录数量')
                    ->sortable(),
                TextColumn::make('format')
                    ->label('格式')
                    ->badge(),
                BadgeColumn::make('status')
                    ->label('状态')
                    ->colors([
                        'warning' => 'processing',
                        'success' => 'completed',
                        'danger' => 'failed',
                    ])
                    ->formatStateUsing(fn (string $state): string => match($state) {
                        'processing' => '处理中',
                        'completed' => '已完成',
                        'failed' => '失败',
                        default => $state,
                    }),
                TextColumn::make('user.name')
                    ->label('用户')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        'processing' => '处理中',
                        'completed' => '已完成',
                        'failed' => '失败',
                    ]),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('用户')
                    ->relationship('user', 'name'),
            ])
            ->actions([
                Action::make('download')
                    ->label('下载')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn (DownloadRecord $record): string => $record->download_url)
                    ->openUrlInNewTab()
                    ->visible(fn (DownloadRecord $record): bool => $record->isCompleted()),
                DeleteAction::make()
                    ->label('删除')
                    ->before(function (DownloadRecord $record) {
                        // 删除文件
                        if ($record->file_path && Storage::disk('public')->exists($record->file_path)) {
                            Storage::disk('public')->delete($record->file_path);
                        }
                    })
                    ->after(function (DownloadRecord $record) {
                        // 记录活动日志
                        \App\Models\ActivityLog::log(
                            'delete',
                            "删除下载记录：{$record->filename}",
                            [
                                'download_record_id' => $record->id,
                                'filename' => $record->filename,
                            ]
                        );
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('批量删除')
                        ->before(function ($records) {
                            // 删除文件
                            foreach ($records as $record) {
                                if ($record->file_path && Storage::disk('public')->exists($record->file_path)) {
                                    Storage::disk('public')->delete($record->file_path);
                                }
                            }
                        })
                        ->after(function ($records) {
                            // 记录活动日志
                            $filenames = $records->pluck('filename')->implode(', ');
                            \App\Models\ActivityLog::log(
                                'delete',
                                "批量删除下载记录：{$filenames}",
                                [
                                    'count' => $records->count(),
                                    'filenames' => $filenames,
                                ]
                            );
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDownloadRecords::route('/'),
        ];
    }
}
