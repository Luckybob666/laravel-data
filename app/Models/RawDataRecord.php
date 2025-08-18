<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RawDataRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone',
        'data',
        'upload_record_id',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * 获取上传记录
     */
    public function uploadRecord()
    {
        return $this->belongsTo(RawUploadRecord::class, 'upload_record_id');
    }

    /**
     * 获取完整的JSON数据
     */
    public function getFullDataAttribute()
    {
        $data = $this->data ?? [];
        return array_merge(['phone' => $this->phone], $data);
    }

    /**
     * 格式化手机号码
     */
    public function setPhoneAttribute($value)
    {
        $this->attributes['phone'] = trim($value);
    }
}
