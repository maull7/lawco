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
        Schema::table('regulations', function (Blueprint $table) {
            $table->index(['year', 'tanggal_diundangkan', 'id'], 'regulations_list_sort_index');
            $table->index(['parse_status', 'deleted_at'], 'regulations_parse_status_index');
            $table->index('created_at', 'regulations_created_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('regulations', function (Blueprint $table) {
            $table->dropIndex('regulations_list_sort_index');
            $table->dropIndex('regulations_parse_status_index');
            $table->dropIndex('regulations_created_at_index');
        });
    }
};
