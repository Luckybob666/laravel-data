<?php

require_once 'vendor/autoload.php';

use App\Helpers\LargeFileProcessor;
use Illuminate\Support\Facades\Log;

// 模拟测试数据（包含空行和重复数据）
$testData = [
    ['phone' => '13800138001', 'name' => '张三', 'email' => 'zhangsan@example.com'],
    ['phone' => '13800138002', 'name' => '李四', 'email' => 'lisi@example.com'],
    ['phone' => '13800138001', 'name' => '张三2', 'email' => 'zhangsan2@example.com'], // 重复手机号
    ['phone' => '13800138003', 'name' => '王五', 'email' => 'wangwu@example.com'],
    ['phone' => '13800138002', 'name' => '李四2', 'email' => 'lisi2@example.com'], // 重复手机号
    ['phone' => '', 'name' => '空手机号', 'email' => 'empty@example.com'], // 空手机号
    ['phone' => null, 'name' => 'NULL手机号', 'email' => 'null@example.com'], // NULL手机号
    ['phone' => '13800138004', 'name' => '赵六', 'email' => 'zhaoliu@example.com'],
];

echo "开始测试上传修复...\n";

try {
    // 创建处理器实例
    $processor = new LargeFileProcessor('raw', 1);
    
    // 处理测试数据
    foreach ($testData as $row) {
        $processor->processRow($row);
    }
    
    // 获取统计结果
    $stats = $processor->getStats();
    
    echo "测试完成！\n";
    echo "总行数: " . $stats['total_rows'] . "\n";
    echo "有效处理行数: " . $stats['processed_rows'] . "\n";
    echo "成功插入: " . $stats['success_count'] . "\n";
    echo "重复数据: " . $stats['duplicate_count'] . "\n";
    
    // 验证计算逻辑
    $expectedTotal = count($testData);
    $expectedValid = 6; // 有手机号的行数（不包括空和NULL）
    $expectedSuccess = 4; // 成功插入的数量
    $expectedDuplicate = 2; // 重复的数量
    
    echo "\n验证结果:\n";
    echo "总行数是否正确: " . ($stats['total_rows'] == $expectedTotal ? "✓" : "✗") . "\n";
    echo "有效处理行数是否正确: " . ($stats['processed_rows'] == $expectedValid ? "✓" : "✗") . "\n";
    echo "成功插入是否正确: " . ($stats['success_count'] == $expectedSuccess ? "✓" : "✗") . "\n";
    echo "重复数据是否正确: " . ($stats['duplicate_count'] == $expectedDuplicate ? "✓" : "✗") . "\n";
    
    // 清理
    $processor->cleanup();
    
} catch (Exception $e) {
    echo "测试失败: " . $e->getMessage() . "\n";
}
