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
        Schema::create('data_records', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique()->comment('手机号码');
            $table->json('data')->nullable()->comment('其他列数据JSON格式');
            $table->unsignedBigInteger('upload_record_id')->nullable()->comment('上传记录ID');
            $table->timestamps();
            
            $table->index('phone');
            $table->index('upload_record_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_records');
    }
};
