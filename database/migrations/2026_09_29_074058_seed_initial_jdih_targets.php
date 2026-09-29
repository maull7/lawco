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
        $legacySectorMap = (array) config('database.connections.jdih.sector_by_source', []);
        $targets = [
            ['name' => 'JDIH Komdigi', 'source' => 'jdih_komdigi', 'is_active' => true],
            ['name' => 'JDIH Kemenhub', 'source' => 'jdih_kemenhub', 'is_active' => true],
            ['name' => 'Import Manual', 'source' => 'import', 'is_active' => false],
        ];

        foreach ($targets as $target) {
            $configuredSectorId = (int) ($legacySectorMap[$target['source']] ?? 0);
            $sectorId = $configuredSectorId > 0
                && DB::table('sectors')->where('id', $configuredSectorId)->whereNull('deleted_at')->exists()
                    ? $configuredSectorId
                    : null;

            DB::table('jdih_targets')->updateOrInsert(
                ['source' => $target['source']],
                [
                    ...$target,
                    'target_url' => null,
                    'sector_id' => $sectorId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('jdih_targets')->whereIn('source', ['jdih_komdigi', 'jdih_kemenhub', 'import'])->delete();
    }
};
