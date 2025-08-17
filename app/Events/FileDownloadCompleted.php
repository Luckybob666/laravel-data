<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FileDownloadCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $filename;
    public $downloadUrl;
    public $recordCount;
    public $userId;

    /**
     * Create a new event instance.
     */
    public function __construct($filename, $downloadUrl, $recordCount, $userId)
    {
        $this->filename = $filename;
        $this->downloadUrl = $downloadUrl;
        $this->recordCount = $recordCount;
        $this->userId = $userId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->userId),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
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
