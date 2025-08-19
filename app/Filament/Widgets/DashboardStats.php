<?php

namespace App\Filament\Widgets;

use App\Models\DataRecord;
use App\Models\RawDataRecord;
use App\Models\DownloadRecord;
use App\Models\UploadRecord;
use App\Models\RawUploadRecord;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStats extends BaseWidget
{
    protected function getStats(): array
    {
        // 获取统计数据
        $totalDataRecords = DataRecord::count() + RawDataRecord::count();
        $refinedDataRecords = DataRecord::count();
        $rawDataRecords = RawDataRecord::count();
        $totalUploadRecords = UploadRecord::count() + RawUploadRecord::count();
        $totalDownloadRecords = DownloadRecord::count();
        
        // 获取今日数据
        $todayDataRecords = DataRecord::whereDate('created_at', today())->count();
        $todayRawDataRecords = RawDataRecord::whereDate('created_at', today())->count();
        $todayUploadRecords = UploadRecord::whereDate('created_at', today())->count();
        $todayRawUploadRecords = RawUploadRecord::whereDate('created_at', today())->count();
        $todayDownloadRecords = DownloadRecord::whereDate('created_at', today())->count();
        
        // 获取处理中的任务
        $processingUploads = UploadRecord::where('status', UploadRecord::STATUS_PROCESSING)->count();
        $processingRawUploads = RawUploadRecord::where('status', RawUploadRecord::STATUS_PROCESSING)->count();
        $processingDownloads = DownloadRecord::where('status', DownloadRecord::STATUS_PROCESSING)->count();
        
        // 获取成功处理的数据
        $completedUploads = UploadRecord::where('status', UploadRecord::STATUS_COMPLETED)->count();
        $completedRawUploads = RawUploadRecord::where('status', RawUploadRecord::STATUS_COMPLETED)->count();
        $completedDownloads = DownloadRecord::where('status', DownloadRecord::STATUS_COMPLETED)->count();

        return [
            Stat::make('总数据记录', number_format($totalDataRecords))
                ->description('精数据: ' . number_format($refinedDataRecords) . ' | 粗数据: ' . number_format($rawDataRecords))
                ->descriptionIcon('heroicon-o-server')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, 17]),

            Stat::make('精数据记录', number_format($refinedDataRecords))
                ->description('今日新增: ' . number_format($todayDataRecords))
                ->descriptionIcon('heroicon-o-check-badge')
                ->color('info')
                ->chart([17, 16, 14, 15, 14, 13, 12]),

            Stat::make('粗数据记录', number_format($rawDataRecords))
                ->description('今日新增: ' . number_format($todayRawDataRecords))
                ->descriptionIcon('heroicon-o-document')
                ->color('warning')
                ->chart([15, 10, 5, 2, 10, 3, 9]),

            Stat::make('上传记录', number_format($totalUploadRecords))
                ->description('精数据: ' . $completedUploads . ' | 粗数据: ' . $completedRawUploads . ' | 处理中: ' . ($processingUploads + $processingRawUploads))
                ->descriptionIcon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->chart([9, 3, 10, 5, 15, 4, 17]),

            Stat::make('下载记录', number_format($totalDownloadRecords))
                ->description('已完成: ' . $completedDownloads . ' | 处理中: ' . $processingDownloads)
                ->descriptionIcon('heroicon-o-arrow-down-tray')
                ->color('danger')
                ->chart([12, 8, 15, 6, 18, 9, 14]),

            Stat::make('今日活跃', number_format($todayDataRecords + $todayRawDataRecords + $todayUploadRecords + $todayRawUploadRecords + $todayDownloadRecords))
                ->description('数据: ' . ($todayDataRecords + $todayRawDataRecords) . ' | 上传: ' . ($todayUploadRecords + $todayRawUploadRecords) . ' | 下载: ' . $todayDownloadRecords)
                ->descriptionIcon('heroicon-o-calendar')
                ->color('gray')
                ->chart([5, 8, 12, 6, 9, 11, 7]),
        ];
    }
}
