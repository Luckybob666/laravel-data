<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardStats;
use App\Filament\Widgets\DataTrendsChart;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    
    protected static ?int $navigationSort = -2;
    
    public function getTitle(): string
    {
        return '数据仪表盘';
    }
    
    public function getWidgets(): array
    {
        return [
            DashboardStats::class,
            DataTrendsChart::class,
        ];
    }
}
