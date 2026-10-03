<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $categorySector = DB::table('regulation_categories')
            ->select('sector_id')
            ->whereColumn('regulation_categories.id', 'regulations.category_id')
            ->whereNotNull('sector_id');

        DB::table('regulations')
            ->whereNull('sector_id')
            ->whereExists((clone $categorySector)->selectRaw('1'))
            ->update(['sector_id' => $categorySector]);

        $targetSector = DB::table('jdih_sync_log')
            ->join('jdih_targets', 'jdih_targets.source', '=', 'jdih_sync_log.jdih_source')
            ->select('jdih_targets.sector_id')
            ->whereColumn('jdih_sync_log.lawco_regulation_id', 'regulations.id')
            ->whereNotNull('jdih_targets.sector_id')
            ->orderBy('jdih_sync_log.id')
            ->limit(1);

        DB::table('regulations')
            ->whereNull('sector_id')
            ->whereExists((clone $targetSector)->selectRaw('1'))
            ->update(['sector_id' => $targetSector]);
    }

    /** Sector values are preserved until the sector column is rolled back. */
    public function down(): void {}
};
