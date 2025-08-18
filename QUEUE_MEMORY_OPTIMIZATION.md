# 队列内存优化指南 - 大内存服务器配置

## 问题描述
在处理大文件导入导出时，队列工作进程容易出现内存溢出错误：
```
PHP Fatal error: Allowed memory size of 134217728 bytes exhausted
```

## 解决方案 - 大内存服务器优化

### 1. 增加PHP内存限制

#### 方法一：在php.ini中设置
```ini
memory_limit = 20G
max_execution_time = 7200
```

#### 方法二：在代码中动态设置
```php
ini_set('memory_limit', '20G');
ini_set('max_execution_time', 7200);
```

### 2. 优化队列工作进程启动参数

#### Linux/Mac
```bash
php artisan queue:work \
    --memory=20480 \
    --timeout=7200 \
    --tries=3 \
    --max-jobs=2000 \
    --max-time=7200 \
    --sleep=3 \
    --verbose
```

#### Windows
```cmd
php artisan queue:work --memory=20480 --timeout=7200 --tries=3 --max-jobs=2000 --max-time=7200 --sleep=3 --verbose
```

### 3. 使用提供的启动脚本

#### Linux/Mac
```bash
chmod +x start-queue-worker.sh
./start-queue-worker.sh
```

#### Windows
```cmd
start-queue-worker.bat
```

### 4. 代码优化

#### 流式处理
- 使用 `WithChunkReading` 分批读取文件（5000行一批）
- 使用 `WithHeadingRow` 自动处理表头
- 批量插入数据（2000条一批），避免一次性加载大量数据

#### 内存管理
- 定期清理内存：`gc_collect_cycles()`
- 重置OPcache：`opcache_reset()`
- 监控内存使用情况（GB级别）

### 5. 环境变量配置

在 `.env` 文件中添加：
```env
QUEUE_MEMORY_LIMIT=20G
QUEUE_TIMEOUT=7200
QUEUE_RETRY_AFTER=90
```

### 6. 监控和调试

#### 查看内存使用情况
```php
$memoryUsage = memory_get_usage(true) / 1024 / 1024 / 1024; // GB
$memoryPeak = memory_get_peak_usage(true) / 1024 / 1024 / 1024; // GB
```

#### 查看队列状态
```bash
php artisan queue:work --once --verbose
```

### 7. 服务器配置建议

#### 生产环境（大内存服务器）
- 使用 Supervisor 管理队列进程
- 设置合理的进程数量（建议2-4个进程）
- 监控内存和CPU使用情况
- 每个进程分配20GB内存

#### 开发环境
- 使用 `php artisan queue:work --tries=1` 快速调试
- 设置较小的批量大小进行测试

## 性能优化参数

### 批量处理优化
- **批量插入大小**: 2000条记录
- **分块读取大小**: 5000行
- **内存限制**: 20GB
- **超时时间**: 2小时
- **最大任务数**: 2000个

### 数据库优化
- 确保有适当的索引
- 使用批量插入而不是单条插入
- 定期清理临时数据

## 常见问题

### Q: 仍然出现内存溢出怎么办？
A: 
1. 进一步增加内存限制到 30GB 或更高
2. 减少批量处理大小到 1000
3. 检查是否有内存泄漏

### Q: 处理速度很慢怎么办？
A:
1. 增加批量处理大小到 3000-5000
2. 优化数据库索引
3. 使用更快的存储设备（SSD）
4. 增加队列进程数量

### Q: 如何监控队列性能？
A:
1. 查看 Laravel 日志文件
2. 使用队列监控工具
3. 设置性能指标收集
4. 监控内存使用情况（GB级别）

## 最佳实践

1. **渐进式优化**：从2000条批量开始，根据性能调整
2. **监控内存**：定期检查内存使用情况（GB级别）
3. **错误处理**：设置合理的重试机制
4. **日志记录**：详细记录处理过程
5. **测试验证**：在生产环境部署前充分测试
6. **资源分配**：合理分配服务器资源，避免过度使用

## 大内存服务器优势

- **处理能力**: 可以同时处理多个大文件
- **批量大小**: 可以设置更大的批量处理大小
- **并发处理**: 可以运行多个队列进程
- **稳定性**: 减少内存溢出风险
- **性能**: 显著提高处理速度
