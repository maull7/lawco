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
        Schema::table('jdih_sync_log', function (Blueprint $table): void {
            $table->dropUnique('jdih_sync_log_checksum_unique');
            $table->index('jdih_checksum', 'jdih_sync_log_checksum_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jdih_sync_log', function (Blueprint $table): void {
            $table->unique('jdih_checksum', 'jdih_sync_log_checksum_unique');
            $table->dropIndex('jdih_sync_log_checksum_index');
        });
    }
};
