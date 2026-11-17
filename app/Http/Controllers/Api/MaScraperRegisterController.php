<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LegalDecisionPdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Registrasi URL tunnel dari ma-scraper-api/server.py saat start, agar URL quick tunnel yang berganti tidak perlu diisi manual di .env.
 */
class MaScraperRegisterController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $token = (string) config('services.ma_scraper.token');
        if ($token === '' || ! hash_equals($token, (string) $request->header('X-API-Key'))) {
            return response()->json(['success' => false, 'message' => 'API key tidak valid.'], 401);
        }

        $data = $request->validate([
            'url' => ['required', 'string', 'url:https', 'max:255'],
            'name' => ['nullable', 'string', 'max:64'],
        ]);
        $url = rtrim($data['url'], '/');
        $name = $data['name'] ?? 'default';

        // Scraper yang baru start langsung jadi aktif; yang lain tetap tersimpan sebagai cadangan (maks 5 terbaru).
        $registry = (array) Cache::get(LegalDecisionPdfService::SCRAPER_REGISTRY_CACHE_KEY, []);
        unset($registry[$name]);
        $registry[$name] = $url;
        Cache::forever(LegalDecisionPdfService::SCRAPER_REGISTRY_CACHE_KEY, array_slice($registry, -5, preserve_keys: true));
        Cache::forever(LegalDecisionPdfService::SCRAPER_URL_CACHE_KEY, $url);
        Log::info('ma-scraper.register', ['name' => $name, 'url' => $url, 'ip' => $request->ip()]);

        return response()->json(['success' => true, 'name' => $name, 'url' => $url]);
    }
}
