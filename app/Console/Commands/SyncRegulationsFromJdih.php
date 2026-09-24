<?php

namespace App\Console\Commands;

use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Sinkronisasi regulasi dari database scraper JDIH ke Lawco.
 *
 * Membaca (read-only) `jdih.regulations` lewat koneksi DB "jdih", menyalin
 * setiap PDF yang benar-benar ada ke storage public Lawco
 * (storage/app/public/regulations/{checksum}.pdf), lalu membuat record
 * Regulation via Eloquent (observer/parser Lawco tetap berlaku).
 *
 * Idempotent: tabel `jdih_sync_log` mencatat (jdih_source, jdih_document_id)
 * yang sudah dibuat; dokumen yang sama tidak pernah dibuat dua kali.
 *
 * Pemetaan (bukan asumsi — dari data nyata):
 *   jdih.title                -> lawco.regulations.title
 *   jdih.number               -> lawco.regulations.regulation_number
 *   jdih.year                 -> lawco.regulations.year
 *   jdih.date                 -> lawco.regulations.effective_date (bila cocok Y-m-d)
 *   jdih.category             -> lawco.regulation_categories.name (firstOrCreate)
 *   jdih.regulation_type      -> lawco.regulation_types.name (firstOrCreate)
 *   checksum/jdih_document_id -> idempotensi + nama file PDF di storage
 */
class SyncRegulationsFromJdih extends Command
{
    protected $signature = 'jdih:sync
        {--limit=0 : batas jumlah dokumen yang diproses (0 = semua)}
        {--source= : hanya source tertentu (mis. upload, jdih_komdigi, import)}
        {--dry-run : tampilkan rencana tanpa menulis apa pun}';

    protected $description = 'Sinkronkan regulasi dari database scraper JDIH (koneksi "jdih") ke Lawco (PDF + record)';

    /** Pemetaan slug regulation_type scraper -> nama regulation_types Lawco. */
    private const TYPE_MAP = [
        'undang_undang' => 'Undang-Undang',
        'peraturan_pemerintah' => 'Peraturan Pemerintah',
        'peraturan_presiden' => 'Peraturan Presiden',
        'peraturan' => 'Peraturan',
        'peraturan_menteri' => 'Peraturan Menteri',
        'peraturan_dirjen' => 'Peraturan Direktur Jenderal',
        'keputusan' => 'Keputusan',
        'keputusan_menteri' => 'Keputusan Menteri',
        'keputusan_dirjen' => 'Keputusan Direktur Jenderal',
        'instruksi_menteri' => 'Instruksi Menteri',
        'surat_edaran' => 'Surat Edaran',
        'pedoman' => 'Pedoman',
    ];

    /** Level (skala hierarki Lawco) untuk jenis yang belum ada di seeder. */
    private const TYPE_LEVEL = [
        'Undang-Undang' => 1,
        'Peraturan Pemerintah' => 2,
        'Peraturan Presiden' => 2,
        'Peraturan Menteri' => 3,
        'Peraturan Direktur Jenderal' => 3,
        'Peraturan' => 3,
        'Keputusan Menteri' => 4,
        'Keputusan Direktur Jenderal' => 4,
        'Instruksi Menteri' => 4,
        'Keputusan' => 4,
        'Surat Edaran' => 5,
        'Pedoman' => 5,
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));
        $source = (string) $this->option('source');

        try {
            $jdih = DB::connection('jdih');
            $jdih->getPdo();
        } catch (\Throwable $e) {
            $this->error('Koneksi ke database scraper (koneksi "jdih") gagal: '.$e->getMessage());

            return 1;
        }

        $root = rtrim((string) env('JDIH_SCRAPER_ROOT', 'D:\\coding\\proyect_of_work\\scraping-web'), '\\/');

        $query = $jdih->table('regulations')
            ->select('source', 'document_id', 'category', 'year', 'title', 'number', 'date',
                'regulation_type', 'checksum', 'local_path', 'status', 'bytes')
            ->whereIn('status', ['uploaded', 'imported', 'skipped'])
            ->orderBy('first_seen_at');

        if ($source !== '') {
            $query->where('source', $source);
        }

        $rows = $query->get();
        if ($limit > 0) {
            $rows = $rows->take($limit);
        }

        $this->info(sprintf(
            'Sumber jdih: %d baris diproses%s',
            $rows->count(),
            $dry ? ' (DRY-RUN, tidak ada yang ditulis)' : '',
        ));

        $counts = ['imported' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($rows as $row) {
            // 1) File PDF harus benar-benar ada di disk scraper.
            $src = $this->resolveSourceFile($row, $root);
            if ($src === null) {
                $counts['skipped']++;
                $this->warn(sprintf(
                    '  [skip:file_hilang] %s/%s :: %s',
                    $row->source,
                    $row->document_id,
                    $this->short($row->title),
                ));
                continue;
            }

            // 2) Sudah pernah disinkronkan? (idempotent)
            $already = DB::table('jdih_sync_log')
                ->where('jdih_source', $row->source)
                ->where('jdih_document_id', $row->document_id)
                ->first();
            if ($already !== null) {
                $counts['skipped']++;
                $this->line(sprintf(
                    '  [skip:already_synced] %s/%s -> #%d',
                    $row->source,
                    $row->document_id,
                    $already->lawco_regulation_id,
                ));
                continue;
            }

            // 3) Jenis regulasi (canonical Lawco).
            $typeName = $this->resolveTypeName((string) $row->regulation_type, (string) $row->title);
            if ($typeName === null) {
                $counts['failed']++;
                $this->error(sprintf(
                    '  [fail:type_unknown] %s/%s regulation_type=%s :: %s',
                    $row->source,
                    $row->document_id,
                    var_export($row->regulation_type, true),
                    $this->short($row->title),
                ));
                continue;
            }

            $checksum = strtolower(trim((string) $row->checksum));
            if ($checksum === '') {
                $checksum = md5($row->source.':'.$row->document_id);
            }
            $rel = 'regulations/'.$checksum.'.pdf';

            $title = trim((string) $row->title);
            if ($title === '') {
                $title = pathinfo($src, PATHINFO_FILENAME);
            }

            if ($dry) {
                $this->line(sprintf(
                    '  [plan] %s/%s -> %s | %s | %s',
                    $row->source,
                    $row->document_id,
                    $rel,
                    $typeName,
                    $this->short($title),
                ));
                continue;
            }

            try {
                $regulation = DB::transaction(function () use ($row, $src, $typeName, $checksum, $rel, $title) {
                    // Salin PDF ke storage public Lawco bila belum ada.
                    if (! Storage::disk('public')->exists($rel)) {
                        $stream = @fopen($src, 'rb');
                        if ($stream === false) {
                            throw new \RuntimeException('Gagal membuka file sumber: '.$src);
                        }
                        try {
                            Storage::disk('public')->writeStream($rel, $stream);
                        } finally {
                            fclose($stream);
                        }
                    }

                    // Master: tidak hardcode ID, selalu cari berdasarkan nama.
                    $type = RegulationType::firstOrCreate(
                        ['name' => $typeName],
                        ['level' => self::TYPE_LEVEL[$typeName] ?? 4],
                    );

                    $categoryName = trim((string) $row->category);
                    $category = $categoryName === '' ? null : RegulationCategory::firstOrCreate(
                        ['name' => $categoryName],
                    );

                    $number = $this->resolveNumber((string) $row->number, $title);
                    $year = $this->resolveYear($row->year, $title, (string) $row->date);
                    $effectiveDate = $this->parseDate((string) $row->date);

                    $regulation = Regulation::create([
                        'regulation_number' => $number,
                        'title' => $title,
                        'regulation_type_id' => $type->id,
                        'category_id' => $category?->id,
                        'year' => $year,
                        'effective_date' => $effectiveDate,
                        'file_path' => $rel,
                        // parse_status dibiarkan default 'not_parsed' — parsing
                        // tetap lewat alur Lawco sendiri (tombol Parse regulasi).
                    ]);

                    DB::table('jdih_sync_log')->insert([
                        'jdih_source' => $row->source,
                        'jdih_document_id' => $row->document_id,
                        'jdih_checksum' => $checksum,
                        'lawco_regulation_id' => $regulation->id,
                        'file_path' => $rel,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return $regulation;
                });

                $counts['imported']++;
                $this->line(sprintf(
                    '  [imported] #%d %s | %s | %s | %s',
                    $regulation->id,
                    $rel,
                    $this->short($title),
                    $typeName,
                    $categoryName = trim((string) $row->category),
                ));
            } catch (\Throwable $e) {
                $counts['failed']++;
                $this->error(sprintf('  [fail] %s/%s : %s', $row->source, $row->document_id, $e->getMessage()));
            }
        }

        $this->info(sprintf(
            'SELESAI | imported=%d skipped=%d failed=%d',
            $counts['imported'],
            $counts['skipped'],
            $counts['failed'],
        ));

        return 0;
    }

    /** Resolusi path file scraper (relatif terhadap JDIH_SCRAPER_ROOT). */
    private function resolveSourceFile(object $row, string $root): ?string
    {
        $raw = trim((string) ($row->local_path ?? ''));
        if ($raw === '') {
            return null;
        }
        $absolute = preg_match('/^[A-Za-z]:[\\\\\/]/', $raw) === 1
            || str_starts_with($raw, '\\\\');
        $path = $absolute
            ? $raw
            : rtrim($root, '\\/').'\\'.str_replace('/', '\\', ltrim($raw, '\\/'));

        return is_file($path) ? $path : null;
    }

    /** Slug scraper -> nama jenis Lawco; fallback deteksi dari judul. */
    private function resolveTypeName(string $slug, string $title): ?string
    {
        $slug = strtolower(trim($slug));
        if ($slug !== '' && isset(self::TYPE_MAP[$slug])) {
            return self::TYPE_MAP[$slug];
        }

        return self::deriveTypeFromTitle($title);
    }

    private static function deriveTypeFromTitle(string $title): ?string
    {
        $patterns = [
            '/undang-undang/i' => 'Undang-Undang',
            '/peraturan pemerintah/i' => 'Peraturan Pemerintah',
            '/peraturan presiden/i' => 'Peraturan Presiden',
            '/peraturan menteri/i' => 'Peraturan Menteri',
            '/peraturan direktur jenderal/i' => 'Peraturan Direktur Jenderal',
            '/peraturan dirjen/i' => 'Peraturan Direktur Jenderal',
            '/keputusan direktur jenderal/i' => 'Keputusan Direktur Jenderal',
            '/keputusan dirjen/i' => 'Keputusan Direktur Jenderal',
            '/keputusan menteri/i' => 'Keputusan Menteri',
            '/instruksi menteri/i' => 'Instruksi Menteri',
            '/surat edaran/i' => 'Surat Edaran',
            '/pedoman/i' => 'Pedoman',
        ];
        foreach ($patterns as $pattern => $name) {
            if (preg_match($pattern, $title) === 1) {
                return $name;
            }
        }

        return null;
    }

    private function resolveNumber(string $number, string $title): string
    {
        $number = trim($number);
        if ($number !== '') {
            return mb_substr($number, 0, 255);
        }
        if (preg_match('/nomor\s+([0-9a-z.\-\/]+)/i', $title, $m) === 1) {
            return mb_substr($m[1], 0, 255);
        }

        return '';
    }

    private function resolveYear(int|string $year, string $title, string $date): int
    {
        $year = (int) $year;
        if ($year > 0) {
            return $year;
        }
        if (preg_match('/(19|20)\d{2}/', $date.' '.$title, $m) === 1) {
            return (int) $m[0];
        }

        return (int) date('Y');
    }

    private function parseDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'Y/m/d'] as $format) {
            $d = \DateTime::createFromFormat($format, $value);
            if ($d !== false) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }

    private function short(?string $value): string
    {
        $value = trim((string) $value);
        if (mb_strlen($value) <= 60) {
            return $value === '' ? '-' : $value;
        }

        return mb_substr($value, 0, 57).'...';
    }
}