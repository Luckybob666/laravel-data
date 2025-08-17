<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DownloadRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'filename',
        'file_path',
        'download_url',
        'record_count',
        'format',
        'upload_record_id',
        'user_id',
        'filters',
        'status',
        'error_message',
    ];

    protected $casts = [
        'filters' => 'array',
        'record_count' => 'integer',
    ];

    // 状态常量
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute(): string
    {
        return match($this->status) {
            self::STATUS_PROCESSING => '处理中',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_FAILED => '失败',
            default => '未知',
        };
    }

    /**
     * 检查是否已完成
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * 检查是否失败
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * 检查是否处理中
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * 用户关系
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 上传记录关系
     */
    public function uploadRecord()
    {
        return $this->belongsTo(UploadRecord::class);
    }
}
