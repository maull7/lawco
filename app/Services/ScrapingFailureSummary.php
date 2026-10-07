<?php

namespace App\Services;

use App\Jobs\SyncJdihRegulations;
use Throwable;

class ScrapingFailureSummary
{
    /** @return array{source: ?string, document_ids: ?array, total: ?int, failed: ?int, imported: ?int, message: string} */
    public function describe(object $failure): array
    {
        $payload = json_decode($failure->payload, true);
        $source = null;
        $documentIds = null;
        if (is_array($payload) && ($payload['data']['commandName'] ?? null) === SyncJdihRegulations::class
            && is_string($payload['data']['command'] ?? null)) {
            try {
                $job = @unserialize($payload['data']['command'], ['allowed_classes' => [SyncJdihRegulations::class]]);
                if ($job instanceof SyncJdihRegulations) {
                    $source = $job->source;
                    $documentIds = $job->documentIds;
                }
            } catch (Throwable) {
                $documentIds = null;
            }
        }
        $name = is_array($payload) ? ($payload['displayName'] ?? '') : '';
        if ($source === null && is_string($name) && preg_match('/JDIH sync \(([^,]+),/', $name, $matches)) {
            $source = $matches[1] === 'all' ? null : $matches[1];
        }

        $failed = $this->countFrom($failure->exception, '/failed=(\d+)/i');
        $imported = $this->countFrom($failure->exception, '/Imported\s*:\s*(\d+)/i');
        $already = $this->countFrom($failure->exception, '/Already synced\s*:\s*(\d+)/i');

        return [
            'source' => $source,
            'document_ids' => $documentIds,
            'total' => $documentIds !== null ? count(array_unique($documentIds)) : $this->countFrom($failure->exception, '/Total source\s*:\s*(\d+)/i'),
            'failed' => $failed ?? $this->countFrom($failure->exception, '/Failed\s*:\s*(\d+)/i'),
            'imported' => $imported !== null ? $imported + ($already ?? 0) : null,
            'message' => $this->humanError($failure->exception),
        ];
    }

    public function humanError(string $exception): string
    {
        $error = mb_strtolower($exception);

        return match (true) {
            str_contains($error, 'pdf sumber tidak ditemukan'), str_contains($error, 'file_hilang'), str_contains($error, 'no such file') => 'File PDF sumber belum tersedia. File perlu diunduh kembali dari website sumber sebelum dicoba lagi.',
            str_contains($error, 'connection refused'), str_contains($error, 'koneksi'), str_contains($error, 'getaddrinfo'), str_contains($error, 'could not connect') => 'Koneksi ke database atau layanan antrean terputus. Periksa layanan tersebut, lalu coba lagi.',
            str_contains($error, 'timeout'), str_contains($error, 'timed out'), str_contains($error, 'too long') => 'Proses melebihi batas waktu. Sebagian dokumen mungkin sudah masuk; retry akan melanjutkan dokumen yang tersisa.',
            str_contains($error, 'permission denied'), str_contains($error, 'menyalin'), str_contains($error, 'verifikasi pdf'), str_contains($error, 'no space'), str_contains($error, 'membuka file') => 'File PDF tidak dapat disimpan atau diverifikasi. Periksa izin folder, ruang penyimpanan, dan kondisi file sumber.',
            str_contains($error, 'duplicate'), str_contains($error, 'constraint'), str_contains($error, 'sqlstate') => 'Data dokumen tidak dapat disimpan ke database. Periksa data dan pengaturan sektor sebelum mencoba lagi.',
            default => 'Sinkronisasi berhenti sebelum selesai. Dokumen yang sudah masuk tetap tersimpan; lihat detail teknis untuk penyebabnya lalu coba lagi.',
        };
    }

    /** @return list<string> */
    public function searchTerms(string $keyword): array
    {
        $groups = [
            ['pdf sumber tidak ditemukan', 'file_hilang', 'no such file'],
            ['connection refused', 'koneksi', 'getaddrinfo', 'could not connect'],
            ['timeout', 'timed out', 'too long'],
            ['permission denied', 'menyalin', 'verifikasi pdf', 'no space', 'membuka file'],
            ['duplicate', 'constraint', 'sqlstate'],
        ];
        $terms = [];
        foreach ($groups as $group) {
            if (str_contains(mb_strtolower($this->humanError($group[0])), mb_strtolower($keyword))) {
                $terms = array_merge($terms, $group);
            }
        }

        return $terms;
    }

    private function countFrom(string $exception, string $pattern): ?int
    {
        return preg_match($pattern, $exception, $matches) ? (int) $matches[1] : null;
    }
}
