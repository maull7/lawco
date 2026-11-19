<?php

namespace Tests\Feature;

use App\Helpers\DocumentStorage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageR2Test extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_storage_r2_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('storage-r2.index'))
            ->assertOk()
            ->assertSee('Storage R2 (Cloudflare)');
    }

    public function test_non_admin_cannot_access_storage_r2(): void
    {
        $user = User::factory()->create(['role' => 'sub_admin']);

        $this->actingAs($user)->get(route('storage-r2.index'))->assertForbidden();
        $this->actingAs($user)->postJson(route('storage-r2.run'), ['action' => 'stats'])->assertForbidden();
    }

    public function test_prune_is_rejected_while_documents_still_use_local_disk(): void
    {
        config(['filesystems.pdf_disk' => 'public']);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('storage-r2.run'), ['action' => 'prune'])
            ->assertStatus(422);
    }

    public function test_unknown_action_is_rejected(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->postJson(route('storage-r2.run'), ['action' => 'rm -rf'])
            ->assertStatus(422);
    }

    public function test_migrate_copies_new_files_and_skips_existing(): void
    {
        Storage::fake('public');
        Storage::fake(DocumentStorage::r2DiskName());
        Storage::disk('public')->put('regulations/a.pdf', 'aaa');
        Storage::disk('public')->put('review-documents/b.pdf', 'bbb');
        Storage::disk(DocumentStorage::r2DiskName())->put('review-documents/b.pdf', 'bbb');

        $this->artisan('documents:migrate-to-r2')
            ->expectsOutputToContain('copied=1 skipped=1 failed=0')
            ->assertSuccessful();

        Storage::disk(DocumentStorage::r2DiskName())->assertExists('regulations/a.pdf');
        Storage::disk('public')->assertExists('regulations/a.pdf');
    }

    public function test_migrate_stops_when_daily_quota_is_reached(): void
    {
        config(['filesystems.r2_daily_copy_limit_gb' => 5 / 1024 ** 3]); // 5 bytes
        Storage::fake('public');
        Storage::fake(DocumentStorage::r2DiskName());
        Storage::disk('public')->put('regulations/a.pdf', 'aaaa');
        Storage::disk('public')->put('regulations/b.pdf', 'bbbb');

        $this->artisan('documents:migrate-to-r2')
            ->expectsOutputToContain('copied=1')
            ->assertSuccessful();
        $this->assertCount(1, Storage::disk(DocumentStorage::r2DiskName())->allFiles('regulations'));

        $this->artisan('documents:migrate-to-r2')->expectsOutputToContain('copied=0')->assertSuccessful();

        $this->travel(1)->days();
        $this->artisan('documents:migrate-to-r2')->expectsOutputToContain('copied=1')->assertSuccessful();
        $this->assertCount(2, Storage::disk(DocumentStorage::r2DiskName())->allFiles('regulations'));
    }

    public function test_prune_deletes_only_files_already_on_r2_with_same_size(): void
    {
        config(['filesystems.pdf_disk' => DocumentStorage::r2DiskName()]);
        Storage::fake('public');
        Storage::fake(DocumentStorage::r2DiskName());
        Storage::disk('public')->put('regulations/same.pdf', 'same');
        Storage::disk(DocumentStorage::r2DiskName())->put('regulations/same.pdf', 'same');
        Storage::disk('public')->put('regulations/diff.pdf', 'local');
        Storage::disk(DocumentStorage::r2DiskName())->put('regulations/diff.pdf', 'x');
        Storage::disk('public')->put('regulations/only-local.pdf', 'x');

        $this->artisan('documents:migrate-to-r2 --prune')->assertSuccessful();

        Storage::disk('public')->assertMissing('regulations/same.pdf');
        Storage::disk('public')->assertExists('regulations/diff.pdf');
        Storage::disk('public')->assertExists('regulations/only-local.pdf');
    }

    public function test_prune_command_refuses_while_pdf_disk_is_public(): void
    {
        config(['filesystems.pdf_disk' => 'public']);
        Storage::fake('public');
        Storage::fake(DocumentStorage::r2DiskName());
        Storage::disk('public')->put('regulations/a.pdf', 'a');
        Storage::disk(DocumentStorage::r2DiskName())->put('regulations/a.pdf', 'a');

        $this->artisan('documents:migrate-to-r2 --prune')->assertFailed();

        Storage::disk('public')->assertExists('regulations/a.pdf');
    }
}
