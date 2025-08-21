<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsedDataRecord extends Model
{
    protected $fillable = [
        'phone',
        'data',
        'upload_record_id',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * 获取关联的上传记录
     */
    public function uploadRecord(): BelongsTo
    {
        return $this->belongsTo(UsedUploadRecord::class, 'upload_record_id');
    }
}
