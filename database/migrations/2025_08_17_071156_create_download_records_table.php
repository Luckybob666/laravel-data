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
        Schema::create('download_records', function (Blueprint $table) {
            $table->id();
            $table->string('filename')->comment('文件名');
            $table->string('file_path')->comment('文件路径');
            $table->string('download_url')->comment('下载链接');
            $table->integer('record_count')->default(0)->comment('记录数量');
            $table->string('format')->default('xlsx')->comment('文件格式');
            $table->unsignedBigInteger('upload_record_id')->nullable()->comment('关联的上传记录ID');
            $table->unsignedBigInteger('user_id')->comment('用户ID');
            $table->json('filters')->nullable()->comment('导出时的过滤条件');
            $table->enum('status', ['processing', 'completed', 'failed'])->default('processing')->comment('状态');
            $table->text('error_message')->nullable()->comment('错误信息');
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['upload_record_id']);
            $table->index(['status']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('download_records');
    }
};
