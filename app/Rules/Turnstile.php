<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Translation\PotentiallyTranslatedString;
use Throwable;

/**
 * Verifies a Cloudflare Turnstile token. Passes when no secret key is configured
 * (local/testing), so the widget only enforces where TURNSTILE_SECRET_KEY is set.
 */
class Turnstile implements ValidationRule
{
    /** Run even when the token field is missing from the request. */
    public bool $implicit = true;

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! config('services.turnstile.secret_key')) {
            return;
        }

        try {
            $passed = is_string($value) && $value !== '' && Http::asForm()->timeout(10)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $value,
                    'remoteip' => request()->ip(),
                ])->json('success') === true;
        } catch (Throwable $exception) {
            report($exception);
            $passed = false;
        }

        if (! $passed) {
            $fail('Verifikasi captcha gagal. Silakan coba lagi.');
        }
    }
}
