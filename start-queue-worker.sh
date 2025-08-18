#!/bin/bash

# 队列工作进程启动脚本
# 设置内存限制和优化参数 - 大内存服务器配置

echo "启动队列工作进程..."

# 设置PHP内存限制到20GB
export PHP_MEMORY_LIMIT=20G

# 启动队列工作进程，增加内存限制
php artisan queue:work \
    --memory=20480 \
    --timeout=7200 \
    --tries=3 \
    --max-jobs=2000 \
    --max-time=7200 \
    --sleep=3 \
    --verbose

echo "队列工作进程已启动"
