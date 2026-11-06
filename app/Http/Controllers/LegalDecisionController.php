<?php

namespace App\Http\Controllers;

use App\Jobs\DownloadLegalDecisionJob;
use App\Jobs\RefreshLegalDecisionPage;
use App\Models\DownloadedLegalDecision;
use App\Models\LegalDecisionDownloadFailure;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Services\AiClient;
use App\Services\LegalDecisionPdfService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Smalot\PdfParser\Parser;
use Symfony\Component\HttpFoundation\HeaderUtils;

class LegalDecisionController extends Controller
{
    public function __construct(private LegalDecisionPdfService $pdfService) {}

    public function index(Request $request): View|RedirectResponse
    {
        $search = trim($request->input('search', ''));
        $year = $request->input('year');
        $page = max(1, (int) $request->input('page', 1));
        $downloadFilter = trim($request->input('download_status', '')); // 'downloaded', 'not_downloaded', atau ''

        $kategori = trim($request->input('kategori', ''));
        $peradilan = trim($request->input('peradilan', ''));
        $periode = trim($request->input('periode', 'putus'));

        $params = $this->pdfService->livePageParams(['kategori' => $kategori, 'peradilan' => $peradilan, 'periode' => $periode, 'tahun' => $year, 'page' => $page]);
        $cached = $this->pdfService->cachedLivePage($params);

        // Halaman ini tidak pernah memanggil scraper/portal MA secara langsung: selalu tampil dari cache, dan
        // pengambilan ulang dijadwalkan ke queue (satu job per kombinasi filter berkat atomic lock ShouldBeUnique).
        $isRefreshQueued = false;
        if (($request->boolean('refresh') || ! $cached || ! $cached['is_fresh']) && config('services.ma_scraper.url')) {
            RefreshLegalDecisionPage::dispatch($params);
            $isRefreshQueued = true;
        }
        // "refresh" hanya untuk permintaan ini; jangan terbawa ke link halaman/filter.
        $request->query->remove('refresh');

        $liveData = $cached['data'] ?? null;
        $blockedUntil = $this->pdfService->sourceBlockedUntil();
        $sidebarFilters = $liveData['sidebar_filters'] ?? $this->fallbackSidebarFilters($params);

        $rawItems = null;
        $downloadedTotal = 0;
        if ($downloadFilter === 'downloaded') {
            // "Sudah Di-download" dihitung murni dari database lokal: tanpa memindai halaman portal.
            $downloadedRows = $this->downloadedRowsForFilter($kategori, $peradilan, $year);
            $downloadedTotal = $downloadedRows->count();
            $rawItems = $downloadedRows->forPage($page, 20)->values()->map(fn (DownloadedLegalDecision $row) => [
                'judul' => $row->decision_number ?: $row->title,
                'url' => $row->source_url,
                'pengadilan' => $row->court_name,
                'klasifikasi' => $row->classification,
                'tahun' => $row->year,
                'tgl_putus' => $row->tgl_putus,
                'tgl_register' => $row->tgl_register,
                'tgl_upload' => $row->tgl_upload,
                'ringkasan' => $row->summary,
            ]);
        } elseif ($liveData) {
            $rawItems = collect($liveData['items']);
            if ($downloadFilter === 'not_downloaded') {
                // Hanya halaman portal yang sudah ada di cache; status unduhan dicek ke database lokal.
                $attempted = $this->attemptedDownloadSet($rawItems->pluck('url')->filter()->values());
                $rawItems = $rawItems->reject(fn (array $item) => isset($attempted[$item['url'] ?? '']))->values();
            }
        }

        if ($rawItems !== null) {
            $liveUrls = $rawItems->pluck('url')->filter()->values();
            $hashes = $liveUrls->map(fn ($u) => md5($u))->all();

            // source_url => record putusan yang sudah diunduh (beserta pengunduhnya)
            $downloadedUrls = DownloadedLegalDecision::query()
                ->with('user:id,name')
                ->whereIn('source_url', $liveUrls)
                ->get(['source_url', 'metadata', 'user_id'])
                ->keyBy('source_url');

            // Ambil data kegagalan download dari tabel baru legal_decision_download_failures
            $failures = LegalDecisionDownloadFailure::query()
                ->with('user:id,name')
                ->whereIn('url_hash', $hashes)
                ->get()
                ->keyBy('url_hash');

            $items = $rawItems->map(function ($it) use ($downloadedUrls, $failures) {
                $url = $it['url'] ?? '';
                $hash = $url !== '' ? md5($url) : '';
                $record = $url !== '' ? $downloadedUrls->get($url) : null;
                // Record tetap "Sudah ada"; cek ke R2 hanya untuk memberi tahu jika file PDF-nya gagal terunduh.
                $existsInR2 = $record !== null ? $this->pdfService->existsInR2($url) : null;
                $failure = ($record === null && $hash !== '') ? ($failures->get($hash) ?? Cache::get('legal-decision-download-failed:'.$hash)) : null;

                $isFailed = $failure !== null;
                $errorMessage = is_array($failure) ? ($failure['error'] ?? null) : $failure?->error_message;

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
                    'source_url' => $url ?: null,
                    'file_url' => null,
                    'is_downloaded' => $record !== null,
                    'is_pdf_missing' => $existsInR2 === false,
                    'is_download_failed' => $isFailed,
                    'download_error' => $errorMessage,
                    'is_in_cloud' => ! empty($record?->metadata['r2_path']),
                    'downloaded_by' => $record?->user?->name ?? ($failure instanceof LegalDecisionDownloadFailure ? $failure->user?->name : null),
                ];
            });

            $currentPage = $page;
            $maxPage = (int) ($liveData['pagination']['max_page'] ?? 499);
            // Total mengikuti filter aktif (dihitung scraper dari sidebar portal MA); perkiraan dari jumlah halaman jika tidak tersedia.
            $totalCount = $liveData['total_count'] ?? $maxPage * 20;

            if ($downloadFilter === 'downloaded') {
                $totalCount = $downloadedTotal;
                $paginatedTotal = $downloadedTotal;
            } else {
                if ($downloadFilter === 'not_downloaded') {
                    $totalCount = max(0, $totalCount - $this->downloadedRowsForFilter($kategori, $peradilan, $year)->count());
                }

                // Portal MA mendukung navigasi halaman hingga max_page (499); batasi agar tidak ada puluhan ribu halaman fiktif.
                // Total bisa meleset (max_page gagal terbaca): pastikan navigasi menjangkau halaman ini, dan halaman berikutnya
                // bila halaman portal ini penuh, agar tombol halaman tidak hilang.
                $portalCount = count($liveData['items'] ?? []);
                $paginatedTotal = max(min($totalCount, $maxPage * 20), ($currentPage - 1) * 20 + $portalCount + ($portalCount >= 20 ? 1 : 0));
            }

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
                    'download_status' => $downloadFilter,
                ],
                'decisions' => $decisions,
                'real_total_count' => $totalCount,
                'sidebar_filters' => $sidebarFilters,
                'is_live' => true,
                'download_notice' => null,
                'cached_at' => $liveData['cached_at'] ?? null,
                'is_refresh_queued' => $isRefreshQueued,
                'blocked_until' => $blockedUntil,
            ]);
        }

        // Belum pernah ada cache untuk filter ini: refresh sudah dijadwalkan, tampilkan status memuat (halaman memuat ulang otomatis).
        $decisions = new LengthAwarePaginator(
            collect([]),
            ($page - 1) * 20, // tetap tampilkan tombol kembali saat portal gagal di halaman > 1
            20,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('legal-decisions.index', [
            'filters' => [
                'search' => $search,
                'year' => $year,
                'kategori' => $kategori,
                'peradilan' => $peradilan,
                'periode' => $periode,
                'download_status' => $downloadFilter,
            ],
            'decisions' => $decisions,
            'real_total_count' => 0,
            'sidebar_filters' => $sidebarFilters,
            'is_live' => true,
            'is_loading' => $isRefreshQueued,
            'blocked_until' => $blockedUntil,
            'scraper_error' => $isRefreshQueued ? null : (Cache::get(RefreshLegalDecisionPage::errorKey($params)) ?? 'API scraper MA belum dikonfigurasi (MA_SCRAPER_URL).'),
            'loading_error' => Cache::get(RefreshLegalDecisionPage::errorKey($params)),
        ]);
    }

    /**
     * Sidebar filter portal dari cache halaman lain (halaman 1 filter yang sama, atau halaman utama) agar tetap tampil.
     *
     * @param  array{kategori: string, peradilan: string, periode: string, tahun: int, page: int}  $params
     * @return array<int, array<string, mixed>>
     */
    private function fallbackSidebarFilters(array $params): array
    {
        return $this->pdfService->cachedLivePage([...$params, 'page' => 1])['data']['sidebar_filters']
            ?? $this->pdfService->cachedLivePage($this->pdfService->livePageParams([]))['data']['sidebar_filters']
            ?? [];
    }

    /**
     * Jumlah putusan unik yang sudah diunduh dan cocok dengan filter portal (kategori/peradilan/tahun).
     *
     * Kolom classification/court_name menyimpan label ("Pidana Khusus > Narkotika"), sedangkan filter portal berupa slug,
     * sehingga dicocokkan lewat slug: kategori cocok dengan segmen mana pun dari klasifikasi.
     */
    private function downloadedRowsForFilter(string $kategori, string $peradilan, mixed $year): Collection
    {
        // Slug portal MA kadang diberi akhiran angka ("perdata-1", "pajak-2") dan peradilan berawalan "pengadilan/",
        // sedangkan slug dari label yang tersimpan tidak; samakan dulu agar jumlah cocok dengan daftar.
        $kategori = (string) preg_replace('/-\d+$/', '', $kategori);
        $peradilan = Str::after($peradilan, 'pengadilan/');

        // shortcut: dihitung di PHP atas seluruh baris unduhan; pindahkan ke kolom slug terindeks jika tabel sudah puluhan ribu baris.
        return DownloadedLegalDecision::query()
            ->when(! empty($year), fn (Builder $q) => $q->where('year', $year))
            ->latest()
            ->get()
            ->filter(function (DownloadedLegalDecision $row) use ($kategori, $peradilan): bool {
                $classificationSlugs = collect(explode('>', (string) $row->classification))->map(fn ($part) => Str::slug($part));

                return ($kategori === '' || $classificationSlugs->contains($kategori))
                    && ($peradilan === '' || Str::slug((string) $row->court_name) === $peradilan);
            })
            ->unique('source_url')
            ->values();
    }

    /**
     * Set URL yang sudah tercatat pernah diunduh (termasuk yang gagal unduh atau file R2 hilang)
     * agar tergolong "sudah di-download".
     *
     * @param  Collection<int, string>  $urls
     * @return array<string, true>
     */
    private function attemptedDownloadSet(Collection $urls): array
    {
        $downloaded = DownloadedLegalDecision::query()
            ->whereIn('source_url', $urls)
            ->pluck('source_url')
            ->all();

        $hashToUrl = $urls->mapWithKeys(fn (string $url) => [md5($url) => $url]);
        $failedHashes = LegalDecisionDownloadFailure::query()
            ->whereIn('url_hash', $hashToUrl->keys())
            ->pluck('url_hash');
        $cachedFailures = $hashToUrl->keys()->filter(fn (string $hash) => Cache::has('legal-decision-download-failed:'.$hash));

        $allUrls = array_merge($downloaded, $failedHashes->merge($cachedFailures)->map(fn ($h) => $hashToUrl[$h])->all());

        return array_fill_keys($allUrls, true);
    }

    /** @param array<int, array<string, mixed>> $items */
    private function allDownloaded(array $items): bool
    {
        $urls = collect($items)->pluck('url')->filter()->unique();

        return $urls->isNotEmpty()
            && DownloadedLegalDecision::query()->whereIn('source_url', $urls)->count() >= $urls->count();
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
        $userIdFilter = $request->filled('user_id') ? (int) $request->input('user_id') : null;
        ['from' => $recapFrom, 'to' => $recapTo, 'time_from' => $recapTimeFrom, 'time_to' => $recapTimeTo] = $this->recapDateRange($request);

        // Sumber data download: Murni dari tabel DownloadedLegalDecision (hanya yang sudah diunduh oleh user)
        $query = DownloadedLegalDecision::query()
            ->with('user:id,name')
            ->when($userIdFilter, fn (Builder $q) => $q->where('user_id', $userIdFilter))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('decision_number', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhere('summary', 'like', "%{$search}%")))
            ->when($kategori !== '', fn (Builder $q) => $q->where('classification', $kategori))
            ->when($peradilan !== '', fn (Builder $q) => $q->where('court_name', $peradilan))
            ->when(! empty($year), fn (Builder $q) => $q->where('year', $year));

        // Filter status file dari tabel: 'available' (r2_path tercatat) atau 'missing' (PDF gagal terunduh, r2_path kosong).
        $fileStatus = in_array($request->input('file_status'), ['available', 'missing'], true) ? $request->input('file_status') : '';
        $query->when($fileStatus === 'missing', fn (Builder $q) => $q->pdfMissing())
            ->when($fileStatus === 'available', fn (Builder $q) => $q->pdfAvailable());

        $decisions = $query
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $decisions->getCollection()->each(function (DownloadedLegalDecision $decision): void {
            $decision->setAttribute('is_pdf_missing', $decision->isPdfMissing());
        });

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

        // Rekapitulasi hasil unduhan per user, memakai kriteria yang sama dengan filter status file di tabel:
        // gagal = record arsip tanpa r2_path, berhasil = sisanya.
        // Waktu = updated_at (status terakhir file): retry yang berhasil tercatat di jam berhasil, gagal berulang di jam gagal terakhir.
        // Input & label memakai jam lokal (WIB); query dikonversi ke UTC karena timestamp disimpan dalam UTC.
        $localTimezone = config('automatic-parse.timezone', 'Asia/Jakarta');
        $recapLocalRange = $recapFrom ? [
            Carbon::parse($recapFrom.' '.($recapTimeFrom ?? '00:00'), $localTimezone)->startOfMinute(),
            Carbon::parse($recapTo.' '.($recapTimeTo ?? '23:59'), $localTimezone)->endOfMinute(),
        ] : null;
        $currentHourLocalRange = [now($localTimezone)->startOfHour(), now($localTimezone)->endOfHour()];
        $toAppTimezone = fn (array $range): array => array_map(fn (Carbon $time): Carbon => $time->copy()->timezone(config('app.timezone')), $range);
        $recapRange = $recapLocalRange ? $toAppTimezone($recapLocalRange) : null;
        $currentHourRange = $toAppTimezone($currentHourLocalRange);

        $countPerUser = fn (string $scope, ?array $range): array => DownloadedLegalDecision::query()
            ->{$scope}()
            ->when($range, fn (Builder $q) => $q->whereBetween('updated_at', $range))
            ->selectRaw('COALESCE(user_id, 0) as user_key, COUNT(*) as total')
            ->groupBy('user_key')
            ->pluck('total', 'user_key')
            ->map(fn ($total): int => (int) $total)
            ->all();
        $archiveCounts = [
            'success' => $countPerUser('pdfAvailable', $recapRange),
            'failure' => $countPerUser('pdfMissing', $recapRange),
            'hour_success' => $countPerUser('pdfAvailable', $currentHourRange),
            'hour_failure' => $countPerUser('pdfMissing', $currentHourRange),
        ];

        // Jumlah menit aktif per user (menit yang ada unduhannya) untuk rata-rata file/menit, agar jeda antar sesi tidak ikut dihitung.
        $minuteBucket = DownloadedLegalDecision::query()->getConnection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m-%d %H:%M', updated_at)"
            : "DATE_FORMAT(updated_at, '%Y-%m-%d %H:%i')";
        $activeSpans = DownloadedLegalDecision::query()
            ->when($recapRange, fn (Builder $q) => $q->whereBetween('updated_at', $recapRange))
            ->selectRaw("COALESCE(user_id, 0) as user_key, COUNT(DISTINCT {$minuteBucket}) as active_minutes")
            ->groupBy('user_key')
            ->pluck('active_minutes', 'user_key')
            ->map(fn ($minutes): int => max(1, (int) $minutes))
            ->all();

        // Pengguna yang pernah mengunduh tetap tampil (dengan angka 0) walau tidak ada unduhan pada periode filter.
        $everDownloadedKeys = DownloadedLegalDecision::query()
            ->selectRaw('DISTINCT COALESCE(user_id, 0) as user_key')
            ->pluck('user_key')
            ->all();
        $allUserKeys = collect($everDownloadedKeys)->map(fn ($uid): int => (int) $uid);

        $users = User::query()
            ->whereIn('id', $allUserKeys->filter())
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        $userDownloadStats = $allUserKeys->map(function (int $uid) use ($users, $archiveCounts, $activeSpans): object {
            $user = $users->get($uid);
            $success = $archiveCounts['success'][$uid] ?? 0;
            $failure = $archiveCounts['failure'][$uid] ?? 0;

            return (object) [
                'user_id' => $uid,
                'name' => $uid === 0 ? 'Sistem / Tanpa Akun' : ($user?->name ?? 'Pengguna #'.$uid),
                'email' => $uid === 0 ? 'system@lawco' : ($user?->email ?? '-'),
                'success_count' => $success,
                'failure_count' => $failure,
                'total_count' => $success + $failure,
                'hour_success_count' => $archiveCounts['hour_success'][$uid] ?? 0,
                'hour_failure_count' => $archiveCounts['hour_failure'][$uid] ?? 0,
                'per_minute' => (int) round(($success + $failure) / ($activeSpans[$uid] ?? 1)),
            ];
        })->sortByDesc('total_count')->values();

        // Non-admin hanya melihat rekapnya sendiri. Admin melihat semua pengguna saat "Tampilkan Semua Pengguna",
        // atau dirinya + pengguna yang dipilih lewat dropdown.
        $currentUser = auth()->user();
        $isAdmin = (bool) $currentUser?->isAdmin();
        $userOptions = $isAdmin ? $userDownloadStats->filter(fn (object $stat): bool => $stat->user_id > 0)->values() : collect();
        $visibleUserIds = array_filter([(int) $currentUser?->id, $isAdmin ? $userIdFilter : null]);
        $summarySource = $isAdmin ? $userDownloadStats : $userDownloadStats->whereIn('user_id', $visibleUserIds);

        $userDownloadStats = $userDownloadStats
            ->when(! $isAdmin || $userIdFilter, fn ($stats) => $stats->whereIn('user_id', $visibleUserIds))
            ->sortByDesc(fn (object $stat): bool => $stat->user_id === (int) $currentUser?->id)
            ->values();

        $totalSuccessCount = $summarySource->sum('success_count');
        $totalFailureCount = $summarySource->sum('failure_count');

        $summaryStats = (object) [
            'total_success' => $totalSuccessCount,
            'total_failure' => $totalFailureCount,
            'total_all' => $totalSuccessCount + $totalFailureCount,
        ];

        // Rata-rata per pengguna dari baris yang tampil di tabel rekap.
        $userAverages = $userDownloadStats->isEmpty() ? null : (object) [
            'success' => (int) round($userDownloadStats->avg('success_count')),
            'failure' => (int) round($userDownloadStats->avg('failure_count')),
            'total' => (int) round($userDownloadStats->avg('total_count')),
            'per_minute' => (int) round($userDownloadStats->avg('per_minute')),
        ];

        return view('legal-decisions.index', [
            'filters' => [
                'search' => $search,
                'kategori' => $kategori,
                'peradilan' => $peradilan,
                'year' => $year,
                'periode' => $periode,
                'file_status' => $fileStatus,
                'user_id' => $userIdFilter,
                'recap_from' => $recapFrom,
                'recap_to' => $recapTo,
                'recap_time_from' => $recapTimeFrom,
                'recap_time_to' => $recapTimeTo,
            ],
            'decisions' => $decisions,
            'real_total_count' => $decisions->total(),
            'sidebar_filters' => $sidebarFilters,
            'userDownloadStats' => $userDownloadStats,
            'userOptions' => $userOptions,
            'currentUserId' => (int) $currentUser?->id,
            'canSendWorkAlert' => $isAdmin,
            'summaryStats' => $summaryStats,
            'userAverages' => $userAverages,
            'recapPeriodLabel' => $recapLocalRange
                ? $recapLocalRange[0]->format('d/m/Y H:i').' – '.$recapLocalRange[1]->format('d/m/Y H:i').' WIB'
                : 'Semua Waktu',
            'currentHourLabel' => $currentHourLocalRange[0]->format('H:i').' – '.$currentHourLocalRange[1]->format('H:i').' WIB',
            'selectedUserId' => $userIdFilter,
            'is_live' => false,
            'listRoute' => 'legal-decisions.downloads',
            'pageTitle' => 'Putusan MA (Download)',
            'pageDescription' => 'Putusan Mahkamah Agung yang sudah diunduh dan tersimpan di database internal.',
            'emptyMessage' => 'Putusan akan muncul di sini setelah diunduh dari menu Putusan MA (langsung).',
        ]);
    }

    /**
     * Rentang tanggal rekap download dari query string (format Y-m-d). Tanggal tidak valid diabaikan,
     * tanggal akhir kosong disamakan dengan tanggal awal, dan urutan yang terbalik ditukar.
     *
     * Jam (H:i) opsional: awal rentang = tanggal awal + jam awal, akhir rentang = tanggal akhir + jam akhir.
     *
     * @return array{from: ?string, to: ?string, time_from: ?string, time_to: ?string}
     */
    private function recapDateRange(Request $request): array
    {
        $validDate = function (mixed $value): ?string {
            if (! is_string($value) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $parts)) {
                return null;
            }

            return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $value : null;
        };

        $validTime = fn (mixed $value): ?string => is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : null;

        $timeFrom = $validTime($request->query('recap_time_from'));
        $timeTo = $validTime($request->query('recap_time_to'));
        $from = $validDate($request->query('recap_from'));

        // Filter jam tanpa tanggal berarti jam tersebut pada hari ini.
        if ($from === null && ($timeFrom !== null || $timeTo !== null)) {
            $from = now(config('automatic-parse.timezone', 'Asia/Jakarta'))->toDateString();
        }

        $to = $validDate($request->query('recap_to')) ?? $from;

        if ($from === null) {
            return ['from' => null, 'to' => null, 'time_from' => null, 'time_to' => null];
        }

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return ['from' => $from, 'to' => $to, 'time_from' => $timeFrom, 'time_to' => $timeTo];
    }

    /**
     * Admin mengirim peringatan suara ke user yang progres download-nya macet (boleh ke diri sendiri untuk uji coba).
     * Disimpan di cache dan diambil browser user lewat polling.
     */
    public function sendWorkAlert(User $user): JsonResponse
    {
        Cache::put('legal-decision-work-alert:'.$user->id, [
            'message' => "Halo kak {$user->name}, kerja!",
            'sent_by' => auth()->user()->name,
        ], now()->addMinutes(10));

        return response()->json(['success' => true, 'message' => "Peringatan dikirim ke {$user->name}."]);
    }

    /**
     * Polling dari browser user: ambil (sekali pakai) peringatan yang dikirim admin.
     */
    public function pendingWorkAlert(): JsonResponse
    {
        return response()->json(['alert' => Cache::pull('legal-decision-work-alert:'.auth()->id())]);
    }

    /**
     * Hapus satu putusan yang sudah diunduh (termasuk berkas di R2).
     */
    public function destroyDownload(int|string $id): RedirectResponse
    {
        $decision = DownloadedLegalDecision::query()->findOrFail($id);
        $number = $decision->decision_number;

        if ($decision->source_url) {
            $this->pdfService->deleteFromR2($decision->source_url);
        }

        $decision->delete();

        UserActivityLog::log(
            'deleted',
            DownloadedLegalDecision::class,
            null,
            "Menghapus Putusan MA dari arsip: {$number}"
        );

        return redirect()->back()->with('success', 'Putusan berhasil dihapus dari arsip download.');
    }

    /**
     * Hapus beberapa putusan yang dipilih secara massal.
     */
    public function bulkDestroyDownloads(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer'],
        ]);

        $decisions = DownloadedLegalDecision::query()->whereIn('id', $validated['ids'])->get();
        $count = $decisions->count();

        foreach ($decisions as $decision) {
            if ($decision->source_url) {
                $this->pdfService->deleteFromR2($decision->source_url);
            }
            $decision->delete();
        }

        UserActivityLog::log(
            'deleted',
            DownloadedLegalDecision::class,
            null,
            "Menghapus {$count} Putusan MA secara massal dari arsip download"
        );

        return redirect()->back()->with('success', "{$count} putusan berhasil dihapus dari arsip download.");
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
        if ($this->pdfService->isArchiveComplete($existing)) {
            return response()->json([
                'success' => true,
                'already_exists' => true,
                'message' => 'Putusan sudah ada di database.',
                'decision_number' => $existing->decision_number,
            ]);
        }

        // Record hanya disimpan jika PDF benar-benar didapat, supaya arsip tidak berisi putusan yang preview-nya 404.
        ['content' => $content, 'r2_path' => $r2Path, 'error' => $error] = $this->pdfService->archive($url);
        if ($error !== null) {
            Log::channel('single')->warning("[downloadSingle] {$url}: {$error}");

            // Tetap masuk ke Putusan MA (download) dengan peringatan "File gagal terunduh".
            $failedRecord = $this->pdfService->recordFailedDownload($url, $validated, auth()->id(), $error);

            $failureRecord = LegalDecisionDownloadFailure::recordFailure(
                url: $url,
                errorMessage: $error,
                decisionNumber: $customTitle ?: $safeName,
                userId: auth()->id()
            );

            UserActivityLog::log(
                action: 'download_failed',
                subjectType: DownloadedLegalDecision::class,
                subjectId: $failedRecord->id,
                description: 'Gagal mengunduh Putusan MA: '.($customTitle ?: $safeName).' ('.$error.')',
                userId: auth()->id()
            );

            Cache::put('legal-decision-download-failed:'.md5($url), [
                'error' => $error,
                'failed_at' => now()->toISOString(),
            ], now()->addDays(7));

            return response()->json([
                'success' => false,
                'message' => $existing ? "Putusan sudah tercatat, tetapi PDF-nya belum tersimpan: {$error}" : $error,
                'decision_number' => $customTitle ?: $safeName,
            ], 422);
        }

        LegalDecisionDownloadFailure::clearFailure($url, auth()->id());
        Cache::forget('legal-decision-download-failed:'.md5($url));

        // Putusan lama tanpa PDF di R2: lengkapi arsipnya, bukan membuat record baru.
        if ($existing) {
            $existing->update([
                'user_id' => auth()->id() ?? $existing->user_id,
                'file_size' => strlen($content),
                'metadata' => [...($existing->metadata ?? []), ...$this->pdfService->integrityMetadata($content, $r2Path)],
            ]);

            UserActivityLog::log(
                'downloaded',
                DownloadedLegalDecision::class,
                $existing->id,
                "Mengunduh dan melengkapi berkas PDF Putusan MA: {$existing->decision_number}"
            );

            return response()->json([
                'success' => true,
                'already_exists' => false,
                'message' => 'PDF putusan berhasil dilengkapi di arsip.',
                'decision_number' => $existing->decision_number,
            ]);
        }

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
                'file_size' => strlen($content),
                'mime_type' => 'application/pdf',
                'download_type' => 'manual',
                'metadata' => [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    ...$this->pdfService->integrityMetadata($content, $r2Path),
                ],
            ]);

            UserActivityLog::log(
                'downloaded',
                DownloadedLegalDecision::class,
                $record->id,
                "Mengunduh Putusan MA: {$record->decision_number}"
            );

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
            $existing = DownloadedLegalDecision::query()->where('source_url', $url)->first();

            // Arsip lama yang PDF-nya belum ada di R2 tetap diantrikan supaya dilengkapi.
            if ($this->pdfService->isArchiveComplete($existing)) {
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
        $objectPath = $this->pdfService->objectPath($url);

        $safeName = $customTitle !== '' ? preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $customTitle) : 'Putusan_MA_'.$hash;
        $safeName = trim(preg_replace('/\s+/', '_', $safeName)).'.pdf';

        $archived = DownloadedLegalDecision::query()->where('source_url', $url)->first();

        // Sudah lengkap di arsip: jangan diunduh ulang dari halaman live, cukup beri informasi.
        // Unduhan dari halaman arsip (archived=1), atau arsip yang PDF-nya belum ada di R2, tetap dilayani.
        $isArchiveComplete = $this->pdfService->isArchiveComplete($archived);
        if ($isArchiveComplete && $isDownload && ! $request->boolean('archived')) {
            return back()->with('info', 'Putusan "'.($archived->decision_number ?: $customTitle).'" sudah ada di Putusan MA (download).');
        }

        // Baca dari R2 jika file-nya ada di bucket (tercatat di arsip, preview sebelumnya, atau environment lain),
        // sehingga PDF yang sama tidak diambil berulang dari portal MA.
        $existsInR2 = $this->pdfService->existsInR2($url);
        if ($existsInR2 || ($existsInR2 === null && ! empty($archived?->metadata['r2_path']))) {
            try {
                $response = Storage::disk('r2')->response($objectPath, $safeName, [
                    'Content-Type' => 'application/pdf',
                ], $isDownload ? 'attachment' : 'inline');

                if ($isDownload) {
                    $this->recordDownload($request, $url, $customTitle, $safeName, null);
                }

                return $response;
            } catch (\Throwable $e) {
                Log::channel('single')->warning('R2 read error, ambil ulang dari scraper: '.$e->getMessage());
            }
        }

        ['content' => $content, 'error' => $error] = $this->pdfService->fetch($url);

        if ($content === null) {
            Log::channel('single')->error("Failed to fetch PDF for decision: {$url} Reason: {$error}");

            // Catat kegagalan ke database & cache agar berstatus "Gagal diunduh" dengan tombol "Coba Lagi"
            if ($isDownload) {
                LegalDecisionDownloadFailure::recordFailure(
                    url: $url,
                    errorMessage: $error,
                    decisionNumber: $customTitle ?: $safeName,
                    userId: auth()->id()
                );

                UserActivityLog::log(
                    action: 'download_failed',
                    subjectType: DownloadedLegalDecision::class,
                    subjectId: null,
                    description: 'Gagal mengunduh Putusan MA: '.($customTitle ?: $safeName).' ('.$error.')',
                    userId: auth()->id()
                );

                Cache::put('legal-decision-download-failed:'.md5($url), [
                    'error' => $error,
                    'failed_at' => now()->toISOString(),
                ], now()->addDays(7));
            }

            if ($isDownload) {
                $this->pdfService->recordFailedDownload($url, $this->decisionDataFromRequest($request, $customTitle), auth()->id(), (string) ($error ?: 'PDF tidak dapat diambil dari portal MA.'));
            }

            // Portal MA menolak permintaan server (mis. 403): arahkan unduhan langsung ke halaman putusan MA.
            if ($isDownload && str_ends_with((string) parse_url($url, PHP_URL_HOST), 'mahkamahagung.go.id')) {
                return redirect()->away($url);
            }

            // Preview tampil di iframe modal: tampilkan pesan yang jelas, bukan halaman 404 polos.
            return response()->view('legal-decisions.pdf-unavailable', ['sourceUrl' => $url, 'reason' => $error], 404);
        }

        if ($isDownload) {
            LegalDecisionDownloadFailure::clearFailure($url, auth()->id());
            Cache::forget('legal-decision-download-failed:'.md5($url));
            $this->recordDownload($request, $url, $customTitle, $safeName, $content);
        } elseif ($existsInR2 === false) {
            // Preview: simpan juga ke R2 (tanpa mencatat sebagai unduhan) agar preview/unduhan berikutnya tidak ke portal MA.
            $this->pdfService->uploadToR2($content, $objectPath);
        }

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($isDownload ? 'attachment' : 'inline', $safeName),
        ]);
    }

    /**
     * Metadata putusan dari query string halaman live (dipakai saat mencatat unduhan yang gagal).
     *
     * @return array<string, string>
     */
    private function decisionDataFromRequest(Request $request, string $customTitle): array
    {
        return [
            'decision_number' => $customTitle,
            'court_name' => (string) $request->input('court', ''),
            'classification' => (string) $request->input('classification', ''),
            'year' => (string) $request->input('year', ''),
            'summary' => (string) $request->input('summary', ''),
            'tgl_register' => (string) $request->input('tgl_register', ''),
            'tgl_putus' => (string) $request->input('tgl_putus', ''),
            'tgl_upload' => (string) $request->input('tgl_upload', ''),
        ];
    }

    /**
     * Catat unduhan dan salin PDF ke Cloudflare R2 sekali per putusan (dilewati jika sudah pernah diunduh).
     */
    private function recordDownload(Request $request, string $url, string $customTitle, string $safeName, ?string $content): void
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

            if ($content !== null && ! $this->pdfService->isArchiveComplete($existing)) {
                $r2Path = $this->pdfService->uploadToR2($content, $this->pdfService->objectPath($url));
                if ($r2Path !== null) {
                    $updates['metadata'] = [...($existing->metadata ?? []), ...$this->pdfService->integrityMetadata($content, $r2Path)];
                    $updates['file_size'] = strlen($content);
                }
            }

            if (! empty($updates)) {
                $existing->update($updates);
            }

            UserActivityLog::log(
                'downloaded',
                DownloadedLegalDecision::class,
                $existing->id,
                "Mengunduh Putusan MA: {$existing->decision_number}"
            );

            return;
        }

        $r2Path = $content !== null ? $this->pdfService->uploadToR2($content, $this->pdfService->objectPath($url)) : null;

        // PDF tidak didapat atau gagal disimpan ke R2: tetap catat ke arsip dengan peringatan "File gagal terunduh".
        if ($content === null || ($r2Path === null && $this->pdfService->isR2Configured())) {
            Log::channel('single')->warning("[recordDownload] {$url}: PDF gagal disimpan ke R2, dicatat sebagai gagal terunduh.");
            $this->pdfService->recordFailedDownload($url, $this->decisionDataFromRequest($request, $customTitle), auth()->id(), $content === null ? 'PDF tidak dapat diambil dari portal MA.' : 'PDF gagal disimpan ke Cloudflare R2.');

            return;
        }

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
                'file_path' => $this->pdfService->objectPath($url),
                'file_size' => strlen($content),
                'mime_type' => 'application/pdf',
                'download_type' => 'manual',
                'metadata' => [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    ...$this->pdfService->integrityMetadata($content, $r2Path),
                ],
            ]);

            UserActivityLog::log(
                'downloaded',
                DownloadedLegalDecision::class,
                $record->id,
                "Mengunduh Putusan MA: {$record->decision_number}"
            );
        } catch (\Throwable $e) {
            Log::channel('single')->warning('Failed to log downloaded decision: '.$e->getMessage());
        }
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

        // Ambil ekstrak teks PDF (dari Cloudflare R2 atau langsung via fetch scraper & cache)
        $pdfText = '';
        if ($docUrl !== '') {
            $pdfText = $this->resolvePdfTextForAi($docUrl);
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
Anda adalah "TanyaPutusan AI Legal Intelligence" di Lawco, asisten analis hukum terfokus yang bertugas secara eksklusif untuk membedah dan menganalisis berkas dokumen perkara putusan ini: '{$docTitle}'.

ATURAN UTAMA & PROTOKOL GROUNDING (KETAT):
1. RUANG LINGKUP EKSKLUSIF (OUT-OF-SCOPE REFUSAL):
   - Anda HANYA diperkenankan menjawab pertanyaan yang berkaitan dengan perkara ini ('{$docTitle}'), dokumen putusan, kronologi perkara, dakwaan, pertimbangan hukum hakim, amar putusan, ketentuan undang-undang/pasal terkait, serta implikasi hukum perkaranya.
   - JIKA pengguna mengajukan pertanyaan di luar konteks perkara atau dokumen hukum ini (contoh: coding/programming, perintah git/terminal, matematika umum, resep masakan, obrolan santai umum, atau topik lain di luar dokumen putusan ini), Anda WAJIB MENOLAKNYA secara tegas, sopan, dan singkat.
   - Pola jawaban jika di luar konteks:
     "Mohon maaf, saya adalah asisten **TanyaPutusan AI** yang khusus bertugas untuk menganalisis dan membedah dokumen perkara **{$docTitle}**. Pertanyaan Anda berada di luar konteks perkara atau dokumen putusan ini. Silakan ajukan pertanyaan seputar isi berkas, amar putusan, dakwaan, pertimbangan hakim, atau implikasi hukum terkait perkara ini."
   - JANGAN PERNAH menjelaskan hal umum seperti output git, kode program, atau topik umum lainnya!

2. DASAR KEBENARAN DOKUMEN:
   - Gunakan "EKSTRAK TEKS RESMI DARI DOKUMEN PUTUSAN" di bawah sebagai sumber kebenaran utama untuk menjawab pertanyaan terkait substansi perkara.
   - JANGAN PERNAH mengarang atau berhalusinasi informasi penting (seperti pasal, hukuman, atau amar) yang tidak didukung fakta dokumen.

3. FORMAT STRUKTUR JAWABAN (UNTUK PERTANYAAN TENTANG PERKARA):
   - **Kaidah Hukum / Analisis Doktrin**: Penjelasan hukum atas hal yang ditanyakan.
   - **Dasar Pertimbangan & Amar**: Uraian pertimbangan hakim dan amar putusan (termasuk vonis, dikabulkan/ditolak).
   - **Kesimpulan / Rekomendasi**: Ringkasan jawaban atas pertanyaan pengguna.

4. Gunakan bahasa Indonesia baku hukum yang profesional, lugas, dan terstruktur rapi.

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
                'temperature' => 0.2,
                'max_tokens' => 2000,
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
     * Dapatkan ekstrak teks PDF secara realtime, baik dari Cloudflare R2 atau langsung unduh sementara via scraper.
     */
    private function resolvePdfTextForAi(string $url): string
    {
        $cacheKey = 'pdf_text_ai_'.md5($url);

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($url) {
            $archived = DownloadedLegalDecision::query()->where('source_url', $url)->first();
            $tempPath = tempnam(sys_get_temp_dir(), 'putusan_ai_');

            try {
                // 1. Coba baca dari R2 jika sudah ada
                if ($archived && ($this->pdfService->existsInR2($url) ?? ! empty($archived->metadata['r2_path']))) {
                    try {
                        file_put_contents($tempPath, Storage::disk('r2')->get('putusan_ma/'.md5($url).'.pdf'));
                        $text = $this->extractPdfTextSnippet($tempPath);
                        if (trim($text) !== '') {
                            return $text;
                        }
                    } catch (\Throwable $e) {
                        Log::channel('single')->warning('R2 read error (AI chat): '.$e->getMessage());
                    }
                }

                // 2. Jika belum ada di R2 (live / belum di-download), ambil PDF via scraper service secara on-the-fly
                ['content' => $content, 'error' => $error] = $this->pdfService->fetch($url);
                if (! empty($content)) {
                    file_put_contents($tempPath, $content);
                    $text = $this->extractPdfTextSnippet($tempPath);
                    if (trim($text) !== '') {
                        return $text;
                    }
                } else {
                    Log::channel('single')->warning("Gagal fetch PDF live untuk AI chat ({$url}): {$error}");
                }
            } catch (\Throwable $e) {
                Log::channel('single')->warning('Error resolving PDF text for AI chat: '.$e->getMessage());
            } finally {
                if (file_exists($tempPath)) {
                    @unlink($tempPath);
                }
            }

            return '';
        });
    }

    /**
     * Ekstrak cuplikan teks komprehensif dari file PDF (awal, pertimbangan, dan amar di akhir).
     */
    private function extractPdfTextSnippet(string $filePath): string
    {
        // 1. Coba menggunakan pdftotext CLI jika tersedia di sistem
        try {
            $safePath = escapeshellarg($filePath);
            $tmpOut = tempnam(sys_get_temp_dir(), 'pdftxt_out_');

            // Ekstrak seluruh halaman ke file teks temporer
            $command = "pdftotext -layout {$safePath} ".escapeshellarg($tmpOut);
            @exec($command, $cliOutput, $returnVar);

            if ($returnVar === 0 && file_exists($tmpOut)) {
                $rawText = (string) file_get_contents($tmpOut);
                @unlink($tmpOut);

                if (trim($rawText) !== '') {
                    $snippet = $this->smartCropDecisionText($rawText);
                    if (trim($snippet) !== '') {
                        return $snippet;
                    }
                }
            }
            if (file_exists($tmpOut)) {
                @unlink($tmpOut);
            }
        } catch (\Throwable) {
            // fallback to PHP library
        }

        // 2. Fallback menggunakan Smalot\PdfParser
        try {
            $parser = new Parser;
            $pdf = $parser->parseFile($filePath);
            $pages = $pdf->getPages();
            $totalPages = count($pages);

            if ($totalPages > 0) {
                // Ambil halaman awal (identitas/dakwaan) dan halaman akhir (pertimbangan/amar)
                $selectedPages = [];
                if ($totalPages <= 10) {
                    $selectedPages = $pages;
                } else {
                    // 4 halaman awal + 6 halaman akhir (karena amar & pertimbangan hakim selalu di halaman belakang)
                    $selectedPages = array_merge(
                        array_slice($pages, 0, 4),
                        array_slice($pages, -6)
                    );
                }

                $combinedText = '';
                foreach ($selectedPages as $page) {
                    $combinedText .= "\n".$page->getText();
                }

                return $this->cleanPdfText($combinedText, 12000);
            }
        } catch (\Throwable $e) {
            Log::channel('single')->warning('Smalot parser fallback failed: '.$e->getMessage());
        }

        return '';
    }

    /**
     * Potong teks putusan secara cerdas: mencakup identitas awal serta pertimbangan dan amar putusan di bagian akhir.
     */
    private function smartCropDecisionText(string $rawText): string
    {
        $clean = preg_replace('/[^\x20-\x7E\r\n\t]/', ' ', $rawText);
        $clean = preg_replace('/[ \t]+/', ' ', (string) $clean);
        $clean = trim((string) preg_replace('/\n\s*\n+/', "\n\n", $clean));

        // Jika teks cukup ringkas (< 14000 karakter), kirim semuanya
        if (mb_strlen($clean) <= 14000) {
            return $clean;
        }

        // Jika dokumen tebal, ambil 4.000 karakter awal (identitas & dakwaan) + 9.000 karakter akhir (pertimbangan hakim & amar)
        $head = mb_substr($clean, 0, 4000);
        $tail = mb_substr($clean, -9000);

        return $head."\n\n[... Bagian tengah dokumen dilewati ...]\n\n".$tail;
    }

    /**
     * Bersihkan teks PDF dari karakter non-printable dan batasi panjangnya.
     */
    private function cleanPdfText(string $text, int $limit = 12000): string
    {
        $clean = preg_replace('/[^\x20-\x7E\r\n\t]/', ' ', $text);
        $clean = preg_replace('/\s+/', ' ', (string) $clean);

        return mb_substr(trim((string) $clean), 0, $limit);
    }
}
