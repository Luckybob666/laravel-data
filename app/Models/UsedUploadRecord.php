<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UsedUploadRecord extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'filename',
        'original_filename',
        'country',
        'industry',
        'remarks',
        'domain',
        'total_count',
        'success_count',
        'duplicate_count',
        'status',
        'error_message',
        'file_path',
        'user_id',
    ];

    protected $casts = [
        'total_count' => 'integer',
        'success_count' => 'integer',
        'duplicate_count' => 'integer',
    ];

    /**
     * 获取关联的用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取关联的数据记录
     */
    public function dataRecords(): HasMany
    {
        return $this->hasMany(UsedDataRecord::class, 'upload_record_id');
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute()
    {
        $statusMap = [
            self::STATUS_PENDING => '等待处理',
            self::STATUS_PROCESSING => '处理中',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_FAILED => '处理失败',
        ];

        return $statusMap[$this->status] ?? $this->status;
    }

    /**
     * 检查是否已完成
     */
    public function isCompleted()
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * 检查是否失败
     */
    public function isFailed()
    {
        return $this->status === self::STATUS_FAILED;
    }
}
