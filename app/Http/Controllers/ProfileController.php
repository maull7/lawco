<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileRequest;
use App\Models\Package;
use App\Models\UserPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        $packages = Package::where('is_active', true)->orderBy('sort')->orderBy('id')->get();

        $activeUserPackage = request()->user()->userPackages()
            ->with('package')
            ->whereIn('status', ['active', 'pending'])
            ->latest()
            ->first();

        return view('profile.edit', compact('packages', 'activeUserPackage'));
    }

    public function update(ProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()->route('profile.edit')
            ->with('success', 'Data pribadi berhasil diperbarui.');
    }

    /**
     * Start a package purchase. Users with an incomplete profile are sent back
     * to the profile form by the profile.complete middleware first.
     */
    public function selectPackage(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'package_id' => ['required', 'integer'],
        ]);

        $package = Package::where('is_active', true)->findOrFail($validated['package_id']);

        $latest = $request->user()->userPackages()
            ->with('package')
            ->latest()
            ->first();

        if ($latest && $latest->package_id === $package->id) {
            return redirect()->route('profile.edit')
                ->with('info', "Anda sudah menggunakan paket {$package->name}.");
        }

        if ($latest && ! $this->isUpgrade($latest->package->price, $package->price)) {
            return redirect()->route('profile.edit')
                ->with('error', "Tidak dapat downgrade ke <strong>{$package->name}</strong>. Saat ini Anda menggunakan paket {$latest->package->name}.");
        }

        $userPackage = UserPackage::create([
            'user_id' => $request->user()->id,
            'package_id' => $package->id,
            'type' => $package->isTrial() ? 'trial' : 'paid',
            'status' => $package->isTrial() ? 'active' : 'pending',
            'trial_ends_at' => $package->isTrial() ? Carbon::now()->addMonth() : null,
        ]);

        if ($userPackage->status === 'pending') {
            return redirect()->route('packages.payment', $userPackage)
                ->with('success', 'Silakan selesaikan pembayaran paket Anda.');
        }

        return redirect()->route('dashboard')
            ->with('success', "Paket {$package->name} aktif. Selamat menikmati layanan!");
    }

    private function isUpgrade(string $currentPrice, string $newPrice): bool
    {
        $currentNumeric = $this->priceToFloat($currentPrice);
        $newNumeric = $this->priceToFloat($newPrice);

        return $newNumeric >= $currentNumeric;
    }

    private function priceToFloat(string $price): float
    {
        $cleaned = str_replace(',', '.', preg_replace('/[^\d,]/', '', $price));

        return $cleaned === '' ? PHP_FLOAT_MAX : (float) $cleaned;
    }
}
