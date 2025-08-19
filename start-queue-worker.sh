#!/bin/bash

# 设置内存限制
export PHP_MEMORY_LIMIT="20G"

# 设置最大执行时间（秒）
export PHP_MAX_EXECUTION_TIME=7200

# 设置队列工作器参数
QUEUE_WORKER_PARAMS="--memory=20480 --timeout=7200 --tries=3 --max-jobs=100 --max-time=3600"

echo "启动队列工作器..."
echo "内存限制: $PHP_MEMORY_LIMIT"
echo "最大执行时间: $PHP_MAX_EXECUTION_TIME 秒"
echo "队列工作器参数: $QUEUE_WORKER_PARAMS"

# 启动队列工作器
php artisan queue:work database $QUEUE_WORKER_PARAMS
