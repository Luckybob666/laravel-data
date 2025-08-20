<?php

require_once 'vendor/autoload.php';

use App\Helpers\LargeFileProcessor;
use Illuminate\Support\Facades\Log;

// 模拟测试数据
$testData = [
    ['phone' => '13800138001', 'name' => '张三', 'email' => 'zhangsan@example.com'],
    ['phone' => '13800138002', 'name' => '李四', 'email' => 'lisi@example.com'],
    ['phone' => '13800138001', 'name' => '张三2', 'email' => 'zhangsan2@example.com'], // 重复手机号
    ['phone' => '13800138003', 'name' => '王五', 'email' => 'wangwu@example.com'],
    ['phone' => '13800138002', 'name' => '李四2', 'email' => 'lisi2@example.com'], // 重复手机号
];

echo "开始测试上传修复...\n";

try {
    // 创建处理器实例
    $processor = new LargeFileProcessor(1, 'raw', 1000);
    
    // 处理测试数据
    foreach ($testData as $row) {
        $processor->processRow($row);
    }
    
    // 获取统计结果
    $stats = $processor->getStats();
    
    echo "测试完成！\n";
    echo "处理行数: " . $stats['processed_rows'] . "\n";
    echo "成功插入: " . $stats['success_count'] . "\n";
    echo "重复数据: " . $stats['duplicate_count'] . "\n";
    
    // 清理
    $processor->cleanup();
    
} catch (Exception $e) {
    echo "测试失败: " . $e->getMessage() . "\n";
}
