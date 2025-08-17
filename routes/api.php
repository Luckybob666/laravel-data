<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DataController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// 数据管理API路由
Route::prefix('data')->group(function () {
    // 文件上传
    Route::post('/upload', [DataController::class, 'upload']);
    
    // 数据记录管理
    Route::get('/records', [DataController::class, 'index']);
    Route::put('/records/{id}', [DataController::class, 'update']);
    Route::delete('/records/{id}', [DataController::class, 'destroy']);
    
    // 数据导出
    Route::post('/export', [DataController::class, 'export']);
    
    // 上传记录
    Route::get('/upload-records', [DataController::class, 'uploadRecords']);
    
    // 活动日志
    Route::get('/activity-logs', [DataController::class, 'activityLogs']);
});
