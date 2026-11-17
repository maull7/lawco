<?php

use App\Models\DownloadedLegalDecision;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Unduh ulang yang terjadi sebelum penanda "Diunduh ulang" tercatat di semua jalur: dari log aktivitas,
 * putusan yang punya log `downloaded` setelah log `download_failed` ditandai diunduh ulang oleh pengguna log `downloaded` terakhir.
 */
return new class extends Migration
{
    public function up(): void
    {
        $hasFailed = [];
        $redownloaderBySubject = [];

        DB::table('user_activity_logs')
            ->where('subject_type', DownloadedLegalDecision::class)
            ->whereIn('action', ['download_failed', 'downloaded'])
            ->whereNotNull('subject_id')
            ->orderBy('id')
            ->select(['id', 'subject_id', 'action', 'user_id'])
            ->lazyById(2000)
            ->each(function (object $log) use (&$hasFailed, &$redownloaderBySubject): void {
                if ($log->action === 'download_failed') {
                    $hasFailed[$log->subject_id] = true;
                } elseif (isset($hasFailed[$log->subject_id])) {
                    $redownloaderBySubject[$log->subject_id] = (int) $log->user_id;
                }
            });

        if ($redownloaderBySubject === []) {
            return;
        }

        $userNames = User::query()->pluck('name', 'id');

        foreach (array_chunk(array_keys($redownloaderBySubject), 500) as $subjectIds) {
            DownloadedLegalDecision::query()
                ->whereIn('id', $subjectIds)
                ->whereNull('metadata->redownloaded_by_user_id')
                ->get()
                ->each(function (DownloadedLegalDecision $record) use ($redownloaderBySubject, $userNames): void {
                    $userId = $redownloaderBySubject[$record->id];

                    // updated_at tidak diubah agar periode rekap tetap sesuai waktu unduhan.
                    $record->metadata = [
                        ...($record->metadata ?? []),
                        'redownloaded_by_user_id' => $userId,
                        'redownloaded_by_name' => $userNames[$userId] ?? null,
                    ];
                    $record->timestamps = false;
                    $record->saveQuietly();
                });
        }
    }

    public function down(): void
    {
        // Backfill data: tidak dikembalikan (penanda unduh ulang tetap valid).
    }
};
