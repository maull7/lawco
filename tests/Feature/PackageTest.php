<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PackageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_can_create_package(): void
    {
        $this->actingAs($this->admin())
            ->post(route('packages.store'), [
                'name' => 'Dasar',
                'price' => '5jt',
                'price_period' => '/bulan',
                'tagline' => 'Untuk individu',
                'benefits' => "Legal check regulasi dasar\nReview 1 dokumen per bulan",
                'is_popular' => '1',
                'is_active' => '1',
            ])->assertRedirect(route('packages.index'));

        $this->assertDatabaseHas('packages', [
            'name' => 'Dasar',
            'price' => '5jt',
            'is_popular' => true,
        ]);

        $package = Package::where('name', 'Dasar')->firstOrFail();
        $this->assertSame(['Legal check regulasi dasar', 'Review 1 dokumen per bulan'], $package->benefits);
    }

    public function test_admin_can_update_package(): void
    {
        $package = Package::create([
            'name' => 'Dasar',
            'price' => '5jt',
            'tagline' => 'Lama',
            'benefits' => ['Benefit lama'],
        ]);

        $this->actingAs($this->admin())
            ->put(route('packages.update', $package), [
                'name' => 'Dasar Pro',
                'price' => '7jt',
                'price_period' => '/bulan',
                'tagline' => 'Baru',
                'benefits' => "Benefit baru\nBenefit kedua",
            ])->assertRedirect(route('packages.index'));

        $this->assertDatabaseHas('packages', ['name' => 'Dasar Pro', 'price' => '7jt']);
        $this->assertSame(['Benefit baru', 'Benefit kedua'], $package->fresh()->benefits);
    }

    public function test_admin_can_delete_package(): void
    {
        $package = Package::create(['name' => 'Enterprise', 'price' => 'Custom', 'benefits' => []]);

        $this->actingAs($this->admin())
            ->delete(route('packages.destroy', $package))
            ->assertRedirect(route('packages.index'));

        $this->assertDatabaseMissing('packages', ['id' => $package->id]);
    }

    public function test_non_admin_cannot_manage_packages(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->get(route('packages.index'))
            ->assertForbidden();
    }

    public function test_landing_page_shows_active_packages(): void
    {
        Package::create([
            'name' => 'Bisnis',
            'price' => '12,5jt',
            'price_period' => '/bulan',
            'tagline' => 'Perusahaan',
            'benefits' => ['Review 5 dokumen', 'Konsultasi telepon'],
            'is_popular' => true,
            'is_active' => true,
        ]);
        Package::create([
            'name' => 'Paket Sembunyi',
            'price' => '1jt',
            'benefits' => [],
            'is_active' => false,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Bisnis')
            ->assertSee('Review 5 dokumen')
            ->assertDontSee('Paket Sembunyi');
    }

    public function test_choosing_trial_package_activates_directly(): void
    {
        $trial = Package::create([
            'name' => 'Free Trial 1 Bulan',
            'price' => '0',
            'price_period' => '/bulan',
            'benefits' => ['Legal check dasar'],
        ]);

        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->post(route('profile.package'), [
            'package_id' => $trial->id,
        ])->assertRedirect(route('dashboard'));

        $userPackage = UserPackage::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('trial', $userPackage->type);
        $this->assertSame('active', $userPackage->status);
        $this->assertNotNull($userPackage->trial_ends_at);
    }

    public function test_choosing_paid_package_goes_to_qris_payment(): void
    {
        $paid = Package::create([
            'name' => 'Bisnis',
            'price' => '12,5jt',
            'price_period' => '/bulan',
            'benefits' => ['Review 5 dokumen'],
        ]);

        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->post(route('profile.package'), [
            'package_id' => $paid->id,
        ])->assertRedirect(route('packages.payment', UserPackage::where('user_id', $user->id)->first()));

        $userPackage = UserPackage::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('paid', $userPackage->type);
        $this->assertSame('pending', $userPackage->status);
        $this->assertNull($userPackage->trial_ends_at);

        $this->get(route('packages.payment', $userPackage))
            ->assertOk()
            ->assertSee('qris/qris.png');
    }

    public function test_user_uploads_payment_proof(): void
    {
        Storage::fake('public');

        $paid = Package::create(['name' => 'Bisnis', 'price' => '12,5jt', 'benefits' => []]);
        $user = User::factory()->create(['role' => 'user']);
        $userPackage = UserPackage::create([
            'user_id' => $user->id,
            'package_id' => $paid->id,
            'type' => 'paid',
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->post(route('packages.payment.submit', $userPackage), [
                'payment_proof' => UploadedFile::fake()->image('bukti.jpg'),
            ])->assertRedirect();

        $fresh = $userPackage->fresh();
        $this->assertSame('pending', $fresh->status);
        $this->assertNotNull($fresh->payment_proof);
        Storage::disk('public')->assertExists($fresh->payment_proof);

        $this->get(route('packages.payment', $userPackage))
            ->assertOk()
            ->assertSee('Menunggu konfirmasi admin');
    }

    public function test_admin_confirm_activates_package(): void
    {
        $paid = Package::create(['name' => 'Bisnis', 'price' => '12,5jt', 'benefits' => []]);
        $user = User::factory()->create(['role' => 'user']);
        $userPackage = UserPackage::create([
            'user_id' => $user->id,
            'package_id' => $paid->id,
            'type' => 'paid',
            'status' => 'pending',
            'payment_proof' => 'payment-proofs/bukti.jpg',
        ]);

        $this->actingAs($this->admin())
            ->post(route('packages.payment.confirm', $userPackage))
            ->assertRedirect(route('packages.payment.confirmations'));

        $fresh = $userPackage->fresh();
        $this->assertSame('active', $fresh->status);
        $this->assertNotNull($fresh->confirmed_at);

        $this->actingAs($this->admin())
            ->get(route('packages.payment.confirmations'))
            ->assertOk()
            ->assertSee('Tidak ada pembayaran menunggu konfirmasi');
    }

    public function test_regular_user_cannot_confirm_payment(): void
    {
        $paid = Package::create(['name' => 'Bisnis', 'price' => '12,5jt', 'benefits' => []]);
        $user = User::factory()->create(['role' => 'user']);
        $userPackage = UserPackage::create([
            'user_id' => $user->id,
            'package_id' => $paid->id,
            'type' => 'paid',
            'status' => 'pending',
            'payment_proof' => 'payment-proofs/bukti.jpg',
        ]);

        $this->actingAs($user)
            ->post(route('packages.payment.confirm', $userPackage))
            ->assertForbidden();

        $this->assertSame('pending', $userPackage->fresh()->status);
    }

    public function test_user_cannot_view_others_payment_page(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user']);
        $paid = Package::create(['name' => 'Bisnis', 'price' => '12,5jt', 'benefits' => []]);
        $userPackage = UserPackage::create([
            'user_id' => $owner->id,
            'package_id' => $paid->id,
            'type' => 'paid',
            'status' => 'pending',
        ]);

        $this->actingAs($other)
            ->get(route('packages.payment', $userPackage))
            ->assertForbidden();
    }

    public function test_user_can_upgrade_from_trial_to_paid(): void
    {
        $trial = Package::create(['name' => 'Free Trial', 'price' => '0', 'benefits' => ['Dasar']]);
        $paid = Package::create(['name' => 'Bisnis', 'price' => '12,5jt', 'benefits' => ['Pro']]);

        $user = User::factory()->create(['role' => 'user']);
        UserPackage::create(['user_id' => $user->id, 'package_id' => $trial->id, 'type' => 'trial', 'status' => 'active']);

        $this->actingAs($user)->post(route('profile.package'), [
            'package_id' => $paid->id,
        ])->assertRedirect(route('packages.payment', UserPackage::where('user_id', $user->id)->latest('id')->first()));

        $this->assertSame(2, UserPackage::where('user_id', $user->id)->count());
    }

    public function test_user_cannot_downgrade_package(): void
    {
        $paid = Package::create(['name' => 'Bisnis', 'price' => '12,5jt', 'benefits' => ['Pro']]);
        $basic = Package::create(['name' => 'Dasar', 'price' => '5jt', 'benefits' => ['Ringan']]);

        $user = User::factory()->create(['role' => 'user']);
        UserPackage::create(['user_id' => $user->id, 'package_id' => $paid->id, 'type' => 'paid', 'status' => 'active']);

        $this->actingAs($user)->post(route('profile.package'), [
            'package_id' => $basic->id,
        ])->assertSessionHas('error');

        $this->assertSame(1, UserPackage::where('user_id', $user->id)->count());
    }

    public function test_same_package_selection_gives_info(): void
    {
        $trial = Package::create(['name' => 'Free Trial', 'price' => '0', 'benefits' => ['Dasar']]);

        $user = User::factory()->create(['role' => 'user']);
        UserPackage::create(['user_id' => $user->id, 'package_id' => $trial->id, 'type' => 'trial', 'status' => 'active']);

        $this->actingAs($user)->post(route('profile.package'), [
            'package_id' => $trial->id,
        ])->assertSessionHas('info');

        $this->assertSame(1, UserPackage::where('user_id', $user->id)->count());
    }

    public function test_cannot_downgrade_from_paid_to_trial(): void
    {
        $paid = Package::create(['name' => 'Bisnis', 'price' => '12,5jt', 'benefits' => ['Pro']]);
        $trial = Package::create(['name' => 'Free Trial', 'price' => '0', 'benefits' => ['Dasar']]);

        $user = User::factory()->create(['role' => 'user']);
        UserPackage::create(['user_id' => $user->id, 'package_id' => $paid->id, 'type' => 'paid', 'status' => 'active']);

        $this->actingAs($user)->post(route('profile.package'), [
            'package_id' => $trial->id,
        ])->assertSessionHas('error');

        $this->assertSame(1, UserPackage::where('user_id', $user->id)->count());
    }

    public function test_can_upgrade_to_higher_paid_package(): void
    {
        $basic = Package::create(['name' => 'Dasar', 'price' => '5jt', 'benefits' => ['Ringan']]);
        $premium = Package::create(['name' => 'Enterprise', 'price' => '25jt', 'benefits' => ['Full']]);

        $user = User::factory()->create(['role' => 'user']);
        UserPackage::create(['user_id' => $user->id, 'package_id' => $basic->id, 'type' => 'paid', 'status' => 'active']);

        $this->actingAs($user)->post(route('profile.package'), [
            'package_id' => $premium->id,
        ])->assertRedirect(route('packages.payment', UserPackage::where('user_id', $user->id)->latest('id')->first()));

        $this->assertSame(2, UserPackage::where('user_id', $user->id)->count());
    }

    public function test_incomplete_profile_must_be_completed_before_choosing_package(): void
    {
        $paid = Package::create(['name' => 'Bisnis', 'price' => '12,5jt', 'benefits' => ['Pro']]);
        $user = User::factory()->create(['role' => 'user', 'institution' => null]);

        $this->actingAs($user)->post(route('profile.package'), ['package_id' => $paid->id])
            ->assertRedirect(route('profile.edit'));

        $this->assertSame(0, UserPackage::where('user_id', $user->id)->count());
    }

    public function test_saving_profile_does_not_touch_packages(): void
    {
        $paid = Package::create(['name' => 'Bisnis', 'price' => '12,5jt', 'benefits' => ['Pro']]);
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->post(route('profile.update'), [
            'institution' => 'PT Contoh', 'position' => 'Compliance',
            'province' => config('provinces')[0], 'phone' => '081234567890',
            'package_id' => $paid->id,
        ])->assertRedirect(route('profile.edit'))->assertSessionHas('success');

        $this->assertSame(0, UserPackage::where('user_id', $user->id)->count());
    }

    public function test_profile_page_has_separate_package_forms(): void
    {
        Package::create(['name' => 'Bisnis', 'price' => '12,5jt', 'benefits' => ['Pro']]);

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee(route('profile.package'))
            ->assertSee('Pilih Paket Ini')
            ->assertSee('Simpan Data Pribadi');
    }

    public function test_confirmation_tabs_filter_payments(): void
    {
        $paid = Package::create(['name' => 'Bisnis', 'price' => '12,5jt', 'benefits' => []]);
        $pendingUser = User::factory()->create(['role' => 'user', 'name' => 'User Menunggu']);
        $confirmedUser = User::factory()->create(['role' => 'user', 'name' => 'User Lunas']);
        UserPackage::create(['user_id' => $pendingUser->id, 'package_id' => $paid->id, 'type' => 'paid', 'status' => 'pending']);
        UserPackage::create(['user_id' => $confirmedUser->id, 'package_id' => $paid->id, 'type' => 'paid', 'status' => 'active', 'confirmed_at' => now()]);

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('packages.payment.confirmations'))
            ->assertOk()->assertSee('Terkonfirmasi')->assertSee('Riwayat')
            ->assertSee('User Menunggu')->assertDontSee('User Lunas');

        $this->actingAs($admin)->get(route('packages.payment.confirmations', ['tab' => 'confirmed']))
            ->assertOk()->assertSee('User Lunas')->assertDontSee('User Menunggu');

        $this->actingAs($admin)->get(route('packages.payment.confirmations', ['tab' => 'history']))
            ->assertOk()->assertSee('User Lunas')->assertSee('User Menunggu');
    }
}
