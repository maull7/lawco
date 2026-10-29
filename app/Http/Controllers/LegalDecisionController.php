<?php

namespace App\Http\Controllers;

use App\Models\DownloadedLegalDecision;
use App\Models\LegalDecision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

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
        $cacheKey = 'ma_live_'.md5("{$search}_{$kategori}_{$peradilan}_{$periode}_{$year}_{$page}");

        $scraperError = null;
        $liveData = Cache::remember($cacheKey, 600, function () use ($search, $kategori, $peradilan, $periode, $year, $page, &$scraperError) {
            $script = base_path('scripts/live_ma_scraper.py');
            $python = file_exists('C:\\Python314\\python.exe') ? 'C:\\Python314\\python.exe' : (PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3');
            $sitePackages = 'C:\\Users\\user\\AppData\\Roaming\\Python\\Python314\\site-packages';
            $cmd = 'set "PYTHONPATH='.$sitePackages.'" && "'.$python.'" "'.$script.'" --page='.(int) $page.' --live 2>&1';
            if ($search !== '') {
                $cmd .= ' --q='.escapeshellarg($search);
            }
            if ($kategori !== '') {
                $cmd .= ' --kategori='.escapeshellarg($kategori);
            }
            if ($peradilan !== '') {
                $cmd .= ' --peradilan='.escapeshellarg($peradilan);
            }
            if ($periode !== '') {
                $cmd .= ' --periode='.escapeshellarg($periode);
            }
            if ($year) {
                $cmd .= ' --tahun='.(int) $year;
            }

            $output = shell_exec($cmd);
            if ($output) {
                $json = json_decode(trim($output), true);
                if (isset($json['success']) && $json['success'] && ! empty($json['items'])) {
                    return $json;
                }
                $scraperError = $json['error'] ?? (! empty($json['success']) ? 'Portal MA tidak mengembalikan data untuk filter ini.' : 'Output scraper tidak valid: '.substr(trim($output), 0, 400));
                Log::channel('single')->error('Live Scraper Failed JSON: '.substr($output, 0, 400));
            } else {
                $scraperError = 'Scraper tidak menghasilkan output (cek python3, curl_cffi, dan shell_exec). Perintah: '.$cmd;
                Log::channel('single')->error('Live Scraper Output is NULL or Empty for cmd: '.$cmd);
            }

            return null;
        });

        // Jika live fetch berhasil, gunakan data real-time langsung dari MA
        if ($liveData && ! empty($liveData['items'])) {
            $items = collect($liveData['items'])->map(function ($it) {
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
                ];
            });

            $currentPage = $liveData['pagination']['current_page'] ?? $page;
            $totalCount = $liveData['total_count'] ?? 11450852;
            $maxPage = $liveData['pagination']['max_page'] ?? 499;

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

        $decisions = DownloadedLegalDecision::query()
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('decision_number', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhere('summary', 'like', "%{$search}%")))
            ->when($kategori !== '', fn (Builder $q) => $q->where('classification', $kategori))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $classifications = DownloadedLegalDecision::query()
            ->whereNotNull('classification')
            ->selectRaw('classification, count(*) as total')
            ->groupBy('classification')
            ->orderBy('classification')
            ->get()
            ->map(fn ($row): array => ['label' => $row->classification, 'kategori' => $row->classification, 'count' => $row->total]);

        return view('legal-decisions.index', [
            'filters' => ['search' => $search, 'kategori' => $kategori],
            'decisions' => $decisions,
            'sidebar_filters' => $classifications->isEmpty() ? [] : [[
                'title' => 'Direktori',
                'items' => [['label' => 'Semua Direktori'], ...$classifications],
            ]],
            'is_live' => false,
            'listRoute' => 'legal-decisions.downloads',
            'pageTitle' => 'Putusan MA (Download)',
            'pageDescription' => 'Putusan Mahkamah Agung yang sudah diunduh dan tersimpan di database internal.',
            'emptyMessage' => 'Putusan akan muncul di sini setelah diunduh dari menu Putusan MA (langsung).',
        ]);
    }

    public function streamPdf(Request $request)
    {
        $url = trim($request->input('url', ''));
        if (empty($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
            abort(404, 'URL putusan tidak valid.');
        }

        $isDownload = $request->boolean('download');
        $customTitle = trim($request->input('title', ''));

        $hash = md5($url);
        $dir = storage_path('app/putusan_ma');
        if (! file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $filePath = $dir.DIRECTORY_SEPARATOR.$hash.'.pdf';

        if (! file_exists($filePath) || filesize($filePath) < 500) {
            $script = base_path('scripts/live_ma_scraper.py');
            $python = file_exists('C:\\Python314\\python.exe') ? 'C:\\Python314\\python.exe' : (PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3');
            $sitePackages = 'C:\\Users\\user\\AppData\\Roaming\\Python\\Python314\\site-packages';

            $cmd = 'set "PYTHONPATH='.$sitePackages.'" && "'.$python.'" "'.$script.'" --fetch-pdf='.escapeshellarg($url).' --out='.escapeshellarg($filePath).' 2>&1';
            $output = shell_exec($cmd);

            if (! file_exists($filePath) || filesize($filePath) < 500) {
                Log::channel('single')->error('Failed to fetch PDF for decision: '.$url.' Output: '.substr($output ?? '', 0, 300));

                // Portal MA menolak permintaan server (mis. 403): arahkan unduhan langsung ke halaman putusan MA.
                if ($isDownload && str_ends_with((string) parse_url($url, PHP_URL_HOST), 'mahkamahagung.go.id')) {
                    return redirect()->away($url);
                }

                abort(404, 'File PDF putusan tidak dapat diunduh dari portal Mahkamah Agung.');
            }
        }

        $safeName = $customTitle !== '' ? preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $customTitle) : 'Putusan_MA_'.$hash;
        $safeName = trim(preg_replace('/\s+/', '_', $safeName)).'.pdf';

        if ($isDownload) {
            $this->recordDownload($request, $url, $customTitle, $safeName, $filePath, $hash);

            return response()->download($filePath, $safeName, [
                'Content-Type' => 'application/pdf',
            ]);
        }

        return response()->file($filePath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$safeName.'"',
        ]);
    }

    /**
     * Catat unduhan dan salin PDF ke Supabase sekali per putusan (dilewati jika sudah pernah diunduh).
     */
    private function recordDownload(Request $request, string $url, string $customTitle, string $safeName, string $filePath, string $hash): void
    {
        $existing = DownloadedLegalDecision::query()->where('source_url', $url)->first();

        if ($existing) {
            // Sudah tercatat: hanya lengkapi upload Supabase yang sebelumnya belum/gagal.
            if (empty($existing->metadata['supabase_path'])) {
                $existing->update(['metadata' => [
                    ...($existing->metadata ?? []),
                    'supabase_path' => $this->uploadToSupabase($filePath, 'putusan_ma/'.$hash.'.pdf'),
                ]]);
            }

            return;
        }

        $supabasePath = $this->uploadToSupabase($filePath, 'putusan_ma/'.$hash.'.pdf');

        try {
            DownloadedLegalDecision::create([
                'user_id' => auth()->id(),
                'decision_number' => $customTitle ?: $safeName,
                'title' => $customTitle ?: $safeName,
                'court_name' => 'Mahkamah Agung',
                'source_url' => $url,
                'file_name' => $safeName,
                'file_path' => 'putusan_ma/'.$hash.'.pdf',
                'file_size' => file_exists($filePath) ? filesize($filePath) : null,
                'mime_type' => 'application/pdf',
                'download_type' => 'manual',
                'metadata' => [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'supabase_path' => $supabasePath,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('single')->warning('Failed to log downloaded decision: '.$e->getMessage());
        }
    }

    /**
     * Upload PDF ke Supabase Storage. Mengembalikan "bucket/path" jika berhasil, null jika gagal atau belum dikonfigurasi.
     */
    private function uploadToSupabase(string $filePath, string $objectPath): ?string
    {
        $baseUrl = rtrim((string) config('services.supabase.url'), '/');
        $key = (string) config('services.supabase.key');
        $bucket = (string) config('services.supabase.bucket');

        if ($baseUrl === '' || $key === '' || $bucket === '') {
            return null;
        }

        try {
            $response = Http::withToken($key)
                ->withHeaders(['apikey' => $key, 'x-upsert' => 'false'])
                ->timeout(60)
                ->withBody(file_get_contents($filePath), 'application/pdf')
                ->post("{$baseUrl}/storage/v1/object/{$bucket}/{$objectPath}");

            // Objek yang sudah ada di bucket dianggap berhasil agar tidak terupload ganda.
            if ($response->successful() || str_contains($response->body(), 'Duplicate')) {
                return "{$bucket}/{$objectPath}";
            }

            Log::channel('single')->warning('Supabase upload failed: '.$response->status().' '.substr($response->body(), 0, 300));
        } catch (\Throwable $e) {
            Log::channel('single')->warning('Supabase upload error: '.$e->getMessage());
        }

        return null;
    }
}
