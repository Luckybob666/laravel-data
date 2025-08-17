<?php

namespace App\Listeners;

use App\Events\FileDownloadCompleted;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Notifications\DatabaseNotification;

class SendFileDownloadNotification implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(FileDownloadCompleted $event): void
    {
        $user = User::find($event->userId);
        
        if ($user) {
            // 直接创建数据库通知，使用Filament兼容的格式
            $notification = new DatabaseNotification([
                'id' => \Illuminate\Support\Str::uuid(),
                'type' => 'Illuminate\Notifications\DatabaseNotification',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode([
                    'title' => '文件下载就绪',
                    'message' => "文件 {$event->filename} 已准备就绪，共 {$event->recordCount} 条记录",
                    'filename' => $event->filename,
                    'download_url' => $event->downloadUrl,
                    'record_count' => $event->recordCount,
                    'icon' => 'heroicon-o-arrow-down-tray',
                    'color' => 'success',
                ]),
                'read_at' => null,
            ]);
            
            $notification->save();
        }
    }
}
