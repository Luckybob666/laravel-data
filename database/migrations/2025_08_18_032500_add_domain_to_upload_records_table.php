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
        Schema::table('upload_records', function (Blueprint $table) {
            $table->string('domain')->nullable()->after('remarks')->comment('域名');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('upload_records', function (Blueprint $table) {
            $table->dropColumn('domain');
        });
    }
};
