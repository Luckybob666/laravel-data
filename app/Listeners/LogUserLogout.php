<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class LogUserLogout
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
    public function handle(Logout $event): void
    {
        $user = $event->user;
        
        // 检查是否已经记录过这次登出（防止重复记录）
        $existingLog = ActivityLog::where('user_id', $user->id)
            ->where('action', 'logout')
            ->where('created_at', '>=', now()->subMinutes(1))
            ->first();
            
        if ($existingLog) {
            return; // 如果1分钟内已经有登出记录，则跳过
        }
        
        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'logout',
            'description' => "用户 {$user->name} 登出系统",
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
