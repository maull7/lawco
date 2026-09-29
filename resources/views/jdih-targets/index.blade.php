@extends('layouts.app')

@section('title', 'Target Scraper')
@section('header', 'Target Scraper')

@section('content')
    <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
        <div>
            <p class="text-xs font-semibold tracking-[0.16em] uppercase text-[#c99a3e]">Master Data</p>
            <h2 class="mt-2 text-3xl font-bold tracking-tight text-[#071833]">Target Website Scraper</h2>
            <p class="mt-1.5 text-sm text-[#667085]">Kelola website, source scraper, dan sektor tujuan.</p>
        </div>
        <x-button href="{{ route('jdih-targets.create') }}" variant="primary">Tambah Target</x-button>
    </div>

    @if (session('success'))
        <x-card class="mt-5 border border-emerald-200 bg-emerald-50 text-sm text-emerald-800">{{ session('success') }}</x-card>
    @endif
    @if (session('error'))
        <x-card class="mt-5 border border-rose-200 bg-rose-50 text-sm text-rose-800">{{ session('error') }}</x-card>
    @endif

    <x-card :padding="false" class="mt-6">
        <div class="overflow-x-auto">
            <table class="table-premium min-w-[900px]">
                <thead>
                    <tr>
                        <th>Nama Website</th>
                        <th>Source</th>
                        <th>Target URL</th>
                        <th>Sektor</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($targets as $target)
                        <tr>
                            <td class="font-semibold text-[#071833]">{{ $target->name }}</td>
                            <td><code>{{ $target->source }}</code></td>
                            <td class="max-w-sm truncate text-[#667085]">{{ $target->target_url ?: 'Belum diatur' }}</td>
                            <td>{{ $target->sector?->name ?? 'Belum dipetakan' }}</td>
                            <td><x-badge :color="$target->is_active ? 'green' : 'gray'">{{ $target->is_active ? 'Aktif' : 'Nonaktif' }}</x-badge></td>
                            <td>
                                <div class="flex justify-end gap-2">
                                    <x-button href="{{ route('jdih-targets.edit', $target) }}" variant="outline" size="sm">Edit</x-button>
                                    <form method="POST" action="{{ route('jdih-targets.destroy', $target) }}" onsubmit="return confirm('Hapus target website ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <x-button type="submit" variant="danger" size="sm">Hapus</x-button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-10 text-center text-sm text-[#667085]">Belum ada target website.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
