@extends('layouts.app')
@section('title', 'Pengaturan Parse Otomatis')
@section('header', 'Pengaturan Parse Otomatis')
@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-3xl font-bold text-[#071833]">Pengaturan Parse Otomatis</h2>
            <p class="mt-2 text-sm text-[#667085]">Pilih sektor untuk parse otomatis setiap hari pukul 20.00 WIB.</p>
        </div>
        <x-button href="{{ route('regulations.index') }}" variant="outline">Kembali ke Regulasi</x-button>
    </div>
    <x-card class="mt-6">
        <h3 class="font-bold text-[#071833]">Sektor Jadwal Harian</h3>
        @if ($selectedSectors->isNotEmpty() && $selectedSectors->count() === count($selectedSectorIds))
            <p class="mt-2 text-sm text-[#667085]">{{ $selectedSectors->count() }} sektor tersimpan · Maksimal 3.000 regulasi per malam · 500 dokumen per batch.</p>
            <details class="mt-2 text-sm text-[#667085]"><summary class="cursor-pointer">Lihat sektor tersimpan</summary><p class="mt-2 max-h-24 overflow-y-auto break-words">{{ $selectedSectors->pluck('name')->implode(', ') }}</p></details>
        @else
            <p class="mt-2 text-sm text-amber-700">{{ $selectedSectorIds !== [] ? 'Sektor tersimpan sudah tidak tersedia atau tidak aktif. Pilih sektor aktif kembali.' : 'Belum ada sektor yang dipilih. Simpan sektor untuk mengaktifkan parse terjadwal.' }}</p>
        @endif
        <form method="POST" action="{{ route('regulations.automatic-parse.settings.update') }}" class="mt-5 space-y-4 max-w-3xl">
            @csrf
            @method('PUT')
            <fieldset>
                <legend class="block text-sm font-semibold text-[#071833] mb-2">Sektor yang Diparse Pukul 20.00 WIB</legend>
                <p class="mb-3 text-xs text-[#667085]">Centang satu atau beberapa sektor.</p>
                <x-sector-selector :sectors="$sectors" :selected="old('sector_ids', $selectedSectorIds)" empty-label="Pilih minimal satu sektor untuk jadwal harian." />
                @error('sector_ids')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                @error('sector_ids.*')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
            </fieldset>
            <p class="text-sm text-[#667085]">Pilihan ini disimpan untuk jadwal setiap malam sampai Anda mengubahnya. Jika sektor tidak memiliki PDF yang belum diparse, jadwal dilewati.</p>
            <x-button type="submit" variant="primary" :disabled="$sectors->isEmpty()">Simpan Sektor Jadwal</x-button>
        </form>
    </x-card>
        <x-card class="mt-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="font-bold text-[#071833]">Jalankan Parse Sekarang</h3>
                <div class="flex flex-wrap gap-2">
                    <x-button href="{{ route('regulations.automatic-parse.history') }}" variant="outline" size="sm">Riwayat Parse</x-button>
                </div>
            </div>
            <p class="mt-1 text-sm text-[#667085]">Jadwal pukul 20.00 WIB mengikuti sektor yang disimpan di menu Pengaturan Parse Otomatis, maksimal 3.000 regulasi dalam batch berisi maksimal 500 dokumen. Form di bawah untuk menjalankan parse langsung.</p>
            <form method="POST" action="{{ route('regulations.automatic-parse') }}" class="mt-5 grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
                @csrf
                <div>
                    <label for="automatic-parse-limit" class="block text-sm font-semibold mb-1">Limit dokumen (1–3.000)</label>
                    <input id="automatic-parse-limit" name="limit" type="number" min="1" max="3000" required value="{{ old('limit', 3000) }}" class="input-premium" @disabled($automaticParseSummary['active'] ?? false)>
                    @error('limit')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <fieldset class="min-w-0 lg:col-span-2">
                    <legend class="block text-sm font-semibold mb-1">Sektor yang Diparse</legend>
                    <x-sector-selector :sectors="$sectors" :selected="old('sector_ids', $selectedSectorIds)" :disabled="$automaticParseSummary['active'] ?? false" empty-label="Semua sektor akan diparse jika tidak ada pilihan." />
                    @error('sector_ids')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    @error('sector_ids.*')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </fieldset>
                <div class="lg:col-span-3 border-t border-[#e7eaf0] pt-4 flex justify-end">
                    <x-button type="submit" variant="primary" :disabled="$automaticParseSummary['active'] ?? false">Mulai Parse Sekarang</x-button>
                </div>
            </form>
            @if ($automaticParseSummary)
                <div class="mt-4 text-sm text-[#667085]" role="status">
                    <p class="font-semibold">{{ $automaticParseSummary['active'] ? 'Batch sedang berjalan' : 'Batch terakhir selesai' }} · {{ $automaticParseSummary['percent'] }}%</p>
                    <p class="mt-1">Jumlah batch: {{ $automaticParseSummary['batches'] }} (maksimal 500 dokumen per batch)</p>
                    <details class="mt-2"><summary class="cursor-pointer font-semibold">Lihat sektor dalam proses ini</summary><p class="mt-2 max-h-24 overflow-y-auto break-words">{{ $automaticParseSummary['sector'] }}</p></details>
                    <p class="mt-1">Total: {{ $automaticParseSummary['total'] }} · Menunggu/berjalan: {{ $automaticParseSummary['pending'] }} · Complete: {{ $automaticParseSummary['complete'] }} · Incomplete: {{ $automaticParseSummary['incomplete'] }} · Gagal: {{ $automaticParseSummary['failed'] }}</p>
                    @if ($automaticParseSummary['active'])<p class="mt-1">Muat ulang halaman untuk melihat progres terbaru.</p>@endif
                </div>
            @endif
        </x-card>
@endsection
