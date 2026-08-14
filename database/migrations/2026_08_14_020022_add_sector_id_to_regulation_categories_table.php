<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regulation_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('regulation_categories', 'sector_id')) {
                $table->foreignId('sector_id')->nullable()->default(null)->after('id')->constrained('sectors')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('regulation_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sector_id');
        });
    }
};
