<?php

use App\Models\DownloadedLegalDecision;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Putusan yang sedang dalam antrian dan punya riwayat gagal (legal_decision_download_failures) sebelum fitur "Diunduh ulang" ada:
 * tandai sebagai diunduh ulang oleh pengguna yang mengantrikannya (user_id record) agar masuk filter & rekap Diunduh Ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        $failedHashes = DB::table('legal_decision_download_failures')->pluck('url_hash')->flip();
        $userNames = User::query()->pluck('name', 'id');

        DownloadedLegalDecision::query()
            ->whereNotNull('metadata->queued_at')
            ->whereNull('metadata->redownloaded_by_user_id')
            ->whereNotNull('user_id')
            ->chunkById(500, function ($records) use ($failedHashes, $userNames): void {
                foreach ($records as $record) {
                    if (! $failedHashes->has(md5((string) $record->source_url))) {
                        continue;
                    }

                    // updated_at tidak diubah agar periode rekap tetap sesuai waktu antrian.
                    $record->metadata = [
                        ...($record->metadata ?? []),
                        'redownloaded_by_user_id' => (int) $record->user_id,
                        'redownloaded_by_name' => $userNames[$record->user_id] ?? null,
                    ];
                    $record->timestamps = false;
                    $record->saveQuietly();
                }
            });
    }

    public function down(): void
    {
        // Backfill data: tidak dikembalikan (penanda unduh ulang tetap valid).
    }
};
