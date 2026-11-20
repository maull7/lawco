<?php

namespace App\Services;

use App\Models\Setting;
use RuntimeException;

class AiRuntimeControl
{
    public function isHalted(): bool
    {
        return Setting::get('ai_halted', '0') === '1';
    }

    public function setHalted(bool $halted): void
    {
        Setting::updateOrCreate(['key' => 'ai_halted'], ['value' => $halted ? '1' : '0']);
    }

    public function ensureActive(): void
    {
        if ($this->isHalted()) {
            throw new RuntimeException('AI sedang dinonaktifkan oleh admin. Silakan coba kembali setelah AI diaktifkan.');
        }
    }
}
