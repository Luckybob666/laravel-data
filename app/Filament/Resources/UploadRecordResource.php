<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UploadRecordResource\Pages;
use App\Models\UploadRecord;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Forms\Components\Section;
use App\Jobs\ProcessFileUpload;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Storage;

class UploadRecordResource extends Resource
{
    protected static ?string $model = UploadRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-up';

    protected static ?string $navigationLabel = '上传记录';

    protected static ?string $modelLabel = '上传记录';

    protected static ?string $pluralModelLabel = '上传记录';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('文件上传')
                    ->schema([
                        FileUpload::make('file')
                            ->label('选择文件')
                            ->maxSize(512000) // 500MB
                            ->helperText('支持 .xlsx 和 .csv 格式，最大 500MB')
                            ->required()
                            ->disk('public')
                            ->directory('uploads')
                            ->visible(fn ($livewire) => $livewire instanceof \App\Filament\Resources\UploadRecordResource\Pages\CreateUploadRecord),
                    ])->columns(1),

                Section::make('附加信息')
                    ->schema([
                        TextInput::make('country')
                            ->label('国家')
                            ->maxLength(255),
                        
                        TextInput::make('industry')
                            ->label('行业')
                            ->maxLength(255),
                        
                        TextInput::make('domain')
                            ->label('域名')
                            ->maxLength(255),
                        
                        Textarea::make('remarks')
                            ->label('备注')
                            ->rows(3)
                            ->maxLength(1000),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->columns([
                TextColumn::make('id')
                    ->label('上传ID')
                    ->sortable(),
                
                TextColumn::make('country')
                    ->label('国家')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('industry')
                    ->label('行业')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('domain')
                    ->label('域名')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('remarks')
                    ->label('备注')
                    ->limit(30),
                
                TextColumn::make('total_count')
                    ->label('总条数')
                    ->sortable(),
                
                TextColumn::make('success_count')
                    ->label('成功条数')
                    ->sortable(),
                
                TextColumn::make('duplicate_count')
                    ->label('重复条数')
                    ->sortable(),
                
                BadgeColumn::make('status')
                    ->label('状态')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'processing',
                        'success' => 'completed',
                        'danger' => 'failed',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => '等待处理',
                        'processing' => '处理中',
                        'completed' => '已完成',
                        'failed' => '处理失败',
                    }),
                
                TextColumn::make('user.name')
                    ->label('上传用户')
                    ->sortable(),
                
                TextColumn::make('created_at')
                    ->label('上传时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        'pending' => '等待处理',
                        'processing' => '处理中',
                        'completed' => '已完成',
                        'failed' => '处理失败',
                    ]),
                
                SelectFilter::make('user_id')
                    ->label('上传用户')
                    ->relationship('user', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('编辑')
                    ->visible(fn (UploadRecord $record): bool => $record->isCompleted())
                    ->before(function (UploadRecord $record) {
                        ActivityLog::log('edit', "编辑上传记录：上传ID {$record->id}");
                    }),
                
                Action::make('generate_download')
                    ->label('生成下载地址')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (UploadRecord $record): bool => $record->isCompleted())
                    ->action(function (UploadRecord $record) {
                        // 触发下载任务
                        \App\Jobs\ProcessFileDownload::dispatch(
                            ['upload_record_id' => $record->id],
                            'xlsx',
                            auth()->id()
                        );
                        
                        ActivityLog::log('download', "生成下载地址：上传ID {$record->id}");
                        
                        // 显示成功消息
                        \Filament\Notifications\Notification::make()
                            ->title('生成任务已添加')
                            ->body('文件正在后台生成中，完成后可在下载记录中查看。')
                            ->success()
                            ->send();
                    }),
                
                DeleteAction::make()
                    ->label('删除')
                    ->before(function (UploadRecord $record) {
                        // 删除该批次对应的所有数据记录
                        \App\Models\DataRecord::where('upload_record_id', $record->id)->delete();
                        
                        ActivityLog::log('delete', "删除上传记录：上传ID {$record->id}，同时删除了对应的数据记录");
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('批量删除')
                        ->before(function ($records) {
                            // 删除所有选中记录对应的数据记录
                            foreach ($records as $record) {
                                \App\Models\DataRecord::where('upload_record_id', $record->id)->delete();
                            }
                            
                            $ids = $records->pluck('id')->implode(', ');
                            ActivityLog::log('delete', "批量删除上传记录：上传ID {$ids}，同时删除了对应的数据记录");
                        }),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUploadRecords::route('/'),
            'create' => Pages\CreateUploadRecord::route('/create'),
            'edit' => Pages\EditUploadRecord::route('/{record}/edit'),
        ];
    }
}
