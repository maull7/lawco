<?php

namespace App\Http\Controllers;

use App\Helpers\DocumentStorage;
use App\Models\UserActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\Process\PhpExecutableFinder;

/** Admin buttons to run `documents:migrate-to-r2` in the background and tail its log. */
class StorageR2Controller extends Controller
{
    private const FLAGS = [
        'stats' => '--stats',
        'check' => '--check',
        'migrate' => '',
        'prune-preview' => '--prune --dry-run',
        'prune' => '--prune',
    ];

    public function index(): View
    {
        return view('storage-r2.index', [
            'pdfDisk' => DocumentStorage::diskName(),
            'r2Disk' => DocumentStorage::r2DiskName(),
            'dailyLimitGb' => config('filesystems.r2_daily_copy_limit_gb'),
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        $action = (string) $request->input('action');
        if (! array_key_exists($action, self::FLAGS)) {
            return response()->json(['message' => 'Aksi tidak dikenal.'], 422);
        }

        if ($action === 'prune' && ! DocumentStorage::usesR2()) {
            return response()->json(['message' => 'Ditolak: PDF_DISK masih "'.DocumentStorage::diskName().'". Set PDF_DISK='.DocumentStorage::r2DiskName().' dulu.'], 422);
        }
        if ($this->isRunning()) {
            return response()->json(['message' => 'Proses lain masih berjalan.'], 409);
        }
        if (! function_exists('exec')) {
            return response()->json(['message' => 'Fungsi exec() dimatikan di server. Jalankan manual: php artisan documents:migrate-to-r2 '.self::FLAGS[$action]], 500);
        }

        $pid = (int) exec(sprintf(
            'nohup %s %s documents:migrate-to-r2 %s > %s 2>&1 & echo $!',
            escapeshellarg((new PhpExecutableFinder)->find(false) ?: 'php'),
            escapeshellarg(base_path('artisan')),
            self::FLAGS[$action],
            escapeshellarg($this->logFile()),
        ));
        file_put_contents($this->pidFile(), $pid);

        UserActivityLog::log('storage_r2', null, null, "Menjalankan documents:migrate-to-r2 ({$action})");

        return response()->json(['message' => "Dimulai ({$action}).", 'pid' => $pid]);
    }

    public function log(): JsonResponse
    {
        $log = @file_get_contents($this->logFile()) ?: '';

        return response()->json([
            'running' => $this->isRunning(),
            'log' => mb_substr($log, -200000),
        ]);
    }

    private function logFile(): string
    {
        return storage_path('logs/r2-migrate.log');
    }

    private function pidFile(): string
    {
        return storage_path('app/r2-migrate.pid');
    }

    private function isRunning(): bool
    {
        $pid = (int) @file_get_contents($this->pidFile());

        return $pid > 0 && file_exists("/proc/{$pid}");
    }
}
