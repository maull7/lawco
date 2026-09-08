@extends('layouts.app')

@section('title', 'Jenis Regulasi')
@section('header', 'Jenis Regulasi')

@section('content')
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
        <div>
            <p class="text-xs font-semibold tracking-[0.16em] uppercase text-[#c99a3e]">Master Data</p>
            <h2 class="mt-2 text-3xl font-bold text-[#071833] tracking-tight">Jenis Regulasi</h2>
            <p class="mt-1.5 text-sm text-[#667085]">Kelola jenis regulasi beserta level hierarkinya untuk analisis
                kepatuhan.</p>
        </div>
    </div>

    <x-card class="mt-6">
        <form method="GET" action="{{ route('user.regulation-types.index') }}" class="flex flex-col sm:flex-row gap-3 sm:items-center">
            <label for="sector_id" class="text-sm font-semibold text-[#071833]">Filter sektor</label>
            <select id="sector_id" name="sector_id" class="select-premium sm:max-w-xs">
                <option value="">Semua Sektor</option>
                @foreach ($sectors as $sector)
                    <option value="{{ $sector->id }}" {{ $sectorId === $sector->id ? 'selected' : '' }}>{{ $sector->name }}</option>
                @endforeach
            </select>
            <x-button type="submit" variant="primary" size="md">Tampilkan</x-button>
            @if ($sectorId)
                <x-button href="{{ route('user.regulation-types.index') }}" variant="outline" size="md">Reset</x-button>
            @endif
        </form>
    </x-card>

    @if ($types->isEmpty())
        <x-card class="mt-6">
            <div class="text-center py-12">
                <div class="mx-auto w-16 h-16 rounded-2xl bg-[#f6f8fb] flex items-center justify-center text-[#c99a3e]">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                </div>
                <p class="mt-4 text-base font-bold text-[#071833]">Belum ada jenis regulasi</p>
                <p class="mt-1 text-sm text-[#667085]">Tambahkan jenis regulasi seperti Undang-Undang, Peraturan Pemerintah,
                    dll.</p>
                <x-button href="{{ route('regulation-types.create') }}" variant="primary" size="sm"
                    class="mt-5">Tambah Jenis</x-button>
            </div>
        </x-card>
    @else
        <x-card :padding="false" class="mt-6">
            <table class="table-premium">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Jenis Regulasi</th>
                        <th>Sektor</th>
                        <th>Level Hierarki</th>
                        <th>Jumlah Regulasi</th>
                        <th>AKsi</th>

                    </tr>
                </thead>
                <tbody>
                    @foreach ($types as $index => $type)
                        <tr>
                            <td class="font-semibold">{{ $index + 1 }}</td>
                            <td>
                                <span class="font-semibold text-[#071833]">{{ $type->name }}</span>
                            </td>
                            <td>
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse ($type->regulations->pluck('category.sector')->filter()->unique('id') as $sector)
                                        <x-badge color="blue">{{ $sector->name }}</x-badge>
                                    @empty
                                        <span class="text-sm text-[#667085]">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td>
                                <x-badge :color="$type->levelBadgeColor()">
                                    Level {{ $type->level }}
                                </x-badge>
                            </td>
                            <td>
                                <span class="font-semibold text-[#071833]">{{ $type->regulations_count }}</span>
                                <span class="text-[#667085]">regulasi</span>
                            </td>
                            <td>
                                <a href="{{ route('user.regulation-types.show', $type) }}"
                                    class="inline-flex items-center justify-center w-9 h-9 rounded-xl text-[#667085] hover:bg-[#f6f8fb] hover:text-[#071833] transition"
                                    title="Detail">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>

        <div class="mt-6 p-5 rounded-2xl bg-[#f6f8fb] border border-[#e7eaf0]">
            <h4 class="text-sm font-bold text-[#071833] mb-3">Keterangan Hierarki Regulasi</h4>
            <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
                @foreach ([1 => 'red', 2 => 'orange', 3 => 'yellow', 4 => 'blue', 5 => 'green'] as $level => $color)
                    <div class="flex items-center gap-2">
                        <x-badge :color="$color">Level {{ $level }}</x-badge>
                        <span
                            class="text-xs text-[#667085]">{{ $level === 1 ? 'Tertinggi' : ($level === 5 ? 'Terendah' : '') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <x-modal name="confirm-delete-type" title="Hapus Jenis Regulasi" maxWidth="md">
        <div class="flex items-start gap-4">
            <span class="shrink-0 flex items-center justify-center w-10 h-10 rounded-full bg-rose-50 text-rose-500">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                </svg>
            </span>
            <div>
                <p class="text-sm font-semibold text-[#071833]">Hapus Jenis Regulasi</p>
                <p class="mt-1 text-sm text-[#667085] leading-relaxed">Apakah Anda yakin ingin menghapus jenis regulasi ini?
                    Aksi ini tidak dapat dibatalkan.</p>
            </div>
        </div>
        <x-slot name="footer">
            <x-button type="button" variant="outline"
                @click="$dispatch('close-modal-confirm-delete-type')">Batal</x-button>
            <x-button type="button" variant="danger"
                onclick="document.getElementById('delete-type-form-' + window._deleteTypeId).submit()">Hapus</x-button>
        </x-slot>
    </x-modal>
@endsection
