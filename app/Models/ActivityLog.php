<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'description',
        'ip_address',
        'user_agent',
        'properties',
        'subject_type',
        'subject_id',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    /**
     * 获取操作用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取操作对象
     */
    public function subject()
    {
        return $this->morphTo();
    }

    /**
     * 记录活动日志
     */
    public static function log($action, $description, $properties = [], $subject = null)
    {
        $user = auth()->user();
        
        return static::create([
            'user_id' => $user ? $user->id : null,
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'properties' => $properties,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject ? $subject->id : null,
        ]);
    }

    /**
     * 获取操作类型文本
     */
    public function getActionTextAttribute()
    {
        $actionMap = [
            'login' => '登录',
            'logout' => '登出',
            'upload' => '上传数据',
            'edit' => '编辑记录',
            'delete' => '删除记录',
            'download' => '下载数据',
            'create' => '创建',
            'update' => '更新',
        ];

        return $actionMap[$this->action] ?? $this->action;
    }
}
