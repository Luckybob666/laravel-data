<?php

namespace App\Filament\Widgets;

use App\Models\DataRecord;
use App\Models\UploadRecord;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DataTrendsChart extends ChartWidget
{
    protected static ?string $heading = '数据趋势分析';
    
    protected static ?int $sort = 2;
    
    protected static string $color = 'success';
    
    public ?string $filter = '7d'; // 默认显示7天
    
    protected function getFilters(): ?array
    {
        return [
            '7d' => '最近7天',
            '30d' => '最近30天',
            '90d' => '最近90天',
        ];
    }
    
    protected function getData(): array
    {
        $days = match($this->filter) {
            '30d' => 30,
            '90d' => 90,
            default => 7,
        };
        
        $startDate = Carbon::now()->subDays($days - 1)->startOfDay();
        
        // 获取按日期和国家的数据统计
        $data = DB::table('data_records')
            ->join('upload_records', 'data_records.upload_record_id', '=', 'upload_records.id')
            ->select(
                DB::raw('DATE(data_records.created_at) as date'),
                'upload_records.country',
                DB::raw('COUNT(*) as count')
            )
            ->where('data_records.created_at', '>=', $startDate)
            ->groupBy('date', 'upload_records.country')
            ->orderBy('date')
            ->get();
        
        // 获取所有国家
        $countries = $data->pluck('country')->unique()->filter()->values();
        
        // 生成日期范围
        $dates = [];
        $dateLabels = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $dates[] = $date->format('Y-m-d');
            $dateLabels[] = $date->format('m/d');
        }
        
        // 构建图表数据
        $datasets = [];
        $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4', '#84CC16', '#F97316'];
        
        foreach ($countries as $index => $country) {
            $countryData = [];
            
            foreach ($dates as $date) {
                $count = $data->where('date', $date)->where('country', $country)->first()?->count ?? 0;
                $countryData[] = $count;
            }
            
            $datasets[] = [
                'label' => $country ?: '未知国家',
                'data' => $countryData,
                'borderColor' => $colors[$index % count($colors)],
                'backgroundColor' => $colors[$index % count($colors)] . '20',
                'tension' => 0.4,
                'fill' => false,
            ];
        }
        
        return [
            'datasets' => $datasets,
            'labels' => $dateLabels,
        ];
    }
    
    protected function getType(): string
    {
        return 'line';
    }
    
    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'position' => 'top',
                    'labels' => [
                        'usePointStyle' => true,
                        'padding' => 20,
                    ],
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'grid' => [
                        'color' => '#f1f5f9',
                    ],
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
            'interaction' => [
                'mode' => 'nearest',
                'axis' => 'x',
                'intersect' => false,
            ],
        ];
    }
}
