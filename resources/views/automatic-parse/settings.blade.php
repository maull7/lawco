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
        @if ($selectedSector)
            <p class="mt-2 text-sm text-[#667085]">Sektor tersimpan: <strong>{{ $selectedSector->name }}</strong>. Maksimal 500 regulasi yang belum diparse akan diproses setiap malam.</p>
        @else
            <p class="mt-2 text-sm text-amber-700">{{ $selectedSectorId ? 'Sektor tersimpan sudah tidak tersedia atau tidak aktif. Pilih sektor aktif kembali.' : 'Belum ada sektor yang dipilih. Simpan sektor untuk mengaktifkan parse terjadwal.' }}</p>
        @endif
        <form method="POST" action="{{ route('regulations.automatic-parse.settings.update') }}" class="mt-5 space-y-4 max-w-xl">
            @csrf
            @method('PUT')
            <div>
                <label for="scheduled-sector" class="block text-sm font-semibold text-[#071833] mb-2">Sektor yang Diparse Pukul 20.00 WIB</label>
                <select id="scheduled-sector" name="sector_id" class="select-premium" required>
                    <option value="">Pilih sektor</option>
                    @foreach ($sectors as $sector)
                        <option value="{{ $sector->id }}" @selected(old('sector_id', $selectedSector?->id) == $sector->id)>{{ $sector->name }}</option>
                    @endforeach
                </select>
                @error('sector_id')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
            </div>
            <p class="text-sm text-[#667085]">Pilihan ini disimpan untuk jadwal setiap malam sampai Anda mengubahnya. Jika sektor tidak memiliki PDF yang belum diparse, jadwal dilewati.</p>
            <x-button type="submit" variant="primary" :disabled="$sectors->isEmpty()">Simpan Sektor Jadwal</x-button>
        </form>
    </x-card>
@endsection
