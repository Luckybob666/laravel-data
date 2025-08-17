<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUserActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // 只记录已认证用户的活动
        if (auth()->check()) {
            $user = auth()->user();
            $path = $request->path();
            $method = $request->method();

            // 记录登录活动
            if ($path === 'admin/login' && $method === 'POST') {
                ActivityLog::log(
                    'login',
                    "用户 {$user->name} 登录系统",
                    [
                        'user_id' => $user->id,
                        'email' => $user->email,
                    ]
                );
            }

            // 记录登出活动
            if ($path === 'admin/logout' && $method === 'POST') {
                ActivityLog::log(
                    'logout',
                    "用户 {$user->name} 登出系统",
                    [
                        'user_id' => $user->id,
                        'email' => $user->email,
                    ]
                );
            }
        }

        return $response;
    }
}
