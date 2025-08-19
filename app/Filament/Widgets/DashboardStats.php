<?php

namespace App\Filament\Widgets;

use App\Models\DataRecord;
use App\Models\DownloadRecord;
use App\Models\UploadRecord;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStats extends BaseWidget
{
    protected function getStats(): array
    {
        // 获取统计数据
        $totalDataRecords = DataRecord::count();
        $totalUploadRecords = UploadRecord::count();
        $totalDownloadRecords = DownloadRecord::count();
        
        // 获取今日数据
        $todayDataRecords = DataRecord::whereDate('created_at', today())->count();
        $todayUploadRecords = UploadRecord::whereDate('created_at', today())->count();
        $todayDownloadRecords = DownloadRecord::whereDate('created_at', today())->count();
        
        // 获取处理中的任务
        $processingUploads = UploadRecord::where('status', UploadRecord::STATUS_PROCESSING)->count();
        $processingDownloads = DownloadRecord::where('status', DownloadRecord::STATUS_PROCESSING)->count();
        
        // 获取成功处理的数据
        $completedUploads = UploadRecord::where('status', UploadRecord::STATUS_COMPLETED)->count();
        $completedDownloads = DownloadRecord::where('status', DownloadRecord::STATUS_COMPLETED)->count();

        return [
            Stat::make('总数据记录', number_format($totalDataRecords))
                ->description('今日新增: ' . number_format($todayDataRecords))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, 17]),

            Stat::make('上传记录', number_format($totalUploadRecords))
                ->description('处理中: ' . $processingUploads . ' | 已完成: ' . $completedUploads)
                ->descriptionIcon('heroicon-m-cloud-arrow-up')
                ->color('info')
                ->chart([17, 16, 14, 15, 14, 13, 12]),

            Stat::make('下载记录', number_format($totalDownloadRecords))
                ->description('处理中: ' . $processingDownloads . ' | 已完成: ' . $completedDownloads)
                ->descriptionIcon('heroicon-m-cloud-arrow-down')
                ->color('warning')
                ->chart([15, 10, 5, 2, 10, 3, 9]),

            Stat::make('今日活跃', number_format($todayDataRecords + $todayUploadRecords + $todayDownloadRecords))
                ->description('数据: ' . $todayDataRecords . ' | 上传: ' . $todayUploadRecords . ' | 下载: ' . $todayDownloadRecords)
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('primary')
                ->chart([9, 3, 10, 5, 15, 4, 17]),
        ];
    }
}
