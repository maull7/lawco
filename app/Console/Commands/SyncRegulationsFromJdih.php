<?php

namespace App\Console\Commands;

use App\Jobs\SyncJdihRegulations;
use App\Models\JdihTarget;
use App\Models\Regulation;
use App\Models\RegulationCategory;
use App\Models\RegulationType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sinkronisasi regulasi dari database scraper JDIH ke Lawco.
 *
 * Membaca (read-only) `jdih.regulations` lewat koneksi DB "jdih", menyalin
 * setiap PDF yang benar-benar ada ke storage public Lawco
 * (storage/app/public/regulations/{checksum}.pdf), lalu membuat record
 * Regulation via Eloquent (observer/parser Lawco tetap berlaku).
 *
 * Idempotent: tabel `jdih_sync_log` mencatat (jdih_source, jdih_document_id)
 * yang sudah dibuat; dokumen yang sama tidak pernah dibuat dua kali. Konten
 * identik antar source (checksum sama) juga aman: hanya satu record dibuat.
 *
 * Pemetaan (bukan asumsi — dari data nyata):
 *   jdih.title                -> lawco.regulations.title
 *   jdih.number               -> lawco.regulations.regulation_number
 *   jdih.year                 -> lawco.regulations.year
 *   jdih.date                 -> lawco.regulations.effective_date (bila cocok Y-m-d)
 *   jdih.source                -> lawco.regulations.sector_id via jdih_targets (bukan kolom
 *                                jdih.sector_id yang masih dummy)
 *   jdih.category             -> lawco.regulation_categories.name (firstOrCreate)
 *   jdih.regulation_type      -> lawco.regulation_types.name (firstOrCreate)
 *   checksum/jdih_document_id -> idempotensi + nama file PDF di storage
 */
class SyncRegulationsFromJdih extends Command
{
    protected $signature = 'jdih:sync
        {--limit=0 : batas dokumen belum tersinkron dengan PDF tersedia (0 = semua)}
        {--source= : hanya source tertentu (mis. upload, jdih_komdigi, jdih_kemenhub, import)}
        {--dry-run : tampilkan rencana tanpa menulis apa pun}
        {--only-pending : hanya proses dokumen yang belum tercatat tersinkron}
        {--document=* : hanya document_id tertentu pada source yang dipilih}
        {--from-folder : ambil kandidat dari PDF yang masih tersedia di folder scraper}
        {--queue : masukkan sinkronisasi ke queue Horizon}';

    protected $description = 'Sinkronkan regulasi dari database scraper JDIH (koneksi "jdih") ke Lawco (PDF + record)';

    /** Cache sektor per source, supaya tabel `sectors` tidak di-query per dokumen. */
    private array $sectorIds = [];

    /** Pemetaan slug regulation_type scraper -> nama regulation_types Lawco. */
    private const TYPE_MAP = [
        'undang_undang' => 'Undang-Undang',
        'peraturan_pemerintah' => 'Peraturan Pemerintah',
        'peraturan_presiden' => 'Peraturan Presiden',
        'keputusan_presiden' => 'Keputusan Presiden',
        'perppu' => 'Peraturan Pemerintah Pengganti Undang-Undang',
        'peraturan_sekretaris_jenderal' => 'Peraturan Sekretaris Jenderal',
        'keputusan_sekretaris_jenderal' => 'Keputusan Sekretaris Jenderal',
        'peraturan_inspektur_jenderal' => 'Peraturan Inspektur Jenderal',
        'keputusan_inspektur_jenderal' => 'Keputusan Inspektur Jenderal',
        'instruksi_dirjen' => 'Instruksi Direktur Jenderal',
        'keputusan_eselon_i' => 'Keputusan Eselon I',
        'peraturan_badan' => 'Peraturan Badan',
        'keputusan_badan' => 'Keputusan Badan',
        'peraturan_direksi' => 'Peraturan Direksi',
        'keputusan_direksi' => 'Keputusan Direksi',
        'statuten' => 'Statuten',
        'peraturan' => 'Peraturan',
        'peraturan_menteri' => 'Peraturan Menteri',
        'peraturan_dirjen' => 'Peraturan Direktur Jenderal',
        'keputusan' => 'Keputusan',
        'keputusan_menteri' => 'Keputusan Menteri',
        'keputusan_dirjen' => 'Keputusan Direktur Jenderal',
        'instruksi_menteri' => 'Instruksi Menteri',
        'instruksi_presiden' => 'Instruksi Presiden',
        'peraturan_bersama' => 'Peraturan Bersama',
        'keputusan_bersama' => 'Keputusan Bersama',
        'penelitian_hukum' => 'Kajian atau Penelitian Hukum',
        'surat_edaran' => 'Surat Edaran',
        'pedoman' => 'Pedoman',
        'mou' => 'Nota Kesepahaman',
        'peraturan_kebijakan' => 'Peraturan Kebijakan',
        'terjemahan' => 'Terjemahan',
        'needs_review' => null,
    ];

    /** Level (skala hierarki Lawco) untuk jenis yang belum ada di seeder. */
    private const TYPE_LEVEL = [
        'Undang-Undang' => 1,
        'Peraturan Pemerintah' => 2,
        'Peraturan Presiden' => 2,
        'Keputusan Presiden' => 2,
        'Peraturan Menteri' => 3,
        'Peraturan Direktur Jenderal' => 3,
        'Peraturan Kepala Badan' => 3,
        'Peraturan Inspektur Jenderal' => 3,
        'Peraturan Sekretaris Jenderal' => 3,
        'Peraturan' => 3,
        'Keputusan Menteri' => 4,
        'Keputusan Direktur Jenderal' => 4,
        'Keputusan Kepala Badan' => 4,
        'Keputusan Inspektur Jenderal' => 4,
        'Keputusan Sekretaris Jenderal' => 4,
        'Instruksi Menteri' => 4,
        'Instruksi Presiden' => 2,
        'Peraturan Bersama' => 3,
        'Keputusan Bersama' => 4,
        'Peraturan Senat' => 3,
        'Undang-Undang Dasar' => 1,
        'Peraturan Pemerintah Pengganti Undang-Undang' => 1,
        'Peraturan Badan' => 3,
        'Keputusan Badan' => 4,
        'Peraturan Direksi' => 3,
        'Keputusan Direksi' => 4,
        'Keputusan Eselon I' => 4,
        'Statuten' => 5,
        'Kajian atau Penelitian Hukum' => 5,
        'Instruksi Direktur Jenderal' => 4,
        'Keputusan' => 4,
        'Surat Edaran' => 5,
        'Pedoman' => 5,
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));
        $source = (string) $this->option('source');

        if ($this->option('queue') && $dry) {
            $this->error('--queue tidak dapat dipakai bersama --dry-run. Jalankan dry-run langsung.');

            return self::FAILURE;
        }
        if ($this->option('document') !== [] && $source === '') {
            $this->error('--document membutuhkan --source.');

            return self::FAILURE;
        }

        try {
            $jdih = DB::connection('jdih');
            $jdih->getPdo();
        } catch (\Throwable $e) {
            $this->error('Koneksi ke database scraper (koneksi "jdih") gagal: '.$e->getMessage());
            Log::channel('single')->error('Koneksi database scraper gagal.', [
                'source' => $source !== '' ? $source : 'all',
                'document_ids' => $this->option('document'),
                'exception' => $e,
            ]);

            return 1;
        }

        $root = rtrim((string) config('database.connections.jdih.scraper_root', ''), '\\/');
        if ($root === '') {
            $this->warn('JDIH_SCRAPER_ROOT kosong — hanya PDF dengan local_path absolut yang ketemu.');
        }

        $folderPaths = null;
        if ($this->option('from-folder')) {
            if ($root === '' || ! is_dir($root) || ! is_readable($root)) {
                $this->error('Folder scraper tidak tersedia atau tidak dapat dibaca.');

                return self::FAILURE;
            }
            $folderPaths = [];
            foreach (File::allFiles($root) as $file) {
                if (strtolower($file->getExtension()) !== 'pdf') {
                    continue;
                }
                $folderPaths[] = $file->getPathname();
                $folderPaths[] = substr($file->getPathname(), strlen($root) + 1);
            }
            $folderPaths = array_values(array_unique($folderPaths));
            $this->line(sprintf('  PDF di folder sumber: %d', count($folderPaths) / 2));
        }

        // `subcategory` belum ada di semua versi DB scraper: pilih hanya bila ada.
        $select = ['source', 'document_id', 'category', 'year', 'title', 'number', 'date',
            'regulation_type', 'checksum', 'local_path', 'status', 'bytes'];
        if (Schema::connection('jdih')->hasColumn('regulations', 'subcategory')) {
            $select[] = 'subcategory';
        }

        $query = $jdih->table('regulations')->select($select);
        if ($folderPaths !== null) {
            $query->whereIn('local_path', $folderPaths);
        } else {
            // Scraped documents use `downloaded`; uploaded/imported are the
            // manual paths. Include all completed states accepted by scraper.
            $query->whereIn('status', ['uploaded', 'imported', 'skipped', 'downloaded']);
        }
        $query->orderBy('first_seen_at');

        if ($source !== '') {
            $query->where('source', $source);
        }
        if ($this->option('document') !== []) {
            $query->whereIn('document_id', $this->option('document'));
        }

        $query->orderBy('source')->orderBy('document_id');

        $allRows = $query->get();
        $totalSource = $allRows->count();
        if ($folderPaths !== null) {
            $matchedPaths = $jdih->table('regulations')->whereIn('local_path', $folderPaths)->distinct()->count('local_path');
            $unmatched = max(0, (int) (count($folderPaths) / 2) - $matchedPaths);
            if ($unmatched > 0) {
                $this->warn(sprintf('  [review:metadata_missing] %d PDF di folder belum memiliki metadata dokumen; file tetap disimpan.', $unmatched));
                Log::channel('single')->warning('PDF di folder scraper belum memiliki metadata dokumen.', ['unmatched_files' => $unmatched]);
            }
        }

        $syncedQuery = DB::table('jdih_sync_log')
            ->whereIn('jdih_source', $allRows->pluck('source')->unique());
        if ($this->option('document') !== []) {
            $syncedQuery->whereIn('jdih_document_id', $this->option('document'));
        }
        $syncedDocuments = $syncedQuery->get(['jdih_source', 'jdih_document_id', 'lawco_regulation_id', 'file_path'])
            ->keyBy(static fn (object $log): string => $log->jdih_source.':'.$log->jdih_document_id);
        if ($this->option('queue')) {
            $pendingRows = $allRows->filter(fn (object $row): bool => ! $syncedDocuments->has($row->source.':'.$row->document_id)
                && $this->resolveSourceFile($row, $root) !== null);
            if ($limit > 0) {
                $pendingRows = $pendingRows->take($limit);
            }
            $batchSize = max(1, (int) config('database.connections.jdih.sync_batch_size', 25));
            $batchCount = 0;
            foreach ($pendingRows->groupBy('source') as $batchSource => $sourceRows) {
                foreach ($sourceRows->chunk($batchSize) as $batchRows) {
                    $documentIds = $batchRows->pluck('document_id')->map(static fn (mixed $id): string => (string) $id)->values()->all();
                    SyncJdihRegulations::dispatch((string) $batchSource, 0, $documentIds, (bool) $this->option('from-folder'));
                    $batchCount++;
                }
            }
            $this->info(sprintf('Sinkronisasi dimasukkan ke queue Horizon: %d dokumen dalam %d batch (maksimal %d dokumen/batch).', $pendingRows->count(), $batchCount, $batchSize));
            Log::channel('single')->info('JDIH sync batches planned.', [
                'source' => $source !== '' ? $source : 'all',
                'total_source' => $totalSource,
                'pending_documents' => $pendingRows->count(),
                'batch_count' => $batchCount,
                'batch_size' => $batchSize,
            ]);

            return self::SUCCESS;
        }

        // Klasifikasi ketersediaan PDF atas SELURUH kandidat (bukan hanya yang diproses).
        $available = 0;
        $missing = 0;
        foreach ($allRows as $row) {
            if ($this->resolveSourceFile($row, $root) !== null) {
                $available++;
            } elseif (! $syncedDocuments->has($row->source.':'.$row->document_id)) {
                $missing++;
            }
        }
        $rows = $allRows;
        $processed = 0;

        $this->info(sprintf(
            'Sumber jdih: %d baris diperiksa (total %d)%s',
            $rows->count(),
            $totalSource,
            $dry ? ' — DRY-RUN, tidak ada yang ditulis' : '',
        ));
        $this->line(sprintf('  Available PDF : %d', $available));
        $this->line(sprintf('  Missing PDF   : %d', $missing));

        $counts = ['imported' => 0, 'already_synced' => 0, 'failed' => 0, 'needs_review' => 0];
        $missingFailures = 0;
        $alreadySyncedWithPdf = 0;

        foreach ($rows as $row) {
            // 1) Sudah pernah disinkronkan? (idempotent) — dicek SEBELUM cek file,
            //    supaya baris yang sudah di-cut ("file hilang setelah sync") tetap
            //    tercatat already_synced, bukan Missing PDF. Sekalian dipotong file-nya.
            $already = $syncedDocuments->get($row->source.':'.$row->document_id);
            if ($already !== null) {
                $counts['already_synced']++;
                $this->line(sprintf(
                    '  [skip:already_synced] %s/%s -> #%d',
                    $row->source,
                    $row->document_id,
                    $already->lawco_regulation_id,
                ));
                $sourceFile = $this->resolveSourceFile($row, $root);
                if ($sourceFile !== null) {
                    $alreadySyncedWithPdf++;
                }
                if (! $this->option('only-pending')) {
                    $this->cutIfSynced($sourceFile, (string) $already->file_path, $root, $dry);
                }

                continue;
            }

            // 2) File PDF harus benar-benar ada di disk scraper.
            $src = $this->resolveSourceFile($row, $root);
            if ($src === null) {
                if ($this->option('document') !== []) {
                    $counts['failed']++;
                    $missingFailures++;
                    $this->error(sprintf('  [fail:file_hilang] %s/%s : PDF sumber tidak ditemukan; dokumen belum masuk Lawco.', $row->source, $row->document_id));
                }
                Log::channel('single')->warning('PDF sumber tidak ditemukan.', [
                    'source' => $row->source,
                    'document_id' => $row->document_id,
                    'local_path' => $row->local_path,
                    'title' => $row->title,
                ]);
                $this->warn(sprintf(
                    '  [skip:file_hilang] %s/%s :: %s',
                    $row->source,
                    $row->document_id,
                    $this->short($row->title),
                ));

                continue;
            }

            if ($limit > 0 && $processed >= $limit) {
                continue;
            }
            $processed++;

            // 3) Jenis regulasi (canonical Lawco).
            $typeName = $this->resolveTypeName((string) $row->regulation_type, (string) $row->title);
            if ($typeName === null) {
                $counts['needs_review']++;
                Log::channel('single')->warning('Jenis dokumen JDIH perlu review; PDF sumber dipertahankan.', [
                    'source' => $row->source,
                    'document_id' => $row->document_id,
                    'regulation_type' => $row->regulation_type,
                    'title' => $row->title,
                ]);
                $this->warn(sprintf(
                    '  [review:type_unknown] %s/%s regulation_type=%s :: %s',
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

            // 4) Konten identik sudah pernah dibuat (checksum sama dari source/doc lain)?
            //    Unique(jdih_checksum) di jdih_sync_log; jangan sampai melanggar / membuat duplikat PDF.
            $sameContent = DB::table('jdih_sync_log')
                ->where('jdih_checksum', $checksum)
                ->where(function ($q) use ($row) {
                    $q->where('jdih_source', '!=', $row->source)
                        ->orWhere('jdih_document_id', '!=', $row->document_id);
                })
                ->first();
            if ($sameContent !== null) {
                $counts['already_synced']++;
                $alreadySyncedWithPdf++;
                $this->line(sprintf(
                    '  [skip:konten_identik] %s/%s -> #%d (download content sama, PDF sudah ada)',
                    $row->source,
                    $row->document_id,
                    $sameContent->lawco_regulation_id,
                ));
                $this->cutIfSynced($src, (string) $sameContent->file_path, $root, $dry);

                continue;
            }

            $title = $this->cleanTitle((string) $row->title, $src);

            // Sektor ditentukan per target website di database Lawco, bukan dari
            // kolom sector_id payload scraper yang masih dummy.
            $sectorId = $this->resolveSectorId((string) $row->source);

            // Kategori & subkategori: MATCH dulu ke master Lawco (by nama).
            // Tidak ada kecocokan -> null (tidak auto-create). Subkategori
            // diambil dari kolom opsional `subcategory` scraper (bila kosong -> null).
            $categoryId = $this->resolveCategoryId($sectorId, (string) $row->category);
            $subId = $this->resolveSubcategoryId($categoryId, trim((string) ($row->subcategory ?? '')));

            if ($categoryId === null && trim((string) $row->category) !== '') {
                Log::channel('single')->warning('Kategori tidak cocok dengan kategori Lawco pada sektor sumber.', [
                    'source' => $row->source,
                    'document_id' => $row->document_id,
                    'category' => $row->category,
                    'sector_id' => $sectorId,
                ]);
                $this->warn(sprintf(
                    '  [kategori:tidak-ditemukan] kategori "%s" tidak ditemukan untuk sektor #%d; category_id dibiarkan kosong',
                    trim((string) $row->category),
                    $sectorId,
                ));
            }

            if ($dry) {
                $this->line(sprintf(
                    '  [plan] %s/%s -> %s | %s | sector=%d | kategori=%s sub=%s | %s',
                    $row->source,
                    $row->document_id,
                    $rel,
                    $typeName,
                    $sectorId,
                    $categoryId ?? '-',
                    $subId ?? '-',
                    $this->short($title),
                ));

                continue;
            }

            try {
                $regulation = DB::transaction(function () use ($row, $src, $typeName, $checksum, $rel, $title, $sectorId, $categoryId, $subId) {
                    $this->copySourceFile($src, $rel);

                    // Master: tidak hardcode ID, selalu cari berdasarkan nama.
                    $type = RegulationType::firstOrCreate(
                        ['name' => $typeName],
                        ['level' => self::TYPE_LEVEL[$typeName] ?? 4],
                    );

                    $number = $this->resolveNumber((string) $row->number, $title);
                    $year = $this->resolveYear($row->year, $title, (string) $row->date);
                    $effectiveDate = $this->parseDate((string) $row->date);

                    $regulation = Regulation::create([
                        'regulation_number' => $number,
                        'title' => $title,
                        'regulation_type_id' => $type->id,
                        'sector_id' => $sectorId,
                        'category_id' => $categoryId,
                        'year' => $year,
                        'effective_date' => $effectiveDate,
                        'file_path' => $rel,
                        // parse_status dibiarkan default 'not_parsed' — parsing
                        // tetap lewat alur Lawco sendiri (tombol Parse regulasi).
                    ]);

                    // Subkategori (bila punya kecocokan di master Lawco).
                    // Duplikat tak mungkin: satu create per (source, document_id) via jdih_sync_log.
                    if ($subId !== null) {
                        DB::table('regulation_sub_category')->insert([
                            'regulation_id' => $regulation->id,
                            'sub_category_id' => $subId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

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
                    '  [imported] #%d %s | %s | %s | %s | sector=%d | kategori=%s sub=%s',
                    $regulation->id,
                    $rel,
                    $this->short($title),
                    $typeName,
                    trim((string) $row->category),
                    $sectorId,
                    $categoryId ?? '-',
                    $subId ?? '-',
                ));
                // Cut salinan scraper setelah tercatat sukses di Lawco.
                $this->cutIfSynced($src, $rel, $root, $dry);
            } catch (\Throwable $e) {
                $counts['failed']++;
                Log::channel('single')->error('Gagal menyinkronkan regulasi.', [
                    'source' => $row->source,
                    'document_id' => $row->document_id,
                    'title' => $row->title,
                    'source_path' => $src,
                    'destination_path' => $rel,
                    'checksum' => $checksum,
                    'sector_id' => $sectorId,
                    'category_id' => $categoryId,
                    'exception' => $e,
                ]);
                $this->error(sprintf(
                    '  [fail] %s/%s : %s',
                    $row->source,
                    $row->document_id,
                    $e->getMessage(),
                ));
            }
        }

        // Pending = dokumen yang file-nya TERSEDIA tapi belum tercatat di Lawco
        // (bukan bagian dari run ini karena --limit/dry-run). Review dihitung terpisah.
        $pending = max(0, $available - $counts['imported'] - $alreadySyncedWithPdf - ($counts['failed'] - $missingFailures) - $counts['needs_review']);

        $this->info('--- RINGKASAN ---');
        $this->line(sprintf('  Total source   : %d', $totalSource));
        $this->line(sprintf('  Available PDF  : %d', $available));
        $this->line(sprintf('  Missing PDF    : %d', $missing));
        $this->line(sprintf('  Imported       : %d', $counts['imported']));
        $this->line(sprintf('  Already synced : %d', $counts['already_synced']));
        $this->line(sprintf('  Failed         : %d', $counts['failed']));
        $this->line(sprintf('  Needs review   : %d', $counts['needs_review']));
        $this->line(sprintf('  Pending        : %d', $pending));

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Sektor per source dari target website Lawco.
     * Source tak ter-map / ID 0 -> default_sector_id. ID yang tidak ada di
     * `sectors` -> default_sector_id + warning, bukan diam-diam fallback.
     * Hasil per source di-cache: satu query `sectors` per source, bukan per dokumen.
     */
    private function resolveSectorId(string $source): int
    {
        if (isset($this->sectorIds[$source])) {
            return $this->sectorIds[$source];
        }

        $fallback = (int) config('database.connections.jdih.default_sector_id', 1);
        $target = JdihTarget::query()->with('sector')->where('source', $source)->first();
        $id = (int) ($target?->sector_id ?? 0);

        if ($id <= 0) {
            Log::channel('single')->warning('Mapping sektor source tidak tersedia di database; memakai sektor default.', [
                'source' => $source,
                'default_sector_id' => $fallback,
            ]);
            $this->warn(sprintf(
                '  [sektor:default] source "%s" belum memiliki pemetaan sektor; memakai JDIH_DEFAULT_SECTOR_ID #%d',
                $source,
                $fallback,
            ));

            return $this->sectorIds[$source] = $fallback;
        }

        if ($target?->sector === null) {
            Log::channel('single')->warning('ID sektor source tidak ditemukan; memakai sektor default.', [
                'source' => $source,
                'configured_sector_id' => $id,
                'default_sector_id' => $fallback,
            ]);
            $this->warn(sprintf(
                '  [sektor] source "%s": sektor #%d tidak ada di tabel sectors -> pakai default #%d',
                $source,
                $id,
                $fallback,
            ));

            return $this->sectorIds[$source] = $fallback;
        }

        return $this->sectorIds[$source] = $id;
    }

    /**
     * Cari kategori Lawco by nama (case/trim insensitive) untuk sektor tertentu.
     * Kategori harus milik sektor source agar tidak memakai sektor lain.
     * Return null bila tidak ada — kategori TIDAK dibuat otomatis.
     */
    private function resolveCategoryId(int $sectorId, string $categoryName): ?int
    {
        $categoryName = trim($categoryName);
        if ($categoryName === '') {
            return null;
        }
        $categoryId = RegulationCategory::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($categoryName)])
            ->where('sector_id', $sectorId)
            ->value('id');

        return $categoryId !== null ? (int) $categoryId : null;
    }

    /** Cari subkategori Lawco by (category_id, name); null bila tidak ada/ tidak cocok. */
    private function resolveSubcategoryId(?int $categoryId, string $subName): ?int
    {
        $subName = trim($subName);
        if ($categoryId === null || $subName === '') {
            return null;
        }
        $id = DB::table('sub_categories')
            ->where('category_id', $categoryId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($subName)])
            ->whereNull('deleted_at')
            ->pluck('id')
            ->first();

        return $id !== null ? (int) $id : null;
    }

    /**
     * Cut (hapus) salinan PDF di folder scraper, HANYA bila salinan Lawco
     * benar-benar identik. Nonaktifkan via env JDIH_CUT_SOURCE_FILES=false.
     * Dry-run tidak pernah menghapus apa pun.
     */
    private function cutIfSynced(?string $src, string $rel, string $root, bool $dry): void
    {
        if ($dry || ! config('database.connections.jdih.cut_source_files', true)) {
            return;
        }
        if ($src === null || ! is_file($src)) {
            return;
        }
        if (! $this->filesMatch($src, $rel)) {
            Log::channel('single')->warning('PDF sumber dipertahankan karena salinan Lawco tidak cocok.', [
                'source_path' => $src,
                'lawco_path' => $rel,
            ]);
            $this->warn(sprintf('  [cut:dilewati] salinan Lawco tidak cocok: %s', $rel));

            return;
        }
        if (@unlink($src)) {
            $this->line(sprintf(
                '  [cut] %s (salinan Lawco aman: %s)',
                basename($src),
                $rel,
            ));
            $this->pruneEmptyDirs(dirname($src), $root);
        } else {
            Log::channel('single')->warning('Gagal menghapus PDF sumber setelah sync.', [
                'source_path' => $src,
                'lawco_path' => $rel,
            ]);
            $this->warn(sprintf('  [cut:gagal] tidak bisa menghapus %s', $src));
        }
    }

    /** Copy to a temporary path, verify bytes, then move into the final path. */
    private function copySourceFile(string $src, string $rel): void
    {
        if ($this->filesMatch($src, $rel)) {
            return;
        }

        $temporary = 'regulations/.tmp/'.Str::uuid().'.part';
        $stream = @fopen($src, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Gagal membuka file sumber: '.$src);
        }

        try {
            try {
                if (! Storage::disk('public')->writeStream($temporary, $stream)) {
                    throw new \RuntimeException('Gagal menyalin PDF ke file sementara: '.$temporary);
                }
            } finally {
                fclose($stream);
            }

            if (! $this->filesMatch($src, $temporary)) {
                throw new \RuntimeException('Verifikasi PDF sementara gagal: '.$temporary);
            }

            if (! Storage::disk('public')->move($temporary, $rel)) {
                throw new \RuntimeException('Gagal memindahkan PDF ke path final: '.$rel);
            }

            if (! $this->filesMatch($src, $rel)) {
                throw new \RuntimeException('Verifikasi PDF final gagal: '.$rel);
            }
        } finally {
            Storage::disk('public')->delete($temporary);
        }
    }

    private function filesMatch(string $src, string $rel): bool
    {
        $destination = Storage::disk('public')->path($rel);
        if (! is_file($src) || ! is_file($destination) || filesize($src) !== filesize($destination)) {
            return false;
        }

        $sourceHash = @hash_file('sha256', $src);
        $destinationHash = @hash_file('sha256', $destination);

        return is_string($sourceHash)
            && is_string($destinationHash)
            && hash_equals($sourceHash, $destinationHash);
    }

    /** Hapus folder induk yang kosong ke atas, berhenti di akar scraper. */
    private function pruneEmptyDirs(string $dir, string $root): void
    {
        $dir = rtrim($dir, '/\\');
        $root = rtrim($root, '/\\');
        while (strlen($dir) > strlen($root) && str_starts_with($dir, $root)) {
            if (! @rmdir($dir)) {
                break;
            }
            $dir = dirname($dir);
        }
    }

    /** Resolusi path file scraper (relatif terhadap JDIH_SCRAPER_ROOT). */
    private function resolveSourceFile(object $row, string $root): ?string
    {
        $raw = trim((string) ($row->local_path ?? ''));
        if ($raw === '') {
            return null;
        }
        $absolute = preg_match('/^[A-Za-z]:[\\\\\/]/', $raw) === 1
            || str_starts_with($raw, '\\\\')
            || str_starts_with($raw, '/');
        $path = $absolute
            ? $raw
            : rtrim($root, '/\\').DIRECTORY_SEPARATOR.trim($raw, '/\\');

        return is_file($path) ? $path : null;
    }

    /** Bersihkan judul: buang suffix token unik hasil scraper (bukan bagian judul). */
    private function cleanTitle(string $title, string $src): string
    {
        $title = trim($title);
        if ($title === '') {
            $title = pathinfo($src, PATHINFO_FILENAME);
        }
        // Pola suffix token scraper: " - <base64url panjang>" di akhir nama file.
        $title = preg_replace('/\s+-\s+[A-Za-z0-9~_-]{20,}$/', '', $title);
        $title = trim((string) $title);

        return mb_substr($title, 0, 500);
    }

    /** Slug scraper -> nama jenis Lawco; fallback deteksi dari judul. */
    private function resolveTypeName(string $slug, string $title): ?string
    {
        $slug = Str::slug(str_replace("\u{00AD}", '', $slug), '_');
        if ($slug !== '' && isset(self::TYPE_MAP[$slug])) {
            return self::TYPE_MAP[$slug];
        }

        if ($slug === 'juklak_juknis') {
            return self::deriveTypeFromTitle($title) ?? 'Pedoman';
        }

        return self::deriveTypeFromTitle($title);
    }

    private static function deriveTypeFromTitle(string $title): ?string
    {
        $title = str_replace("\u{00AD}", '', $title);
        $patterns = [
            '/^\s*undang[\s-]*undang dasar/i' => 'Undang-Undang Dasar',
            '/^\s*instruksi presiden/i' => 'Instruksi Presiden',
            '/^\s*peraturan bersama/i' => 'Peraturan Bersama',
            '/^\s*keputusan bersama/i' => 'Keputusan Bersama',
            '/^\s*peraturan senat/i' => 'Peraturan Senat',
            '/^\s*peraturan pemerintah pengganti undang[\s-]*undang/i' => 'Peraturan Pemerintah Pengganti Undang-Undang',
            '/^\s*surat keputusan menteri\b/i' => 'Keputusan Menteri',
            '/^\s*surat keputusan\b/i' => 'Keputusan',
            '/^\s*undang[\s-]*undang\b/i' => 'Undang-Undang',
            '/^\s*peraturan pemerintah/i' => 'Peraturan Pemerintah',
            '/^\s*peraturan presiden/i' => 'Peraturan Presiden',
            '/^\s*keputusan presiden/i' => 'Keputusan Presiden',
            '/^\s*peraturan kepala badan/i' => 'Peraturan Kepala Badan',
            '/^\s*keputusan kepala badan/i' => 'Keputusan Kepala Badan',
            '/^\s*peraturan inspektur jenderal/i' => 'Peraturan Inspektur Jenderal',
            '/^\s*keputusan inspektur jenderal/i' => 'Keputusan Inspektur Jenderal',
            '/^\s*peraturan sekretaris jenderal/i' => 'Peraturan Sekretaris Jenderal',
            '/^\s*keputusan sekretaris jenderal/i' => 'Keputusan Sekretaris Jenderal',
            '/^\s*peraturan direktur jenderal/i' => 'Peraturan Direktur Jenderal',
            '/^\s*peraturan dirjen/i' => 'Peraturan Direktur Jenderal',
            '/^\s*keputusan direktur jenderal/i' => 'Keputusan Direktur Jenderal',
            '/^\s*keputusan dirjen/i' => 'Keputusan Direktur Jenderal',
            '/^\s*peraturan menteri/i' => 'Peraturan Menteri',
            '/^\s*keputusan menteri/i' => 'Keputusan Menteri',
            '/^\s*instruksi direktur jenderal/i' => 'Instruksi Direktur Jenderal',
            '/^\s*instruksi menteri/i' => 'Instruksi Menteri',
            '/^\s*surat edaran/i' => 'Surat Edaran',
            '/^\s*pedoman/i' => 'Pedoman',
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
        // "Nomor KM 112 Tahun 2024", "Nomor KP-SKJ 13 Tahun 2026", "Nomor 66 Tahun 2024"
        if (preg_match('/nomor\s+([0-9A-Za-z.\-\/]+(?:\s+[0-9]+)?)/i', $title, $m) === 1) {
            return mb_substr(trim($m[1]), 0, 255);
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
        $value = preg_replace('/\s+-\s+[A-Za-z0-9~_-]{20,}$/', '', $value) ?? $value;
        if (mb_strlen($value) <= 60) {
            return $value === '' ? '-' : $value;
        }

        return mb_substr($value, 0, 57).'...';
    }
}
