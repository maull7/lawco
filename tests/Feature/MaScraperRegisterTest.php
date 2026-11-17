<?php

namespace Tests\Feature;

use App\Services\LegalDecisionPdfService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class MaScraperRegisterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ma_scraper.url' => 'https://fallback.test',
            'services.ma_scraper.token' => 'secret-token',
        ]);
    }

    public function test_scraper_registers_new_tunnel_url_and_service_uses_it(): void
    {
        $this->postJson('/api/ma-scraper/register', ['url' => 'https://abc-xyz.trycloudflare.com/'], ['X-API-Key' => 'secret-token'])
            ->assertOk()
            ->assertJsonPath('url', 'https://abc-xyz.trycloudflare.com');

        $this->assertSame('https://abc-xyz.trycloudflare.com', app(LegalDecisionPdfService::class)->scraperUrl());
    }

    public function test_service_falls_back_to_env_url_when_nothing_registered(): void
    {
        $this->assertSame('https://fallback.test', app(LegalDecisionPdfService::class)->scraperUrl());
    }

    public function test_wrong_or_missing_token_is_rejected(): void
    {
        $this->postJson('/api/ma-scraper/register', ['url' => 'https://evil.test'], ['X-API-Key' => 'wrong'])->assertUnauthorized();
        $this->postJson('/api/ma-scraper/register', ['url' => 'https://evil.test'])->assertUnauthorized();

        $this->assertNull(Cache::get(LegalDecisionPdfService::SCRAPER_URL_CACHE_KEY));
    }

    public function test_rejected_when_laravel_token_is_empty(): void
    {
        config(['services.ma_scraper.token' => '']);

        $this->postJson('/api/ma-scraper/register', ['url' => 'https://evil.test'], ['X-API-Key' => ''])->assertUnauthorized();
    }

    public function test_non_https_url_is_rejected(): void
    {
        $this->postJson('/api/ma-scraper/register', ['url' => 'http://abc.trycloudflare.com'], ['X-API-Key' => 'secret-token'])
            ->assertUnprocessable();

        $this->postJson('/api/ma-scraper/register', ['url' => 'bukan-url'], ['X-API-Key' => 'secret-token'])
            ->assertUnprocessable();
    }

    public function test_each_device_is_kept_as_backup_and_latest_becomes_active(): void
    {
        $this->register('laptop-a', 'https://a.trycloudflare.com');
        $this->register('laptop-b', 'https://b.trycloudflare.com');
        $this->register('laptop-a', 'https://a2.trycloudflare.com');

        $this->assertSame(['laptop-b' => 'https://b.trycloudflare.com', 'laptop-a' => 'https://a2.trycloudflare.com'], Cache::get(LegalDecisionPdfService::SCRAPER_REGISTRY_CACHE_KEY));
        $this->assertSame('https://a2.trycloudflare.com', app(LegalDecisionPdfService::class)->scraperUrl());
    }

    public function test_fetch_switches_to_healthy_backup_when_active_scraper_is_offline(): void
    {
        Sleep::fake();
        $this->register('laptop-a', 'https://a.trycloudflare.com');
        $this->register('laptop-b', 'https://b.trycloudflare.com');
        Http::fake([
            'b.trycloudflare.com/*' => fn () => throw new ConnectionException('Could not resolve host'),
            'a.trycloudflare.com/health' => Http::response(['ok' => true]),
            'a.trycloudflare.com/pdf*' => Http::response('%PDF-1.4 '.str_repeat('x', 600)),
        ]);

        $result = app(LegalDecisionPdfService::class)->fetch('https://putusan3.mahkamahagung.go.id/direktori/putusan/x.html');

        $this->assertNull($result['error']);
        $this->assertStringStartsWith('%PDF', $result['content']);
        $this->assertSame('https://a.trycloudflare.com', app(LegalDecisionPdfService::class)->scraperUrl());
    }

    public function test_cloudflare_530_also_triggers_failover(): void
    {
        $this->register('laptop-a', 'https://a.trycloudflare.com');
        $this->register('laptop-b', 'https://b.trycloudflare.com');
        Http::fake([
            'b.trycloudflare.com/*' => Http::response('origin unreachable', 530),
            'a.trycloudflare.com/health' => Http::response(['ok' => true]),
            'a.trycloudflare.com/pdf*' => Http::response('%PDF-1.4 '.str_repeat('x', 600)),
        ]);

        $result = app(LegalDecisionPdfService::class)->fetch('https://putusan3.mahkamahagung.go.id/direktori/putusan/x.html');

        $this->assertNull($result['error']);
        $this->assertSame('https://a.trycloudflare.com', app(LegalDecisionPdfService::class)->scraperUrl());
    }

    public function test_fetch_reports_offline_when_no_backup_is_healthy(): void
    {
        Sleep::fake();
        $this->register('laptop-a', 'https://a.trycloudflare.com');
        $this->register('laptop-b', 'https://b.trycloudflare.com');
        Http::fake(['*' => fn () => throw new ConnectionException('Could not resolve host')]);

        $result = app(LegalDecisionPdfService::class)->fetch('https://putusan3.mahkamahagung.go.id/direktori/putusan/x.html');

        $this->assertSame(LegalDecisionPdfService::SCRAPER_OFFLINE_ERROR, $result['error']);
        $this->assertSame('https://b.trycloudflare.com', app(LegalDecisionPdfService::class)->scraperUrl());
    }

    private function register(string $name, string $url): void
    {
        $this->postJson('/api/ma-scraper/register', ['name' => $name, 'url' => $url], ['X-API-Key' => 'secret-token'])->assertOk();
    }
}
