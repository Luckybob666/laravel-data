<?php
/**
 * 大文件处理优化配置脚本
 * 运行此脚本以优化PHP配置，支持大文件上传和处理
 */

echo "=== 大文件处理优化配置 ===\n\n";

// 检查当前PHP配置
echo "当前PHP配置：\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "max_input_time: " . ini_get('max_input_time') . "\n\n";

// 建议的配置
echo "建议的配置（需要在php.ini中设置）：\n";
echo "memory_limit = 1G\n";
echo "max_execution_time = 1800\n";
echo "upload_max_filesize = 500M\n";
echo "post_max_size = 500M\n";
echo "max_input_time = 1800\n\n";

// 检查Laravel配置
echo "Laravel配置建议：\n";
echo "1. 确保 .env 文件中设置了合适的队列配置\n";
echo "2. 使用 Redis 队列驱动以获得更好的性能\n";
echo "3. 配置多个队列工作进程\n\n";

echo "队列启动命令：\n";
echo "php artisan queue:work --timeout=1800 --tries=3 --memory=1024\n";
echo "php artisan queue:work --queue=high,default --timeout=1800 --tries=3 --memory=1024\n\n";

echo "监控命令：\n";
echo "php artisan queue:monitor\n";
echo "php artisan queue:failed\n";
echo "php artisan queue:retry all\n\n";

echo "性能监控：\n";
echo "1. 使用 Laravel Telescope 监控性能\n";
echo "2. 监控内存使用情况\n";
echo "3. 监控队列处理时间\n\n";

echo "=== 配置完成 ===\n";
