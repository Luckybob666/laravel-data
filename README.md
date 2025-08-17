# 数据管理系统

基于 Laravel + Filament 开发的数据存储、过滤和导出管理系统。

## 功能特性

### 📊 数据管理
- **文件上传**：支持 .xlsx 和 .csv 格式，最大 500MB
- **智能解析**：自动识别表头，保留原始字段名
- **数据去重**：基于手机号码自动去重
- **批量处理**：支持大文件批量插入，优化性能

### 📋 记录管理
- **上传记录**：显示上传统计（总数、成功数、重复数）
- **下载记录**：管理导出的文件，支持删除
- **活动日志**：记录所有操作，不可编辑

### 🔄 队列处理
- **异步处理**：文件上传和下载使用队列
- **性能优化**：批量插入，内存优化
- **错误重试**：自动重试机制

### 📤 数据导出
- **格式支持**：Excel (.xlsx) 和 CSV 格式
- **原始表头**：保持原始Excel文件的字段名
- **文件管理**：自动清理临时文件

## 技术栈

- **后端框架**：Laravel 11
- **管理面板**：Filament 3
- **数据库**：MySQL 8.0+
- **队列系统**：Laravel Queue (Database Driver)
- **文件处理**：Maatwebsite Excel
- **前端**：Alpine.js + Tailwind CSS

## 系统要求

- PHP 8.2+
- MySQL 8.0+ 或 MariaDB 10.5+
- Composer 2.0+
- Node.js 18+ (可选，用于前端构建)
- 内存：建议 2GB+
- 存储：根据数据量确定

## 安装部署

### 1. 环境准备

#### PHP 配置优化（php.ini）
```ini
memory_limit = 1G
max_execution_time = 1800
upload_max_filesize = 500M
post_max_size = 500M
max_input_time = 1800
```

#### 系统依赖安装
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install php8.2 php8.2-mysql php8.2-xml php8.2-mbstring php8.2-zip php8.2-curl php8.2-gd php8.2-bcmath

# CentOS/RHEL
sudo yum install php php-mysql php-xml php-mbstring php-zip php-curl php-gd php-bcmath
```

### 2. 项目部署

#### 步骤1：克隆项目
```bash
git clone <your-repository-url>
cd your-project-name
```

#### 步骤2：安装依赖
```bash
composer install --optimize-autoloader --no-dev
```

#### 步骤3：环境配置
```bash
# 复制环境配置文件
cp .env.example .env

# 生成应用密钥
php artisan key:generate
```

#### 步骤4：编辑 .env 文件
使用编辑器打开 `.env` 文件，修改以下配置：
```env
APP_NAME="数据管理系统"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password

QUEUE_CONNECTION=database
CACHE_DRIVER=file
SESSION_DRIVER=file
SESSION_LIFETIME=120
```

#### 步骤5：数据库设置
```bash
# 登录MySQL
mysql -u root -p

# 在MySQL中执行以下命令
CREATE DATABASE your_database_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'your_username'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON your_database_name.* TO 'your_username'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# 运行数据库迁移
php artisan migrate

# 创建存储链接
php artisan storage:link
```

#### 步骤6：创建必要目录和设置权限
```bash
# 创建上传和导出目录
mkdir -p storage/app/public/uploads
mkdir -p storage/app/public/exports

# 设置目录权限
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# 设置文件所有者（Linux系统）
sudo chown -R www-data:www-data /path/to/your/project
```

#### 步骤7：创建管理员账户
```bash
php artisan make:filament-user
```

#### 步骤8：优化缓存
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 3. 队列配置

#### 创建队列表
```bash
php artisan queue:table
php artisan migrate
```

#### 启动队列工作进程
```bash
# 开发环境
php artisan queue:work --timeout=1800 --tries=3 --memory=1024

# 生产环境（使用 Supervisor）
```

#### Supervisor 配置（生产环境）
```bash
# 安装Supervisor
sudo apt install supervisor

# 创建配置文件
sudo nano /etc/supervisor/conf.d/laravel-worker.conf
```

在配置文件中添加以下内容：
```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work --timeout=1800 --tries=3 --memory=1024 --sleep=3
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/worker.log
stopwaitsecs=3600
```

启动 Supervisor：
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laravel-worker:*
```

### 4. Web 服务器配置

#### Nginx 配置
创建配置文件：
```bash
sudo nano /etc/nginx/sites-available/your-domain.com
```

添加以下配置：
```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/your/project/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    # 文件上传大小限制
    client_max_body_size 500M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        
        # 增加超时时间
        fastcgi_read_timeout 1800;
        fastcgi_send_timeout 1800;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # 静态文件缓存
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

启用站点：
```bash
sudo ln -s /etc/nginx/sites-available/your-domain.com /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

#### Apache 配置
确保 `.htaccess` 文件存在并包含：
```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

### 5. 安全配置

#### 文件权限设置
```bash
# 设置正确的文件权限
sudo chown -R www-data:www-data /path/to/your/project
sudo chmod -R 755 /path/to/your/project
sudo chmod -R 775 /path/to/your/project/storage
sudo chmod -R 775 /path/to/your/project/bootstrap/cache
```

#### SSL 证书配置（推荐）
```bash
# 使用 Let's Encrypt
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.com
```

### 6. 性能优化

#### 缓存配置
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### 数据库优化
```sql
-- 添加索引（已包含在迁移中）
-- 定期优化表
OPTIMIZE TABLE data_records;
OPTIMIZE TABLE upload_records;
OPTIMIZE TABLE download_records;
```

## 使用说明

### 管理员账户
首次访问系统需要创建管理员账户：
```bash
php artisan make:filament-user
```

### 功能使用

1. **数据上传**
   - 访问 `/admin/upload-records`
   - 点击"创建上传记录"
   - 选择文件并填写附加信息
   - 系统自动处理并去重

2. **数据导出**
   - 在上传记录列表点击"生成下载地址"
   - 在下载记录页面查看和下载文件
   - 支持删除下载记录和文件

3. **活动监控**
   - 查看活动日志了解系统操作
   - 监控队列处理状态

## 维护命令

### 日常维护
```bash
# 清理过期文件
php artisan storage:clear

# 清理日志
php artisan log:clear

# 优化数据库
php artisan db:optimize

# 检查队列状态
php artisan queue:monitor
```

### 故障排查
```bash
# 查看失败的任务
php artisan queue:failed

# 重试失败的任务
php artisan queue:retry all

# 查看系统日志
tail -f storage/logs/laravel.log
```

### 数据备份
```bash
# 数据库备份
mysqldump -u username -p database_name > backup_$(date +%Y%m%d_%H%M%S).sql

# 文件备份
tar -czf files_backup_$(date +%Y%m%d_%H%M%S).tar.gz storage/app/public/
```

## 监控和备份

### 日志监控
- 应用日志：`storage/logs/laravel.log`
- 队列日志：`storage/logs/worker.log`
- 错误日志：`storage/logs/error.log`

### 性能监控
- 使用 Laravel Telescope（开发环境）
- 监控内存使用情况
- 监控队列处理时间
- 监控文件上传速度

## 故障排除

### 常见问题

1. **文件上传失败**
   - 检查 PHP 配置（upload_max_filesize, post_max_size）
   - 检查存储目录权限
   - 查看错误日志

2. **队列任务失败**
   - 检查队列工作进程是否运行
   - 查看失败任务详情
   - 检查数据库连接

3. **内存不足**
   - 增加 PHP memory_limit
   - 优化批量处理大小
   - 监控内存使用

4. **数据库性能问题**
   - 检查索引是否正确创建
   - 优化查询语句
   - 考虑分表策略

## 更新升级

### 代码更新
```bash
git pull origin main
composer install --optimize-autoloader --no-dev
php artisan migrate
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 数据库迁移
```bash
php artisan migrate --force
```

## 技术支持

如有问题，请查看：
1. Laravel 官方文档
2. Filament 官方文档
3. 系统日志文件
4. 联系技术支持

## 许可证

本项目采用 MIT 许可证。
