<?php

namespace App\Http\Controllers;

use App\Models\UserActivityLog;
use App\Models\UserPackage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PackagePaymentController extends Controller
{
    public function show(UserPackage $userPackage): View
    {
        abort_if($userPackage->user_id !== auth()->id(), 403);

        return view('packages.payment', compact('userPackage'));
    }

    public function submitProof(Request $request, UserPackage $userPackage): RedirectResponse
    {
        abort_if($userPackage->user_id !== auth()->id(), 403);
        abort_if($userPackage->status === 'active', 403);

        $validated = $request->validate([
            'payment_proof' => ['required', 'image', 'max:5120'],
        ]);

        if ($userPackage->payment_proof) {
            Storage::disk('public')->delete($userPackage->payment_proof);
        }

        $proof = $request->file('payment_proof')->store('payment-proofs', 'public');

        $userPackage->update(['payment_proof' => $proof, 'status' => 'pending']);

        return back()->with('success', 'Bukti pembayaran terkirim. Menunggu konfirmasi admin.');
    }

    public function confirmations(Request $request): View
    {
        $tab = $request->query('tab', 'pending');

        $query = UserPackage::with(['user', 'package'])->orderByDesc('updated_at');

        $payments = match ($tab) {
            'confirmed' => (clone $query)->whereNotNull('confirmed_at')->get(),
            'history' => (clone $query)->get(),
            default => (clone $query)->where('status', 'pending')->get(),
        };

        return view('packages.confirmations.index', compact('payments', 'tab'));
    }

    public function confirm(UserPackage $userPackage): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        if ($userPackage->status === 'pending') {
            $userPackage->update(['status' => 'active', 'confirmed_at' => now()]);
            UserActivityLog::log('updated', UserPackage::class, $userPackage->id, "Mengkonfirmasi pembayaran paket {$userPackage->package->name} oleh {$userPackage->user->email}");
        }

        return redirect()->route('packages.payment.confirmations')
            ->with('success', "Pembayaran {$userPackage->user->name} dikonfirmasi. Paket aktif.");
    }
}
