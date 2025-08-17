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
        Schema::create('upload_records', function (Blueprint $table) {
            $table->id();
            $table->string('filename')->comment('文件名');
            $table->string('original_filename')->comment('原始文件名');
            $table->string('country')->nullable()->comment('国家');
            $table->string('industry')->nullable()->comment('行业');
            $table->text('remarks')->nullable()->comment('备注');
            $table->integer('total_count')->default(0)->comment('总条数');
            $table->integer('success_count')->default(0)->comment('成功条数');
            $table->integer('duplicate_count')->default(0)->comment('重复条数');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending')->comment('处理状态');
            $table->text('error_message')->nullable()->comment('错误信息');
            $table->string('file_path')->nullable()->comment('文件存储路径');
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('上传用户');
            $table->timestamps();
            
            $table->index('status');
            $table->index('user_id');
            $table->index('country');
            $table->index('industry');
        });
        
        // 为data_records表添加外键约束
        Schema::table('data_records', function (Blueprint $table) {
            $table->foreign('upload_record_id')->references('id')->on('upload_records')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upload_records');
    }
};
