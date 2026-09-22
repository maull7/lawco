@extends('layouts.app')

@section('title', 'Konsultasi Hukum')
@section('header', 'Konsultasi Hukum')
@section('meta_description',
    'Ajukan konsultasi hukum dengan mengisi informasi permasalahan hukum yang sedang Anda alami.
    Tim kami akan menghubungi Anda kembali.')
@section('robots', 'noindex, follow')

@section('content')
    <div class="max-w-3xl">
        <p class="text-xs font-semibold tracking-[0.16em] uppercase text-[#c99a3e]">Layanan</p>
        <h2 class="mt-2 text-3xl font-bold text-[#071833] tracking-tight">Konsultasi Hukum</h2>
        <p class="mt-1.5 text-sm text-[#667085]">Isi informasi permasalahan hukum yang sedang Anda alami. Kami akan
            menghubungi Anda kembali. Terima kasih.</p>
    </div>

    <x-card class="mt-6 max-w-3xl">
        <form method="POST" action="{{ route('legal-necessities.store') }}" class="space-y-5">
            @csrf

            <div>
                <label for="name" class="block text-sm font-semibold text-[#071833] mb-1.5">Nama Lengkap</label>
                <input id="name" type="text" name="name" value="{{ Auth::user()->name }}" required
                    class="input-premium" placeholder="Nama Anda">
                @error('name')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="email" class="block text-sm font-semibold text-[#071833] mb-1.5">Email</label>
                    <input id="email" type="email" name="email" value="{{ Auth::user()->email }}" required
                        class="input-premium" placeholder="nama@email.com">
                    @error('email')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="phone" class="block text-sm font-semibold text-[#071833] mb-1.5">Nomor HP /
                        WhatsApp</label>
                    <input id="phone" type="tel" name="phone" value="{{ Auth::user()->phone }}" required
                        class="input-premium" placeholder="+62...">
                    @error('phone')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="message" class="block text-sm font-semibold text-[#071833] mb-1.5">Informasi Permasalahan
                    Hukum</label>
                <textarea id="message" name="message" rows="4" required class="input-premium"
                    placeholder="Ceritakan permasalahan hukum yang sedang Anda alami...">{{ old('message') }}</textarea>
                @error('message')
                    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <x-button type="submit" variant="primary" size="lg">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                </svg>
                Kirim Permasalahan Hukum
            </x-button>
        </form>
    </x-card>
@endsection
