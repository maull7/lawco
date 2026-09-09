@extends('layouts.app')

@section('title', 'Sub Category')
@section('header', 'Sub Category')

@section('content')
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
        <div>
            <p class="text-xs font-semibold tracking-[0.16em] uppercase text-[#c99a3e]">Master Data</p>
            <h2 class="mt-2 text-3xl font-bold text-[#071833] tracking-tight">Sub Category</h2>
            <p class="mt-1.5 text-sm text-[#667085]">Kelola seluruh sub category dari semua kategori regulasi.</p>
        </div>
    </div>

    {{-- Filters --}}
    <x-card class="mt-6">
        <form method="GET" action="{{ route('sub-categories.index') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <input type="text" name="search" value="{{ request('search') }}" class="input-premium"
                    placeholder="Cari sub category...">
            </div>
            <select name="category_id" class="select-premium">
                <option value="">Semua Kategori</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                        {{ $cat->name }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <x-button type="submit" variant="primary" size="md" class="flex-1">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    Cari
                </x-button>
                <x-button href="{{ route('sub-categories.index') }}" variant="outline" size="md">Reset</x-button>
            </div>
        </form>
    </x-card>

    {{-- Table --}}
    <x-card :padding="false" class="mt-6">
        @if ($subCategories->isEmpty())
            <div class="text-center py-14">
                <div class="mx-auto w-16 h-16 rounded-2xl bg-[#f6f8fb] flex items-center justify-center text-[#c99a3e]">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 0 1 0 2.828l-7 7a2 2 0 0 1-2.828 0l-7-7A2 2 0 0 1 3 12V7a4 4 0 0 1 4-4Z" />
                    </svg>
                </div>
                <p class="mt-4 text-base font-bold text-[#071833]">Belum ada sub category</p>
                <p class="mt-1 text-sm text-[#667085]">Tambahkan sub category baru untuk mengelompokkan regulasi.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="table-premium">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Sub Category</th>
                            <th>Kategori</th>
                            <th>Status</th>
                            <th>Jumlah Regulasi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($subCategories as $index => $sub)
                            <tr>
                                <td class="font-semibold">{{ $subCategories->firstItem() + $index }}</td>
                                <td>
                                    <span class="font-semibold text-[#071833]">{{ $sub->name }}</span>
                                </td>
                                <td>
                                    @if ($sub->category)
                                        <a href="{{ route('regulation-categories.show', ['regulation_category' => $sub->category->getRouteKey()]) }}"
                                            class="text-sm text-[#071833] hover:text-[#c99a3e] transition">{{ $sub->category->name }}</a>
                                    @else
                                        <span class="text-sm text-[#667085]">Kategori dihapus</span>
                                    @endif
                                </td>

                                <td>
                                    @if ($sub->is_active)
                                        <x-badge color="green">Aktif</x-badge>
                                    @else
                                        <x-badge color="gray">Nonaktif</x-badge>
                                    @endif
                                </td>
                                <td>
                                    <x-badge color="blue">{{ $sub->regulations->count() }}</x-badge>
                                </td>
                                <td>
                                    <a href="{{ route('user.sub-categories.show', $sub) }}"
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
            </div>

            @if ($subCategories->hasPages())
                <div class="px-6 py-4 border-t border-[#e7eaf0]">
                    {{ $subCategories->links() }}
                </div>
            @endif
        @endif
    </x-card>
@endsection
