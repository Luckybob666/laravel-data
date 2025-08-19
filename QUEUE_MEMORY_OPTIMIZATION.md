# 队列内存优化说明

## 问题分析

原始代码存在以下问题：
1. `Method Maatwebsite\Excel\Excel::getStats does not exist` - Maatwebsite Excel 3.1 版本 API 不兼容
2. 内存使用过高，导致处理大文件时失败
3. 重复检查效率低，每个手机号都单独查询数据库

## 解决方案

### 1. 修复 Maatwebsite Excel 兼容性问题

- 将匿名类改为独立的 `ExcelImportHandler` 类
- 使用 `LargeFileProcessor` 来处理数据逻辑
- 正确获取处理统计信息

### 2. 内存优化

- 减少批量大小从 2000 到 1000
- 减少分块大小从 5000 到 3000
- 添加内存清理机制
- 使用批量查询减少数据库访问

### 3. 性能优化

- 批量检查重复手机号（每500个一批）
- 缓存已存在的手机号
- 减少数据库查询次数
- 优化内存使用模式

## 使用方法

### 启动队列工作器

**Linux/Mac:**
```bash
chmod +x start-queue-worker.sh
./start-queue-worker.sh
```

**Windows:**
```cmd
start-queue-worker.bat
```

### 队列工作器参数说明

- `--memory=20480`: 设置内存限制为 20GB
- `--timeout=7200`: 设置单个任务超时时间为 2 小时
- `--tries=3`: 失败重试 3 次
- `--max-jobs=100`: 处理 100 个任务后重启工作器
- `--max-time=3600`: 工作器运行 1 小时后重启

## 监控和日志

### 查看队列状态
```bash
php artisan queue:monitor
```

### 查看失败的任务
```bash
php artisan queue:failed
```

### 重试失败的任务
```bash
php artisan queue:retry all
```

## 性能建议

1. **服务器配置**
   - 确保服务器有足够的内存（建议 32GB+）
   - 使用 SSD 存储提高 I/O 性能
   - 配置适当的 PHP 内存限制

2. **数据库优化**
   - 在 `phone` 字段上创建索引
   - 定期清理重复数据
   - 监控数据库连接数

3. **队列优化**
   - 使用 Redis 队列替代数据库队列（可选）
   - 配置多个队列工作器
   - 监控队列长度和处理速度

## 故障排除

### 常见问题

1. **内存不足**
   - 检查服务器内存使用情况
   - 减少批量大小
   - 增加服务器内存

2. **处理超时**
   - 增加超时时间
   - 检查文件大小
   - 优化数据处理逻辑

3. **数据库连接超时**
   - 检查数据库连接配置
   - 增加连接超时时间
   - 使用连接池

### 日志位置

- Laravel 日志: `storage/logs/laravel.log`
- 队列日志: 在 Laravel 日志中查看
- 系统日志: 检查系统日志文件

## 更新日志

### v1.1.0 (2025-08-19)
- 修复 Maatwebsite Excel 3.1 兼容性问题
- 优化内存使用和性能
- 添加批量重复检查
- 创建队列工作器启动脚本
