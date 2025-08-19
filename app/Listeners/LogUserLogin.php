<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogUserLogin
{

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
    public function handle(Login $event): void
    {
        $user = $event->user;
        
        // 检查是否已经记录过这次登录（防止重复记录）
        $existingLog = ActivityLog::where('user_id', $user->id)
            ->where('action', 'login')
            ->where('created_at', '>=', now()->subMinutes(1))
            ->first();
            
        if ($existingLog) {
            return; // 如果1分钟内已经有登录记录，则跳过
        }
        
        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'login',
            'description' => "用户 {$user->name} 登录系统",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'properties' => [
                'user_id' => $user->id,
                'email' => $user->email,
                'guard' => $event->guard,
            ],
        ]);
    }
}
