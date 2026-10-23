<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class UserPackage extends Model
{
    protected $fillable = ['user_id', 'package_id', 'type', 'status', 'payment_proof', 'confirmed_at', 'trial_ends_at', 'kak_vesta_started_at'];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'kak_vesta_started_at' => 'datetime',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function startsAt(): ?Carbon
    {
        if ($this->status !== 'active') {
            return null;
        }

        if ($this->type === 'trial') {
            return $this->confirmed_at ?? $this->created_at;
        }

        $runningTrial = UserPackage::query()
            ->where('user_id', $this->user_id)
            ->where('type', 'trial')
            ->where('status', 'active')
            ->where('id', '<', $this->id)
            ->where('trial_ends_at', '>', $this->created_at)
            ->latest('id')
            ->first();

        return $runningTrial
            ? $runningTrial->trial_ends_at->copy()->addDay()
            : $this->confirmed_at;
    }

    public function endsAt(): ?Carbon
    {
        return $this->type === 'trial'
            ? $this->trial_ends_at
            : $this->startsAt()?->copy()->addMonths($this->durationMonths());
    }

    /**
     * Package validity in months, read from the package period label
     * (e.g. "/bulan" = 1, "/ 6 Bulan" = 6, "/ 2 Tahun" = 24).
     */
    public function durationMonths(): int
    {
        if (! preg_match('/(\d+)?\s*(tahun|bulan)/i', (string) $this->package?->price_period, $matches)) {
            return 1;
        }

        $count = max(1, (int) $matches[1]);

        return strtolower($matches[2]) === 'tahun' ? $count * 12 : $count;
    }

    /**
     * Pending paid package whose payment proof has not been uploaded yet.
     */
    public function awaitingPayment(): bool
    {
        return $this->status === 'pending' && $this->type === 'paid' && ! $this->payment_proof;
    }

    private function pendingLabel(): string
    {
        return $this->awaitingPayment() ? 'Menunggu Pembayaran' : 'Menunggu Konfirmasi';
    }

    public function startDateDisplay(): string
    {
        if ($this->status === 'pending') {
            return $this->pendingLabel();
        }

        return $this->startsAt()?->copy()->timezone('Asia/Jakarta')->format('d M Y') ?? '-';
    }

    public function endDateDisplay(): string
    {
        if ($this->status === 'pending') {
            return $this->pendingLabel();
        }

        return $this->endsAt()?->copy()->timezone('Asia/Jakarta')->format('d M Y') ?? '-';
    }
}
