@extends('layouts.app')

@section('title', 'Lengkapi Data Pribadi')
@section('header', 'Lengkapi Data Pribadi')

@section('content')
    {{-- Banner --}}
    <section class="relative overflow-hidden rounded-[24px] bg-navy-gradient text-white p-7 sm:p-9">
        <div class="pointer-events-none absolute -top-24 -right-16 w-80 h-80 rounded-full bg-[#c99a3e]/20 blur-3xl"></div>

        <div class="relative">
            <span
                class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[10.5px] font-bold rounded-full bg-[#c99a3e]/20 ring-1 ring-[#c99a3e]/30 text-[#e6c06a] uppercase tracking-wider">
                <span class="w-1 h-1 rounded-full bg-[#e6c06a]"></span>
                Data Pribadi
            </span>
            <h2 class="mt-4 text-2xl sm:text-3xl font-bold tracking-tight">Lengkapi data pribadi Anda</h2>
            <p class="mt-2 text-white/70 text-sm max-w-xl">Institusi, jabatan, asal provinsi, dan nomor telepon dibutuhkan
                sebelum Anda dapat mengakses dashboard.</p>
        </div>
    </section>

    <form method="POST" action="{{ route('profile.update') }}">
        @csrf

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                @unless (auth()->user()->hasCompletedProfile())
                    <div class="mb-6 flex items-start gap-3 rounded-2xl bg-amber-50 ring-1 ring-amber-200 px-5 py-4">
                        <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                        <p class="text-sm font-semibold text-amber-800">Anda belum melengkapi data pribadi. Isi formulir di
                            bawah
                            untuk mengaktifkan akses penuh.</p>
                    </div>
                @endunless

                <x-card>
                    <x-slot name="header">
                        <h3 class="text-lg font-bold text-[#071833]">Form Data Pribadi</h3>
                    </x-slot>

                    <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label for="institution" class="block text-sm font-semibold text-[#071833] mb-2">Institusi
                                <span class="text-rose-500">*</span></label>
                            <input type="text" name="institution" id="institution"
                                value="{{ old('institution', auth()->user()->institution) }}" required class="input-premium"
                                placeholder="Nama kantor / institusi">
                            @error('institution')
                                <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="position" class="block text-sm font-semibold text-[#071833] mb-2">Jabatan
                                <span class="text-rose-500">*</span></label>
                            <input type="text" name="position" id="position"
                                value="{{ old('position', auth()->user()->position) }}" required class="input-premium"
                                placeholder="Contoh: Compliance Officer">
                            @error('position')
                                <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="province" class="block text-sm font-semibold text-[#071833] mb-2">Asal Provinsi
                                <span class="text-rose-500">*</span></label>
                            <select name="province" id="province" required class="input-premium">
                                <option value="">Pilih provinsi</option>
                                @foreach (config('provinces') as $province)
                                    <option value="{{ $province }}" @selected(old('province', auth()->user()->province) === $province)>{{ $province }}
                                    </option>
                                @endforeach
                            </select>
                            @error('province')
                                <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="phone" class="block text-sm font-semibold text-[#071833] mb-2">No. Telepon
                                <span class="text-rose-500">*</span></label>
                            <input type="tel" name="phone" id="phone"
                                value="{{ old('phone', auth()->user()->phone) }}" required class="input-premium"
                                placeholder="08xxxxxxxxxx">
                            @error('phone')
                                <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </x-card>
            </div>

            <div>
                <x-card>
                    <x-slot name="header">
                        <h3 class="text-base font-bold text-[#071833]">Kenapa perlu?</h3>
                    </x-slot>
                    <div class="space-y-4">
                        <div class="flex items-start gap-3">
                            <span
                                class="shrink-0 w-8 h-8 rounded-lg bg-[#f6f8fb] text-[#c99a3e] flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                            </span>
                            <p class="text-sm text-[#667085] leading-relaxed">Memungkinkan pendampingan hukum yang
                                disesuaikan
                                dengan profil kelembagaan Anda.</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <span
                                class="shrink-0 w-8 h-8 rounded-lg bg-[#f6f8fb] text-[#c99a3e] flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </span>
                            <p class="text-sm text-[#667085] leading-relaxed">Memverifikasi identitas untuk keamanan dan
                                kepercayaan dalam setiap transaksi.</p>
                        </div>
                    </div>
                </x-card>
            </div>
        </div>

        <x-card :padding="false" class="mt-6">
            <x-slot name="header">
                <div>
                    <h3 class="text-lg font-bold text-[#071833]">Paket &amp; Harga</h3>
                    <p class="text-xs text-[#667085] mt-0.5">Pilih salah satu paket pendampingan. Paket Free Trial aktif
                        langsung, paket berbayar dilanjutkan ke pembayaran QRIS.</p>
                </div>
            </x-slot>

            @if ($activeUserPackage)
                <div class="px-6 pt-6">
                    <div
                        class="flex items-center gap-3 p-4 rounded-2xl {{ $activeUserPackage->status === 'active' ? 'bg-emerald-50 ring-1 ring-emerald-200' : 'bg-amber-50 ring-1 ring-amber-200' }}">
                        <span
                            class="shrink-0 w-8 h-8 rounded-lg {{ $activeUserPackage->status === 'active' ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-600' }} flex items-center justify-center text-xs font-bold">
                            {{ $activeUserPackage->status === 'active' ? '✓' : '⏳' }}
                        </span>
                        <div class="min-w-0">
                            <p
                                class="text-sm font-semibold {{ $activeUserPackage->status === 'active' ? 'text-emerald-700' : 'text-amber-700' }}">
                                Paket saat ini: <strong>{{ $activeUserPackage->package->name }}</strong>
                            </p>
                            <p
                                class="text-xs {{ $activeUserPackage->status === 'active' ? 'text-emerald-700/70' : 'text-amber-700/70' }}">
                                Status: {{ $activeUserPackage->status === 'active' ? 'Aktif' : 'Menunggu pembayaran' }}
                                @if ($activeUserPackage->status === 'active')
                                    · Hanya dapat upgrade ke paket lebih tinggi
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 p-6">
                @forelse($packages as $package)
                    <label class="cursor-pointer block">
                        <input type="radio" name="package_id" value="{{ $package->id }}" @checked($activeUserPackage ? $activeUserPackage->package_id === $package->id : $package->isTrial())
                            class="peer sr-only">
                        <div
                            class="rounded-2xl border border-[#e7eaf0] bg-white p-5 flex flex-col h-full transition peer-checked:ring-2 peer-checked:ring-[#c99a3e] peer-checked:border-[#c99a3e] {{ $package->is_popular ? 'border-[#c99a3e]/50 bg-navy-gradient text-white' : '' }}">
                            @if ($package->is_popular)
                                <span
                                    class="self-start mb-2 bg-gradient-to-r from-[#c99a3e] to-[#b17c24] text-white text-[10px] font-bold uppercase px-2.5 py-1 rounded-full">Paling
                                    Populer</span>
                            @endif
                            <p class="text-sm font-bold {{ $package->is_popular ? 'text-white' : 'text-[#071833]' }}">
                                {{ $package->name }}
                                @if ($package->isTrial())
                                    <span
                                        class="ml-1 align-middle text-[9px] font-bold uppercase px-1.5 py-0.5 rounded-full bg-emerald-100 text-emerald-700">Free
                                        Trial</span>
                                @endif
                            </p>
                            <p class="text-[10px] {{ $package->is_popular ? 'text-white/70' : 'text-[#667085]' }} mt-0.5">
                                {{ $package->tagline }}</p>
                            <p
                                class="mt-4 text-3xl font-bold {{ $package->is_popular ? 'text-white' : 'text-[#071833]' }}">
                                @if ($package->isTrial())
                                    <span class="text-[#c99a3e]">Free</span>
                                @else
                                    Rp<span class="text-[#c99a3e]">{{ $package->price }}</span>
                                @endif
                                <span
                                    class="text-xs font-semibold {{ $package->is_popular ? 'text-white/70' : 'text-[#667085]' }}">{{ $package->price_period }}</span>
                            </p>
                            <ul
                                class="mt-4 space-y-1.5 text-xs {{ $package->is_popular ? 'text-white/80' : 'text-[#667085]' }} flex-1">
                                @foreach ($package->benefits ?? [] as $benefit)
                                    <li>{{ $benefit }}</li>
                                @endforeach
                            </ul>
                            <span
                                class="mt-4 inline-flex items-center justify-center rounded-lg py-2 text-xs font-bold ring-1 ring-[#c99a3e]/50 text-[#8c6a25]">
                                Pilih Paket Ini
                            </span>
                        </div>
                    </label>
                @empty
                    <div class="col-span-full text-center text-sm text-[#667085] py-6">
                        Belum ada paket tersedia. Silakan hubungi admin.
                    </div>
                @endforelse
            </div>

            <div
                class="px-6 pb-6 flex flex-col sm:flex-row gap-3 items-center justify-between border-t border-[#e7eaf0] pt-6">
                <p class="text-xs text-[#667085]">Paket berbayar akan lanjut ke pembayaran QRIS setelah data tersimpan.</p>
                <div class="flex items-center gap-3">
                    @if (auth()->user()->hasCompletedProfile())
                        <a href="{{ route('dashboard') }}"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-[#071833] bg-[#f6f8fb] ring-1 ring-[#e7eaf0] hover:bg-white transition">
                            Kembali
                        </a>
                    @endif
                    <x-button type="submit" variant="primary" size="lg">Simpan Data Pribadi</x-button>
                </div>
            </div>
        </x-card>
    </form>
@endsection
