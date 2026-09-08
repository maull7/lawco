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
        <x-button type="button" variant="primary" @click="$dispatch('open-modal-add-sub-category')">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Tambah Sub Category
        </x-button>
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
                            <th class="text-center">Aksi</th>
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
                                    <div class="flex items-center justify-end gap-2">

                                        <button type="button"
                                            class="inline-flex items-center justify-center w-9 h-9 rounded-xl text-[#667085] hover:bg-[#f6f8fb] transition"
                                            title="Edit"
                                            onclick="window._editSubId={{ $sub->id }}; document.getElementById('edit-modal-sub-name').value='{{ addslashes($sub->name) }}'; document.getElementById('edit-modal-sub-form').action='{{ route('sub-categories.update', '__ID__') }}'.replace('__ID__', {{ $sub->id }})"
                                            @click="$dispatch('open-modal-edit-sub-category-page')">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M16.862 4.487 18.55 2.8a2.121 2.121 0 1 1 3 3L19.863 7.487m-3-3L8.25 13.1l-1.5 4.5 4.5-1.5 8.613-8.613m-3-3 3 3" />
                                            </svg>
                                        </button>
                                        <form method="POST" action="{{ route('sub-categories.toggle', $sub) }}">
                                            @csrf
                                            @method('PATCH')
                                            <x-button type="submit" variant="{{ $sub->is_active ? 'danger' : 'success' }}"
                                                size="sm">
                                                {{ $sub->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </x-button>
                                        </form>
                                        <a href="{{ route('sub-categories.show', $sub) }}"
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

                                    </div>
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

    {{-- Add Sub Category Modal --}}
    <x-modal name="add-sub-category" title="Tambah Sub Category" maxWidth="lg">
        <form method="POST" action="{{ route('sub-categories.create') }}" class="space-y-5">
            @csrf
            <div>
                <label for="add-category-id" class="block text-sm font-semibold text-[#071833] mb-2">Kategori <span
                        class="text-[#c99a3e]">*</span></label>
                <select name="category_id" id="add-category-id" required class="select-premium">
                    <option value="">-- Pilih Kategori --</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="add-sub-name" class="block text-sm font-semibold text-[#071833] mb-2">Nama Sub Category <span
                        class="text-[#c99a3e]">*</span></label>
                <input type="text" name="name" id="add-sub-name" required class="input-premium"
                    placeholder="Contoh: Reksa Dana">
            </div>
            <div class="flex justify-end gap-3 pt-3 border-t border-[#e7eaf0]">
                <x-button type="button" variant="outline"
                    @click="$dispatch('close-modal-add-sub-category')">Batal</x-button>
                <x-button type="submit" variant="primary">Simpan</x-button>
            </div>
        </form>
    </x-modal>

    {{-- Edit Sub Category Modal --}}
    <x-modal name="edit-sub-category-page" title="Edit Sub Category" maxWidth="md">
        <form method="POST" id="edit-modal-sub-form" action="#">
            @csrf
            @method('PUT')
            <div>
                <label for="edit-modal-sub-name" class="block text-sm font-semibold text-[#071833] mb-2">Nama Sub
                    Category</label>
                <input type="text" name="name" id="edit-modal-sub-name" required class="input-premium">
            </div>
        </form>
        <x-slot name="footer">
            <x-button type="button" variant="outline"
                @click="$dispatch('close-modal-edit-sub-category-page')">Batal</x-button>
            <x-button type="button" variant="primary"
                onclick="document.getElementById('edit-modal-sub-form').submit()">Simpan</x-button>
        </x-slot>
    </x-modal>

    {{-- Delete Confirmation Modal --}}
    <x-modal name="confirm-delete-sub-page" title="Hapus Sub Category" maxWidth="md">
        <div class="flex items-start gap-4">
            <span class="shrink-0 flex items-center justify-center w-10 h-10 rounded-full bg-rose-50 text-rose-500">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                </svg>
            </span>
            <div>
                <p class="text-sm font-semibold text-[#071833]">Hapus Sub Category</p>
                <p class="mt-1 text-sm text-[#667085] leading-relaxed">Apakah Anda yakin ingin menghapus sub category ini?
                </p>
            </div>
        </div>
        <x-slot name="footer">
            <x-button type="button" variant="outline"
                @click="$dispatch('close-modal-confirm-delete-sub-page')">Batal</x-button>
            <x-button type="button" variant="danger"
                onclick="document.getElementById('delete-sub-page-form-' + window._deleteSubPageId).submit()">Hapus</x-button>
        </x-slot>
    </x-modal>
@endsection
