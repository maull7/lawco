<?php

namespace Tests\Feature;

use App\Jobs\SyncJdihRegulations;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ScrapingFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_sub_admin_can_view_only_jdih_sync_failures(): void
    {
        $this->createFailure('JDIH sync (komdigi, 2 documents)', 'PDF gagal disalin');
        $this->createFailure('OtherJob', 'Error antrean lain', 'default');
        $this->createFailure('OtherJob', 'Error pekerjaan lain', 'jdih', 'App\\Jobs\\OtherJob');

        foreach (['admin', 'sub_admin'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('scraping-failures.index'))
                ->assertOk()
                ->assertSee('Riwayat Scraping Gagal')
                ->assertSee('JDIH sync (komdigi, 2 documents)')
                ->assertSee('PDF gagal disalin')
                ->assertDontSee('Error antrean lain')
                ->assertDontSee('Error pekerjaan lain');
        }
    }

    public function test_regular_user_is_forbidden_and_guest_is_redirected(): void
    {
        $this->get(route('scraping-failures.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get(route('scraping-failures.index'))->assertForbidden();
    }

    public function test_empty_history_is_displayed(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('scraping-failures.index'))
            ->assertOk()
            ->assertSee('Belum ada riwayat sinkronisasi scraping yang gagal.');
    }

    public function test_history_is_paginated_newest_first_and_escapes_errors(): void
    {
        for ($index = 0; $index < 21; $index++) {
            $this->createFailure('Batch '.$index, 'Error '.$index);
        }
        DB::table('failed_jobs')->where('id', 21)->update(['exception' => '<script>alert(1)</script>']);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('scraping-failures.index'))
            ->assertOk()
            ->assertSeeInOrder(['Batch 20', 'Batch 19'])
            ->assertDontSee('Batch 0')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
        $this->get(route('scraping-failures.index', ['page' => 2]))
            ->assertOk()->assertSee('Batch 0')->assertDontSee('Batch 20');
    }

    public function test_malformed_payload_has_a_safe_fallback(): void
    {
        $this->createFailure('Batch', 'Error');
        DB::table('failed_jobs')->update(['payload' => 'broken SyncJdihRegulations payload']);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('scraping-failures.index'))->assertOk()->assertSee('Sinkronisasi JDIH');
    }

    public function test_admin_and_sub_admin_can_retry_the_original_job_once(): void
    {
        $queue = Mockery::mock(\Illuminate\Contracts\Queue\Queue::class);
        $queue->shouldReceive('pushRaw')->twice()->withArgs(function (string $raw, string $queueName, array $options): bool {
            $payload = json_decode($raw, true);
            $job = unserialize($payload['data']['command'], ['allowed_classes' => [SyncJdihRegulations::class]]);

            return $queueName === 'jdih' && $payload['attempts'] === 0
                && $payload['retryUntil'] > now()->timestamp
                && $job->source === 'komdigi' && $job->documentIds === ['doc-1'];
        })->andReturn('queued-id');
        Queue::shouldReceive('connection')->with('redis')->andReturn($queue);
        foreach (['admin', 'sub_admin'] as $role) {
            $this->createFailure('JDIH sync', 'Error');
            $uuid = DB::table('failed_jobs')->value('uuid');

            $this->actingAs(User::factory()->create(['role' => $role]))
                ->post(route('scraping-failures.retry', $uuid))
                ->assertRedirect(route('scraping-failures.index'))->assertSessionHas('success');
            $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
            $this->post(route('scraping-failures.retry', $uuid))->assertSessionHas('error');
        }
    }

    public function test_retry_failure_keeps_the_history(): void
    {
        $this->createFailure('JDIH sync', 'Error');
        $uuid = DB::table('failed_jobs')->value('uuid');
        Queue::shouldReceive('connection')->with('redis')->andThrow(new RuntimeException('Queue unavailable'));

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('scraping-failures.retry', $uuid))->assertSessionHas('error');
        $this->assertDatabaseHas('failed_jobs', ['uuid' => $uuid]);
    }

    public function test_retry_rejects_other_jobs_invalid_payload_and_unauthorized_users(): void
    {
        $this->createFailure('Other', 'Error', 'jdih', 'App\\Jobs\\OtherJob');
        $uuid = DB::table('failed_jobs')->value('uuid');
        Queue::shouldReceive('connection')->never();
        $this->post(route('scraping-failures.retry', $uuid))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->post(route('scraping-failures.retry', $uuid))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('scraping-failures.retry', $uuid))->assertSessionHas('error');
        DB::table('failed_jobs')->update(['payload' => 'invalid']);
        $this->post(route('scraping-failures.retry', $uuid))->assertSessionHas('error');
        $this->assertDatabaseHas('failed_jobs', ['uuid' => $uuid]);
    }

    private function createFailure(string $name, string $error, string $queue = 'jdih', string $jobClass = SyncJdihRegulations::class): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'redis',
            'queue' => $queue,
            'payload' => json_encode(['displayName' => $name, 'data' => ['commandName' => $jobClass, 'command' => serialize($jobClass === SyncJdihRegulations::class ? new SyncJdihRegulations('komdigi', 0, ['doc-1']) : new \stdClass)], 'attempts' => 3, 'retryUntil' => 1]),
            'exception' => $error,
            'failed_at' => '2026-10-07 10:00:00',
        ]);
    }
}
