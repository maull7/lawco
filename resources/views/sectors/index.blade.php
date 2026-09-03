@extends('layouts.app')

@section('title', 'Sektor')
@section('header', 'Sektor')

@section('content')
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
        <div>
            <p class="text-xs font-semibold tracking-[0.16em] uppercase text-[#c99a3e]">Master Data</p>
            <h2 class="mt-2 text-3xl font-bold text-[#071833] tracking-tight">Sektor</h2>
            <p class="mt-1.5 text-sm text-[#667085]">Kelola sektor industri untuk mengelompokkan kategori regulasi.</p>
        </div>
        <x-button href="{{ route('sectors.create') }}" variant="primary">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Tambah Sektor
        </x-button>
    </div>

    @if ($sectors->isEmpty())
        <x-card class="mt-6">
            <div class="text-center py-12">
                <div class="mx-auto w-16 h-16 rounded-2xl bg-[#f6f8fb] flex items-center justify-center text-[#c99a3e]">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                    </svg>
                </div>
                <p class="mt-4 text-base font-bold text-[#071833]">Belum ada sektor</p>
                <p class="mt-1 text-sm text-[#667085]">Tambahkan sektor industri seperti Perbankan, Energi, atau Kesehatan.</p>
                <x-button href="{{ route('sectors.create') }}" variant="primary" size="sm"
                    class="mt-5">Tambah Sektor</x-button>
            </div>
        </x-card>
    @else
        <x-card :padding="false" class="mt-6">
            <table class="table-premium">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Sektor</th>
                        <th>Deskripsi</th>
                        <th>Jumlah Kategori</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sectors as $index => $sector)
                        <tr>
                            <td class="font-semibold">{{ $index + 1 }}</td>
                            <td>
                                <button type="button" class="font-semibold text-[#071833] hover:text-[#c99a3e] transition"
                                    @click="$dispatch('open-modal-sector-{{ $sector->id }}')">
                                    {{ $sector->name }}
                                </button>
                            </td>
                            <td class="text-[#667085] max-w-xs truncate">{{ $sector->description }}</td>
                            <td>
                                <span class="font-semibold text-[#071833]">{{ $sector->categories_count }}</span>
                                <span class="text-[#667085]">kategori</span>
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-2">
                                    <x-button href="{{ route('sectors.edit', $sector) }}" variant="outline" size="sm">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M16.862 4.487 18.55 2.8a2.121 2.121 0 1 1 3 3L19.863 7.487m-3-3L8.25 13.1l-1.5 4.5 4.5-1.5 8.613-8.613m-3-3 3 3" />
                                        </svg>
                                    </x-button>
                                    <form method="POST" action="{{ route('sectors.destroy', $sector) }}"
                                        id="delete-sector-form-{{ $sector->id }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-button type="button" variant="danger" size="sm"
                                            onclick="window._deleteSectorId={{ $sector->id }}"
                                            @click="$dispatch('open-modal-confirm-delete-sector')">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </x-button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>
    @endif

    @foreach ($sectors as $sector)
        <x-modal name="sector-{{ $sector->id }}" title="Detail Sektor" :full-screen="true">
            <div class="space-y-6">
                <div>
                    <p class="text-xs font-semibold tracking-[0.16em] uppercase text-[#c99a3e]">Sektor</p>
                    <h3 class="mt-1 text-2xl font-bold text-[#071833]">{{ $sector->name }}</h3>
                    <p class="mt-3 text-sm leading-relaxed text-[#667085]">{{ $sector->description ?: 'Belum ada deskripsi sektor.' }}</p>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-[#071833]">Kategori dalam sektor</h4>
                    @forelse ($sector->categories as $category)
                        <div class="mt-3 rounded-xl border border-[#e7eaf0] p-4" x-data="{ editingCategory: false }">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-semibold text-[#071833]">{{ $category->name }}</p>
                                    @if ($category->regulations_count > 0)
                                        <p class="mt-1 text-xs text-[#667085]">{{ $category->regulations_count }} regulasi menggunakan kategori ini.</p>
                                    @endif
                                </div>
                                @if (auth()->user()->hasPermission('manage_categories'))
                                    <div class="flex shrink-0 items-center gap-2">
                                        <x-button type="button" variant="outline" size="sm" @click="editingCategory = ! editingCategory">
                                            Edit
                                        </x-button>
                                        @if ($category->regulations_count === 0 && $category->subCategories->every(fn ($subCategory) => $subCategory->regulations_count === 0))
                                            <form method="POST" action="{{ route('regulation-categories.destroy', $category) }}"
                                                id="delete-category-form-{{ $category->id }}">
                                                @csrf
                                                @method('DELETE')
                                                <x-button type="button" variant="danger" size="sm"
                                                    onclick="window._deleteCategoryId={{ $category->id }}"
                                                    @click="$dispatch('open-modal-confirm-delete-category')">
                                                    Hapus
                                                </x-button>
                                            </form>
                                        @else
                                            <span class="text-xs font-medium text-[#667085]" title="Kategori masih digunakan oleh regulasi">Tidak dapat dihapus</span>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <form method="POST" action="{{ route('regulation-categories.update', $category) }}"
                                class="mt-3 space-y-3" x-show="editingCategory" x-cloak>
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="return_to" value="sectors">
                                <input type="text" name="name" value="{{ $category->name }}" required maxlength="255"
                                    class="input-premium">
                                <input type="hidden" name="sector_id" value="{{ $sector->id }}">
                                <div class="flex justify-end gap-2">
                                    <x-button type="button" variant="outline" size="sm" @click="editingCategory = false">Batal</x-button>
                                    <x-button type="submit" variant="primary" size="sm">Simpan</x-button>
                                </div>
                            </form>

                            @if ($category->subCategories->isNotEmpty())
                                <div class="mt-3 space-y-2">
                                    @foreach ($category->subCategories as $subCategory)
                                        <div class="flex items-center justify-between gap-3 rounded-lg bg-[#f6f8fb] px-3 py-2"
                                            x-data="{ editingSubCategory: false }">
                                            <form method="POST" action="{{ route('sub-categories.update', $subCategory) }}"
                                                class="flex min-w-0 flex-1 items-center gap-2" x-show="editingSubCategory" x-cloak>
                                                @csrf
                                                @method('PUT')
                                                <input type="text" name="name" value="{{ $subCategory->name }}" required maxlength="255"
                                                    class="input-premium min-w-0 py-1.5 text-sm">
                                                <x-button type="submit" variant="primary" size="sm">Simpan</x-button>
                                                <x-button type="button" variant="outline" size="sm" @click="editingSubCategory = false">Batal</x-button>
                                            </form>
                                            <span class="min-w-0 truncate text-xs text-[#667085]" x-show="! editingSubCategory">{{ $subCategory->name }}</span>
                                            @if (auth()->user()->hasPermission('manage_sub_categories'))
                                                <div class="flex shrink-0 items-center gap-2" x-show="! editingSubCategory">
                                                    <button type="button" class="text-xs font-semibold text-[#071833] hover:text-[#c99a3e]"
                                                        @click="editingSubCategory = true">Edit</button>
                                                    @if ($subCategory->regulations_count === 0)
                                                        <form method="POST" action="{{ route('sub-categories.destroy', $subCategory) }}"
                                                            id="delete-sub-category-form-{{ $subCategory->id }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="button" class="text-xs font-semibold text-rose-600 hover:text-rose-700"
                                                                onclick="window._deleteSubCategoryId={{ $subCategory->id }}"
                                                                @click="$dispatch('open-modal-confirm-delete-sub-category')">Hapus</button>
                                                        </form>
                                                    @else
                                                        <span class="text-xs text-[#667085]" title="Sub kategori masih digunakan oleh regulasi">Tidak dapat dihapus</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="mt-1 text-xs text-[#667085]">Belum ada sub category.</p>
                            @endif
                        </div>
                    @empty
                        <p class="mt-3 text-sm text-[#667085]">Belum ada kategori dalam sektor ini.</p>
                    @endforelse
                </div>
            </div>
            <x-slot name="footer">
                <x-button type="button" variant="outline" @click="$dispatch('close-modal-sector-{{ $sector->id }}')">Tutup</x-button>
            </x-slot>
        </x-modal>
    @endforeach

    <x-modal name="confirm-delete-category" title="Hapus Kategori" maxWidth="md">
        <p class="text-sm leading-relaxed text-[#667085]">Apakah Anda yakin ingin menghapus kategori ini beserta sub kategori di dalamnya?</p>
        <x-slot name="footer">
            <x-button type="button" variant="outline"
                @click="$dispatch('close-modal-confirm-delete-category')">Batal</x-button>
            <x-button type="button" variant="danger"
                onclick="document.getElementById('delete-category-form-' + window._deleteCategoryId).submit()">Hapus</x-button>
        </x-slot>
    </x-modal>

    <x-modal name="confirm-delete-sub-category" title="Hapus Sub Kategori" maxWidth="md">
        <p class="text-sm leading-relaxed text-[#667085]">Apakah Anda yakin ingin menghapus sub kategori ini?</p>
        <x-slot name="footer">
            <x-button type="button" variant="outline"
                @click="$dispatch('close-modal-confirm-delete-sub-category')">Batal</x-button>
            <x-button type="button" variant="danger"
                onclick="document.getElementById('delete-sub-category-form-' + window._deleteSubCategoryId).submit()">Hapus</x-button>
        </x-slot>
    </x-modal>

    <x-modal name="confirm-delete-sector" title="Hapus Sektor" maxWidth="md">
        <div class="flex items-start gap-4">
            <span class="shrink-0 flex items-center justify-center w-10 h-10 rounded-full bg-rose-50 text-rose-500">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                </svg>
            </span>
            <div>
                <p class="text-sm font-semibold text-[#071833]">Hapus Sektor</p>
                <p class="mt-1 text-sm text-[#667085] leading-relaxed">Apakah Anda yakin ingin menghapus sektor ini?
                    Kategori regulasi yang terkait akan menjadi tidak ber-sektor.</p>
            </div>
        </div>
        <x-slot name="footer">
            <x-button type="button" variant="outline"
                @click="$dispatch('close-modal-confirm-delete-sector')">Batal</x-button>
            <x-button type="button" variant="danger"
                onclick="document.getElementById('delete-sector-form-' + window._deleteSectorId).submit()">Hapus</x-button>
        </x-slot>
    </x-modal>
@endsection
