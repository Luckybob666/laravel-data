<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('data_records', function (Blueprint $table) {
            // 添加复合索引，优化按上传记录查询的性能
            $table->index(['upload_record_id', 'created_at'], 'idx_upload_created');
            
            // 添加复合索引，优化按手机号查询的性能
            $table->index(['phone', 'upload_record_id'], 'idx_phone_upload');
            
            // 添加创建时间索引，优化时间范围查询
            $table->index('created_at', 'idx_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('data_records', function (Blueprint $table) {
            $table->dropIndex('idx_upload_created');
            $table->dropIndex('idx_phone_upload');
            $table->dropIndex('idx_created_at');
        });
    }
};
