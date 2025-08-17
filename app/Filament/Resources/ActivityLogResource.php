<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use App\Models\ActivityLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Columns\TextColumn;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = '活动日志';

    protected static ?string $modelLabel = '活动日志';

    protected static ?string $pluralModelLabel = '活动日志';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('日志信息')
                    ->schema([
                        TextInput::make('action')
                            ->label('操作类型')
                            ->disabled(),
                        
                        TextInput::make('description')
                            ->label('操作描述')
                            ->disabled(),
                        
                        TextInput::make('user.name')
                            ->label('操作用户')
                            ->disabled(),
                        
                        TextInput::make('ip_address')
                            ->label('IP地址')
                            ->disabled(),
                        
                        TextInput::make('user_agent')
                            ->label('用户代理')
                            ->disabled(),
                        
                        TextInput::make('properties')
                            ->label('额外属性')
                            ->formatStateUsing(function ($state) {
                                if (is_array($state)) {
                                    return json_encode($state, JSON_UNESCAPED_UNICODE);
                                }
                                return $state;
                            })
                            ->disabled(),
                        
                        TextInput::make('created_at')
                            ->label('操作时间')
                            ->formatStateUsing(function ($state) {
                                return $state ? $state->format('Y-m-d H:i:s') : '';
                            })
                            ->disabled(),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('action')
                    ->label('操作类型')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'login' => '登录',
                        'logout' => '登出',
                        'upload' => '上传数据',
                        'edit' => '编辑记录',
                        'delete' => '删除记录',
                        'download' => '下载数据',
                        'create' => '创建',
                        'update' => '更新',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'login', 'logout' => 'info',
                        'upload', 'download' => 'success',
                        'edit', 'update' => 'warning',
                        'delete' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('description')
                    ->label('操作描述')
                    ->limit(50)
                    ->searchable(),
                
                TextColumn::make('user.name')
                    ->label('操作用户')
                    ->sortable(),
                
                TextColumn::make('ip_address')
                    ->label('IP地址')
                    ->searchable(),
                
                TextColumn::make('created_at')
                    ->label('操作时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->label('操作类型')
                    ->options([
                        'login' => '登录',
                        'logout' => '登出',
                        'upload' => '上传数据',
                        'edit' => '编辑记录',
                        'delete' => '删除记录',
                        'download' => '下载数据',
                        'create' => '创建',
                        'update' => '更新',
                    ]),
                
                SelectFilter::make('user_id')
                    ->label('操作用户')
                    ->relationship('user', 'name'),
                
                Filter::make('created_at')
                    ->label('操作时间')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('从'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('到'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                // 日志不可编辑和删除
            ])
            ->bulkActions([
                // 日志不可批量删除
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListActivityLogs::route('/'),
        ];
    }
}
