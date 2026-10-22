@if (config('services.turnstile.site_key'))
    <div>
        <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}"></div>
        @error('cf-turnstile-response')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
    </div>
    @once
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endonce
@endif
