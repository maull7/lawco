<?php

namespace App\Http\Controllers;

use App\Jobs\DownloadLegalDecisionJob;
use App\Models\DownloadedLegalDecision;
use App\Models\LegalDecision;
use App\Services\AiClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\HeaderUtils;

class LegalDecisionController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->input('search', ''));
        $year = $request->input('year');
        $page = max(1, (int) $request->input('page', 1));

        $kategori = trim($request->input('kategori', ''));
        $peradilan = trim($request->input('peradilan', ''));
        $periode = trim($request->input('periode', 'putus'));

        // Cache key per query, filter, dan page (disimpan 10 menit agar super cepat & aman)
        $cacheKey = 'ma_live_'.md5("{$kategori}_{$peradilan}_{$periode}_{$year}_{$page}");

        $scraperError = null;
        $liveData = Cache::remember($cacheKey, 600, function () use ($kategori, $peradilan, $periode, $year, $page, &$scraperError) {
            if (! config('services.ma_scraper.url')) {
                $scraperError = 'API scraper MA belum dikonfigurasi (MA_SCRAPER_URL).';

                return null;
            }

            try {
                $response = $this->scraperRequest()->get('/putusan', array_filter([
                    'page' => $page,
                    'kategori' => $kategori,
                    'peradilan' => $peradilan,
                    'periode' => $periode,
                    'tahun' => (int) $year,
                ]));
            } catch (ConnectionException $e) {
                $scraperError = 'API scraper MA tidak dapat dihubungi: '.$e->getMessage();
                Log::channel('single')->error('Live Scraper API unreachable: '.$e->getMessage());

                return null;
            }

            $json = $response->json();
            if (! empty($json['success']) && ! empty($json['items'])) {
                return $json;
            }

            $scraperError = $json['error'] ?? (! empty($json['success']) ? 'Portal MA tidak mengembalikan data untuk filter ini.' : 'Respons API scraper tidak valid (HTTP '.$response->status().').');
            Log::channel('single')->error('Live Scraper API failed: HTTP '.$response->status().' '.substr($response->body(), 0, 400));

            return null;
        });

        // Jika live fetch berhasil, gunakan data real-time langsung dari MA
        if ($liveData && ! empty($liveData['items'])) {
            $downloadedUrls = DownloadedLegalDecision::query()
                ->whereIn('source_url', collect($liveData['items'])->pluck('url')->filter())
                ->pluck('source_url')
                ->flip();

            $items = collect($liveData['items'])->map(function ($it) use ($downloadedUrls) {
                return (object) [
                    'decision_number' => $it['judul'] ?? 'Putusan MA',
                    'title' => $it['judul'] ?? '',
                    'court_name' => $it['pengadilan'] ?? 'Mahkamah Agung',
                    'classification' => $it['klasifikasi'] ?? 'Putusan',
                    'year' => $it['tahun'] ?? null,
                    'tgl_putus' => $it['tgl_putus'] ?? null,
                    'tgl_register' => $it['tgl_register'] ?? null,
                    'tgl_upload' => $it['tgl_upload'] ?? null,
                    'summary' => $it['ringkasan'] ?? '',
                    'source_url' => $it['url'] ?? null,
                    'file_url' => null,
                    'is_downloaded' => isset($it['url']) && $downloadedUrls->has($it['url']),
                ];
            });

            $currentPage = $liveData['pagination']['current_page'] ?? $page;
            $maxPage = $liveData['pagination']['max_page'] ?? 499;
            // Total mengikuti filter aktif (dihitung scraper dari sidebar portal MA); perkiraan dari jumlah halaman jika tidak tersedia.
            $totalCount = $liveData['total_count'] ?? $maxPage * 20;

            // Portal MA mendukung navigasi halaman hingga max_page (499).
            // Batasi total navigasi paginator agar slider tidak membuat puluhan ribu halaman fiktif.
            $paginatedTotal = min($totalCount, $maxPage * 20);

            $decisions = new LengthAwarePaginator(
                $items,
                $paginatedTotal,
                20,
                $currentPage,
                ['path' => $request->url(), 'query' => $request->query()]
            );

            $decisions->onEachSide(1);

            return view('legal-decisions.index', [
                'filters' => [
                    'search' => $search,
                    'year' => $year,
                    'kategori' => $kategori,
                    'peradilan' => $peradilan,
                    'periode' => $periode,
                ],
                'decisions' => $decisions,
                'real_total_count' => $totalCount,
                'sidebar_filters' => $liveData['sidebar_filters'] ?? [],
                'is_live' => true,
            ]);
        }

        // Fallback jika portal MA offline: baca dari database lokal
        $query = LegalDecision::query();

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('decision_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%");
            });
        }

        if ($year) {
            $query->where('year', $year);
        }

        $decisions = $query->orderByDesc('id')->paginate(15)->withQueryString();

        return view('legal-decisions.index', [
            'filters' => [
                'search' => $search,
                'year' => $year,
                'court' => null,
                'classification' => null,
            ],
            'decisions' => $decisions,
            'courts' => LegalDecision::query()->whereNotNull('court_name')->distinct()->pluck('court_name'),
            'classifications' => LegalDecision::query()->whereNotNull('classification')->distinct()->pluck('classification'),
            'years' => collect(range(2026, 2015)),
            'is_live' => false,
            'scraper_error' => $scraperError,
        ]);
    }

    /**
     * Putusan MA yang sudah diunduh (database internal), memakai tampilan yang sama dengan halaman live.
     */
    public function downloads(Request $request): View
    {
        $search = trim($request->input('search', ''));
        $kategori = trim($request->input('kategori', ''));
        $peradilan = trim($request->input('peradilan', ''));
        $year = $request->input('year');
        $periode = trim($request->input('periode', 'putus'));

        // Sumber data download: Murni dari tabel DownloadedLegalDecision (hanya yang sudah diunduh oleh user)
        $query = DownloadedLegalDecision::query()
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('decision_number', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhere('summary', 'like', "%{$search}%")))
            ->when($kategori !== '', fn (Builder $q) => $q->where('classification', $kategori))
            ->when($peradilan !== '', fn (Builder $q) => $q->where('court_name', $peradilan))
            ->when(! empty($year), fn (Builder $q) => $q->where('year', $year));

        $decisions = $query
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // 1. Sidebar Direktori (Klasifikasi) dari putusan yang sudah diunduh
        $classifications = DownloadedLegalDecision::query()
            ->whereNotNull('classification')
            ->where('classification', '!=', '')
            ->selectRaw('classification, count(*) as total')
            ->groupBy('classification')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row): array => [
                'label' => $row->classification,
                'kategori' => $row->classification,
                'count' => number_format((int) $row->total, 0, ',', '.'),
            ]);

        // 2. Sidebar Peradilan (Lembaga / Pengadilan) dari putusan yang sudah diunduh
        $courts = DownloadedLegalDecision::query()
            ->whereNotNull('court_name')
            ->where('court_name', '!=', '')
            ->selectRaw('court_name, count(*) as total')
            ->groupBy('court_name')
            ->orderByDesc('total')
            ->limit(15)
            ->get()
            ->map(fn ($row): array => [
                'label' => $row->court_name,
                'peradilan' => $row->court_name,
                'count' => number_format((int) $row->total, 0, ',', '.'),
            ]);

        // 3. Sidebar Tabel Tahun (Putus, Register, Upload) dari putusan yang sudah diunduh
        $yearRecords = DownloadedLegalDecision::query()
            ->whereNotNull('year')
            ->where('year', '!=', '')
            ->selectRaw('year, count(*) as total')
            ->groupBy('year')
            ->orderByDesc('year')
            ->get();

        $columns = [
            [
                'name' => 'Putus',
                'periode' => 'putus',
                'items' => $yearRecords->map(fn ($row): array => [
                    'label' => (string) $row->year,
                    'count' => number_format((int) $row->total, 0, ',', '.'),
                    'tahun' => (int) $row->year,
                    'periode' => 'putus',
                ])->values()->all(),
            ],
            [
                'name' => 'Register',
                'periode' => 'regis',
                'items' => $yearRecords->map(fn ($row): array => [
                    'label' => (string) $row->year,
                    'count' => number_format((int) $row->total, 0, ',', '.'),
                    'tahun' => (int) $row->year,
                    'periode' => 'regis',
                ])->values()->all(),
            ],
            [
                'name' => 'Upload',
                'periode' => 'upload',
                'items' => $yearRecords->map(fn ($row): array => [
                    'label' => (string) $row->year,
                    'count' => number_format((int) $row->total, 0, ',', '.'),
                    'tahun' => (int) $row->year,
                    'periode' => 'upload',
                ])->values()->all(),
            ],
        ];

        $sidebarFilters = [];

        // Kartu Direktori
        if ($classifications->isNotEmpty()) {
            $totalClass = DownloadedLegalDecision::query()->count();
            $sidebarFilters[] = [
                'title' => 'Direktori',
                'items' => [
                    ['label' => 'Semua Direktori', 'kategori' => '', 'count' => number_format($totalClass, 0, ',', '.')],
                    ...$classifications,
                ],
            ];
        }

        // Kartu Peradilan
        if ($courts->isNotEmpty()) {
            $totalCourts = DownloadedLegalDecision::query()->count();
            $sidebarFilters[] = [
                'title' => 'Peradilan',
                'items' => [
                    ['label' => 'Semua Peradilan', 'peradilan' => '', 'count' => number_format($totalCourts, 0, ',', '.')],
                    ...$courts,
                ],
            ];
        }

        // Kartu Tabel Tahun
        if ($yearRecords->isNotEmpty()) {
            $sidebarFilters[] = [
                'title' => 'Tahun',
                'is_table' => true,
                'columns' => $columns,
            ];
        }

        return view('legal-decisions.index', [
            'filters' => [
                'search' => $search,
                'kategori' => $kategori,
                'peradilan' => $peradilan,
                'year' => $year,
                'periode' => $periode,
            ],
            'decisions' => $decisions,
            'real_total_count' => $decisions->total(),
            'sidebar_filters' => $sidebarFilters,
            'is_live' => false,
            'listRoute' => 'legal-decisions.downloads',
            'pageTitle' => 'Putusan MA (Download)',
            'pageDescription' => 'Putusan Mahkamah Agung yang sudah diunduh dan tersimpan di database internal.',
            'emptyMessage' => 'Putusan akan muncul di sini setelah diunduh dari menu Putusan MA (langsung).',
        ]);
    }

    /**
     * Unduh satu putusan langsung (dipanggil secara sekuensial oleh antrian frontend tanpa butuh queue worker).
     */
    public function downloadSingle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url'],
            'decision_number' => ['nullable', 'string', 'max:255'],
            'court_name' => ['nullable', 'string', 'max:255'],
            'classification' => ['nullable', 'string', 'max:255'],
            'year' => ['nullable'],
            'summary' => ['nullable', 'string'],
            'tgl_register' => ['nullable', 'string', 'max:50'],
            'tgl_putus' => ['nullable', 'string', 'max:50'],
            'tgl_upload' => ['nullable', 'string', 'max:50'],
        ]);

        $url = trim($validated['url']);
        $hash = md5($url);
        $customTitle = trim((string) ($validated['decision_number'] ?? ''));
        $safeName = $customTitle !== '' ? preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $customTitle) : 'Putusan_MA_'.$hash;
        $safeName = trim(preg_replace('/\s+/', '_', $safeName)).'.pdf';

        $courtName = trim((string) ($validated['court_name'] ?? 'Mahkamah Agung')) ?: 'Mahkamah Agung';
        $classification = trim((string) ($validated['classification'] ?? ''));
        $year = trim((string) ($validated['year'] ?? ''));
        $summary = trim((string) ($validated['summary'] ?? ''));
        $tglRegister = trim((string) ($validated['tgl_register'] ?? ''));
        $tglPutus = trim((string) ($validated['tgl_putus'] ?? ''));
        $tglUpload = trim((string) ($validated['tgl_upload'] ?? ''));

        $existing = DownloadedLegalDecision::query()->where('source_url', $url)->first();
        if ($existing) {
            return response()->json([
                'success' => true,
                'already_exists' => true,
                'message' => 'Putusan sudah ada di database.',
                'decision_number' => $existing->decision_number,
            ]);
        }

        // Ambil PDF dari backend scraper jika ada
        $content = null;
        if (config('services.ma_scraper.url')) {
            try {
                $response = $this->scraperRequest()->timeout(90)->get('/pdf', ['url' => $url]);
                if ($response->successful() && strlen($response->body()) > 500) {
                    $content = $response->body();
                }
            } catch (\Throwable $e) {
                Log::channel('single')->warning('[downloadSingle] Gagal mengambil PDF via scraper: '.$e->getMessage());
            }
        }

        $r2Path = $content !== null ? $this->uploadToR2($content, 'putusan_ma/'.$hash.'.pdf') : null;

        try {
            $record = DownloadedLegalDecision::create([
                'user_id' => auth()->id(),
                'decision_number' => $customTitle ?: $safeName,
                'title' => $customTitle ?: $safeName,
                'court_name' => $courtName,
                'classification' => $classification ?: null,
                'year' => $year ?: null,
                'tgl_register' => $tglRegister ?: null,
                'tgl_putus' => $tglPutus ?: null,
                'tgl_upload' => $tglUpload ?: null,
                'summary' => $summary ?: null,
                'source_url' => $url,
                'file_name' => $safeName,
                'file_path' => 'putusan_ma/'.$hash.'.pdf',
                'file_size' => $content !== null ? strlen($content) : null,
                'mime_type' => 'application/pdf',
                'download_type' => 'manual',
                'metadata' => [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'r2_path' => $r2Path,
                ],
            ]);

            return response()->json([
                'success' => true,
                'already_exists' => false,
                'message' => 'Berhasil diunduh dan disimpan ke database.',
                'decision_number' => $record->decision_number,
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->error('[downloadSingle] Gagal menyimpan record: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Masukkan beberapa putusan yang dipilih ke dalam antrian download (background queue).
     */
    public function queueBulkDownload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'decisions' => ['required', 'array', 'min:1', 'max:50'],
            'decisions.*.url' => ['required', 'url'],
            'decisions.*.decision_number' => ['nullable', 'string', 'max:255'],
            'decisions.*.court_name' => ['nullable', 'string', 'max:255'],
            'decisions.*.classification' => ['nullable', 'string', 'max:255'],
            'decisions.*.year' => ['nullable'],
            'decisions.*.summary' => ['nullable', 'string'],
            'decisions.*.tgl_register' => ['nullable', 'string', 'max:50'],
            'decisions.*.tgl_putus' => ['nullable', 'string', 'max:50'],
            'decisions.*.tgl_upload' => ['nullable', 'string', 'max:50'],
        ]);

        $queuedCount = 0;
        $skippedCount = 0;
        $userId = auth()->id();

        foreach ($validated['decisions'] as $item) {
            $url = trim($item['url']);
            $alreadyExists = DownloadedLegalDecision::query()->where('source_url', $url)->exists();

            if ($alreadyExists) {
                $skippedCount++;

                continue;
            }

            DownloadLegalDecisionJob::dispatch($item, $userId);
            $queuedCount++;
        }

        $message = "{$queuedCount} putusan berhasil dimasukkan ke antrian download.";
        if ($skippedCount > 0) {
            $message .= " ({$skippedCount} putusan dilewati karena sudah ada di database).";
        }

        return response()->json([
            'success' => true,
            'queued_count' => $queuedCount,
            'skipped_count' => $skippedCount,
            'message' => $message,
        ]);
    }

    /**
     * Preview / download PDF putusan. Arsip disimpan hanya di Cloudflare R2 (tanpa file lokal):
     * putusan yang sudah diarsipkan dibaca dari R2, selebihnya diambil dari backend scraper.
     */
    public function streamPdf(Request $request)
    {
        $url = trim($request->input('url', ''));
        if (empty($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            abort(404, 'URL putusan tidak valid.');
        }

        $isDownload = $request->boolean('download');
        $customTitle = trim($request->input('title', ''));
        $hash = md5($url);
        $objectPath = 'putusan_ma/'.$hash.'.pdf';

        $safeName = $customTitle !== '' ? preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $customTitle) : 'Putusan_MA_'.$hash;
        $safeName = trim(preg_replace('/\s+/', '_', $safeName)).'.pdf';

        $archived = DownloadedLegalDecision::query()->where('source_url', $url)->first();

        // Sudah ada di database: jangan diunduh ulang dari halaman live, cukup beri informasi.
        // Unduhan dari halaman arsip (archived=1) tetap dilayani.
        if ($archived && $isDownload && ! $request->boolean('archived')) {
            return back()->with('info', 'Putusan "'.($archived->decision_number ?: $customTitle).'" sudah ada di Putusan MA (download).');
        }

        if (! empty($archived?->metadata['r2_path'])) {
            try {
                $response = Storage::disk('r2')->response($objectPath, $safeName, [
                    'Content-Type' => 'application/pdf',
                ], $isDownload ? 'attachment' : 'inline');

                if ($isDownload) {
                    $this->recordDownload($request, $url, $customTitle, $safeName, null, $hash);
                }

                return $response;
            } catch (\Throwable $e) {
                Log::channel('single')->warning('R2 read error, ambil ulang dari scraper: '.$e->getMessage());
            }
        }

        $content = null;
        $output = 'API scraper MA belum dikonfigurasi (MA_SCRAPER_URL).';

        if (config('services.ma_scraper.url')) {
            try {
                $response = $this->scraperRequest()->timeout(90)->get('/pdf', ['url' => $url]);
                $output = 'HTTP '.$response->status().' '.substr($response->body(), 0, 300);

                if ($response->successful() && strlen($response->body()) > 500) {
                    $content = $response->body();
                }
            } catch (ConnectionException $e) {
                $output = $e->getMessage();
            }
        }

        if ($content === null) {
            Log::channel('single')->error('Failed to fetch PDF for decision: '.$url.' Output: '.substr($output, 0, 300));

            // Portal MA menolak permintaan server (mis. 403): arahkan unduhan langsung ke halaman putusan MA.
            if ($isDownload && str_ends_with((string) parse_url($url, PHP_URL_HOST), 'mahkamahagung.go.id')) {
                return redirect()->away($url);
            }

            abort(404, 'File PDF putusan tidak dapat diunduh dari portal Mahkamah Agung.');
        }

        if ($isDownload) {
            $this->recordDownload($request, $url, $customTitle, $safeName, $content, $hash);
        }

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($isDownload ? 'attachment' : 'inline', $safeName),
        ]);
    }

    /**
     * Klien HTTP ke backend scraper MA (ma-scraper-api/server.py), lokal atau lewat tunnel ngrok/Cloudflare.
     */
    private function scraperRequest(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.ma_scraper.url'), '/'))
            ->withHeaders([
                'X-API-Key' => (string) config('services.ma_scraper.token'),
                'ngrok-skip-browser-warning' => '1',
            ])
            ->timeout(60);
    }

    /**
     * Catat unduhan dan salin PDF ke Cloudflare R2 sekali per putusan (dilewati jika sudah pernah diunduh).
     */
    private function recordDownload(Request $request, string $url, string $customTitle, string $safeName, ?string $content, string $hash): void
    {
        $existing = DownloadedLegalDecision::query()->where('source_url', $url)->first();

        $courtName = trim((string) $request->input('court', 'Mahkamah Agung')) ?: 'Mahkamah Agung';
        $classification = trim((string) $request->input('classification', ''));
        $year = trim((string) $request->input('year', ''));
        $summary = trim((string) $request->input('summary', ''));
        $tglRegister = trim((string) $request->input('tgl_register', ''));
        $tglPutus = trim((string) $request->input('tgl_putus', ''));
        $tglUpload = trim((string) $request->input('tgl_upload', ''));

        if ($existing) {
            // Sudah tercatat: lengkapi field yang mungkin sebelumnya kosong
            $updates = [];
            if (empty($existing->classification) && $classification !== '') {
                $updates['classification'] = $classification;
            }
            if (empty($existing->court_name) || $existing->court_name === 'Mahkamah Agung') {
                if ($courtName !== 'Mahkamah Agung') {
                    $updates['court_name'] = $courtName;
                }
            }
            if (empty($existing->year) && $year !== '') {
                $updates['year'] = $year;
            }
            if (empty($existing->summary) && $summary !== '') {
                $updates['summary'] = $summary;
            }
            if (empty($existing->tgl_register) && $tglRegister !== '') {
                $updates['tgl_register'] = $tglRegister;
            }
            if (empty($existing->tgl_putus) && $tglPutus !== '') {
                $updates['tgl_putus'] = $tglPutus;
            }
            if (empty($existing->tgl_upload) && $tglUpload !== '') {
                $updates['tgl_upload'] = $tglUpload;
            }

            if (empty($existing->metadata['r2_path']) && $content !== null) {
                $updates['metadata'] = [
                    ...($existing->metadata ?? []),
                    'r2_path' => $this->uploadToR2($content, 'putusan_ma/'.$hash.'.pdf'),
                ];
            }

            if (! empty($updates)) {
                $existing->update($updates);
            }

            return;
        }

        $r2Path = $content !== null ? $this->uploadToR2($content, 'putusan_ma/'.$hash.'.pdf') : null;

        try {
            DownloadedLegalDecision::create([
                'user_id' => auth()->id(),
                'decision_number' => $customTitle ?: $safeName,
                'title' => $customTitle ?: $safeName,
                'court_name' => $courtName,
                'classification' => $classification ?: null,
                'year' => $year ?: null,
                'tgl_register' => $tglRegister ?: null,
                'tgl_putus' => $tglPutus ?: null,
                'tgl_upload' => $tglUpload ?: null,
                'summary' => $summary ?: null,
                'source_url' => $url,
                'file_name' => $safeName,
                'file_path' => 'putusan_ma/'.$hash.'.pdf',
                'file_size' => $content !== null ? strlen($content) : null,
                'mime_type' => 'application/pdf',
                'download_type' => 'manual',
                'metadata' => [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'r2_path' => $r2Path,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->warning('Failed to log downloaded decision: '.$e->getMessage());
        }
    }

    /**
     * Upload PDF ke Cloudflare R2 (disk "r2"). Mengembalikan "bucket/path" jika berhasil, null jika gagal atau belum dikonfigurasi.
     */
    private function uploadToR2(string $content, string $objectPath): ?string
    {
        $bucket = (string) config('filesystems.disks.r2.bucket');

        if ($bucket === '' || ! config('filesystems.disks.r2.endpoint') || ! config('filesystems.disks.r2.key')) {
            return null;
        }

        try {
            Storage::disk('r2')->put($objectPath, $content, ['ContentType' => 'application/pdf']);

            return "{$bucket}/{$objectPath}";
        } catch (\Throwable $e) {
            Log::channel('single')->warning('R2 upload error: '.$e->getMessage());
        }

        return null;
    }

    /**
     * Tanya AI mengenai perkara / putusan Mahkamah Agung menggunakan ChatGPT (OpenAI) dengan grounding data putusan.
     */
    public function aiChat(Request $request, AiClient $aiClient): JsonResponse
    {
        @set_time_limit(180);

        $prompt = trim((string) $request->input('message', ''));
        if ($prompt === '') {
            return response()->json([
                'success' => false,
                'message' => 'Pertanyaan tidak boleh kosong.',
            ], 422);
        }

        $contextPutusan = $request->input('context_putusan', []);
        $history = $request->input('history', []);

        $docTitle = trim((string) ($contextPutusan['number'] ?? $contextPutusan['judul'] ?? 'Putusan Mahkamah Agung'));
        $docSummary = trim((string) ($contextPutusan['summary'] ?? $contextPutusan['ringkasan'] ?? ''));
        $docCourt = trim((string) ($contextPutusan['court'] ?? $contextPutusan['pengadilan'] ?? 'Mahkamah Agung'));
        $docClass = trim((string) ($contextPutusan['classification'] ?? $contextPutusan['klasifikasi'] ?? ''));
        $docYear = trim((string) ($contextPutusan['year'] ?? $contextPutusan['tahun'] ?? ''));
        $docUrl = trim((string) ($contextPutusan['sourceUrl'] ?? $contextPutusan['url'] ?? ''));

        // Ambil ekstrak teks PDF jika putusan sudah diarsipkan di Cloudflare R2
        $pdfText = '';
        if ($docUrl !== '') {
            $pdfText = $this->extractArchivedPdfText($docUrl);
        }

        $groundingContext = "DOKUMEN PUTUSAN PERKARA YANG SEDANG DITELAAH:\n";
        $groundingContext .= "- Judul / Nomor Perkara: {$docTitle}\n";
        if ($docCourt !== '') {
            $groundingContext .= "- Lembaga / Pengadilan: {$docCourt}\n";
        }
        if ($docClass !== '') {
            $groundingContext .= "- Klasifikasi: {$docClass}\n";
        }
        if ($docYear !== '') {
            $groundingContext .= "- Tahun: {$docYear}\n";
        }
        if ($docSummary !== '') {
            $groundingContext .= "- Ringkasan / Catatan Perkara: {$docSummary}\n";
        }
        if ($docUrl !== '') {
            $groundingContext .= "- Tautan Sumber Resmi: {$docUrl}\n";
        }
        if ($pdfText !== '') {
            $groundingContext .= "\nEKSTRAK TEKS RESMI DARI DOKUMEN PUTUSAN:\n{$pdfText}\n";
        }

        $systemInstruction = <<<EOT
Anda adalah "TanyaPutusan AI Legal Intelligence" di Lawco, asisten pakar hukum Indonesia yang menganalisis dokumen dan berkas putusan Mahkamah Agung Republik Indonesia.

ATURAN UTAMA & PROTOKOL GROUNDING:
1. Pengguna sedang menelaah perkara spesifik: '{$docTitle}'.
2. Fokuskan jawaban Anda 100% pada perkara ini dan data yang disediakan di bawah.
3. JANGAN PERNAH berhalusinasi atau mengarang nomor perkara, tanggal putusan, nama hakim, atau amar putusan yang tidak ada datanya.
4. Susun jawaban secara sistematis, terstruktur, elegan, dan gunakan format Markdown yang rapi:
   - **Kaidah Hukum / Analisis Doktrin**: Penjelasan hukum atas hal yang ditanyakan.
   - **Dasar Pertimbangan & Amar**: Uraian pertimbangan hakim dan amar putusan (jika relevan).
   - **Kesimpulan / Rekomendasi**: Ringkasan jawaban atas pertanyaan pengguna.
5. Gunakan bahasa Indonesia baku hukum yang profesional dan mudah dipahami.

{$groundingContext}
EOT;

        $messages = [
            ['role' => 'system', 'content' => $systemInstruction],
        ];

        if (is_array($history)) {
            foreach (array_slice($history, -6) as $item) {
                if (! empty($item['role']) && ! empty($item['content'])) {
                    $messages[] = [
                        'role' => $item['role'] === 'user' ? 'user' : 'assistant',
                        'content' => (string) $item['content'],
                    ];
                }
            }
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        try {
            $result = $aiClient->complete([
                'messages' => $messages,
                'temperature' => 0.3,
                'max_tokens' => 1500,
            ], allowGroq: false);

            return response()->json([
                'success' => true,
                'reply' => $result['content'],
                'model' => $result['model'],
                'provider' => $result['provider'],
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->error('Tanya AI Legal Decision error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses tanya AI: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Unduh PDF arsip dari R2 ke file temporer sistem, ekstrak teksnya, lalu hapus file temporernya.
     */
    private function extractArchivedPdfText(string $url): string
    {
        $archived = DownloadedLegalDecision::query()->where('source_url', $url)->first();
        if (empty($archived?->metadata['r2_path'])) {
            return '';
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'putusan_');

        try {
            file_put_contents($tempPath, Storage::disk('r2')->get('putusan_ma/'.md5($url).'.pdf'));

            return $this->extractPdfTextSnippet($tempPath);
        } catch (\Throwable $e) {
            Log::channel('single')->warning('R2 read error (AI chat): '.$e->getMessage());

            return '';
        } finally {
            @unlink($tempPath);
        }
    }

    /**
     * Ekstrak cuplikan teks dari file PDF menggunakan pdftotext jika tersedia.
     */
    private function extractPdfTextSnippet(string $filePath): string
    {
        try {
            $safePath = escapeshellarg($filePath);
            $command = "pdftotext -f 1 -l 10 -layout {$safePath} -";
            $output = @shell_exec($command);
            if (is_string($output) && trim($output) !== '') {
                $clean = preg_replace('/[^\x20-\x7E\r\n\t]/', ' ', $output);
                $clean = preg_replace('/\s+/', ' ', $clean);

                return substr(trim((string) $clean), 0, 4000);
            }
        } catch (\Throwable) {
            // fallback gracefully
        }

        return '';
    }
}
