<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FileDownloadReady extends Notification
{
    use Queueable;

    protected $filename;
    protected $downloadUrl;
    protected $recordCount;

    /**
     * Create a new notification instance.
     */
    public function __construct($filename, $downloadUrl = null, $recordCount = 0)
    {
        $this->filename = $filename;
        $this->downloadUrl = $downloadUrl;
        $this->recordCount = $recordCount;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => '文件下载就绪',
            'message' => "文件 {$this->filename} 已准备就绪，共 {$this->recordCount} 条记录",
            'filename' => $this->filename,
            'download_url' => $this->downloadUrl,
            'record_count' => $this->recordCount,
        ];
    }
}
