@extends('layouts.app')

@section('title', 'Category Details')
@section('header', $regulationCategory->name)

@section('content')
    {{-- Hero --}}
    <section class="relative overflow-hidden rounded-[24px] bg-navy-gradient text-white p-7 sm:p-9">
        <div class="pointer-events-none absolute -top-24 -right-16 w-80 h-80 rounded-full bg-[#c99a3e]/18 blur-3xl"></div>

        <div class="relative grid lg:grid-cols-3 gap-6 items-start">
            <div class="lg:col-span-2">
                <span
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[10.5px] font-bold rounded-full bg-[#c99a3e]/20 ring-1 ring-[#c99a3e]/30 text-[#e6c06a] uppercase tracking-wider">
                    <span class="w-1 h-1 rounded-full bg-[#e6c06a]"></span>
                    Regulation Category
                </span>
                <h2 class="mt-4 text-3xl font-bold tracking-tight">{{ $regulationCategory->name }}</h2>
                @if ($regulationCategory->description)
                    <p class="mt-3 text-white/70 leading-relaxed max-w-3xl">{{ $regulationCategory->description }}</p>
                @endif
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 backdrop-blur p-5">
                <p class="text-[11px] font-semibold tracking-[0.16em] uppercase text-white/55">Regulasi</p>
                <div class="mt-3 flex items-baseline gap-2">
                    <p class="text-4xl font-bold text-white">{{ $regulationCategory->regulations->count() }}</p>
                    <span class="text-sm text-white/65">regulasi</span>
                </div>
                <p class="text-xs text-white/55 mt-2">Daftar regulasi di bawah kategori ini.</p>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
        <div class="lg:col-span-2 space-y-6">
            <x-card>
                <x-slot name="header">
                    <h3 class="text-lg font-bold text-[#071833]">Category Information</h3>
                </x-slot>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Name</dt>
                        <dd class="mt-1.5 text-sm font-semibold text-[#071833]">{{ $regulationCategory->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Created</dt>
                        <dd class="mt-1.5 text-sm font-semibold text-[#071833]">
                            {{ $regulationCategory->created_at->format('d F Y') }}
                        </dd>
                    </div>
                </dl>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                    @if ($regulationCategory->description)
                        <div class="mt-6 pt-6 border-t border-[#e7eaf0]">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Description</p>
                            <p class="mt-2 text-sm text-[#071833] leading-relaxed">{{ $regulationCategory->description }}
                            </p>
                        </div>
                    @endif
                    @if ($regulationCategory->sector_id)
                        <div class="mt-6 pt-6 border-t border-[#e7eaf0]">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Sektor</p>
                            <p class="mt-2 text-sm text-[#071833] leading-relaxed">{{ $regulationCategory->sector->name }}
                            </p>
                        </div>
                    @endif
                </dl>


            </x-card>

            <x-card :padding="false">
                <x-slot name="header">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-[#071833]">Daftar Regulasi</h3>
                            <p class="text-xs text-[#667085] mt-0.5">Regulasi yang terdaftar di bawah kategori ini</p>
                        </div>
                        <div class="flex items-center gap-2">

                            <span
                                class="px-3 py-1 rounded-full bg-[#f6f8fb] text-xs font-bold text-[#667085]">{{ $regulationCategory->regulations->count() }}
                                regulasi</span>
                        </div>
                    </div>
                </x-slot>

                @if ($regulations->isEmpty())
                    <div class="text-center py-14">
                        <div
                            class="mx-auto w-14 h-14 rounded-2xl bg-[#f6f8fb] flex items-center justify-center text-[#c99a3e]">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="1.4">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        </div>
                        <p class="mt-3 text-sm font-bold text-[#071833]">Belum ada regulasi</p>
                        <p class="text-xs text-[#667085] mt-1">Tambahkan regulasi baru melalui tombol di atas.</p>
                    </div>
                @else
                    <ul class="divide-y divide-[#f0f3f8]">
                        @foreach ($regulations as $regulation)
                            <li class="px-6 py-4 hover:bg-[#f6f8fb]/60 transition">
                                <div class="flex items-start gap-4">
                                    <div
                                        class="shrink-0 w-11 h-11 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path
                                                d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zM14 3.5L18.5 8H14V3.5zM6 20V4h7v5h5v11H6z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('regulations.show', $regulation) }}"
                                            class="text-sm font-bold text-[#071833] hover:text-[#c99a3e] transition">{{ $regulation->regulation_number }}</a>
                                        <p class="text-xs text-[#667085] mt-0.5 line-clamp-1">{{ $regulation->title }}</p>
                                        <div class="flex items-center gap-2 mt-1.5">
                                            @if ($regulation->type)
                                                <x-badge :color="$regulation->type->levelBadgeColor()">{{ $regulation->type->name }}
                                                    Lv{{ $regulation->type->level }}</x-badge>
                                            @endif
                                            <span class="text-[10px] text-[#667085]">{{ $regulation->year }}</span>
                                            <span class="text-[10px] text-[#667085]">&middot;
                                                {{ $regulation->documents->count() }} dokumen</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 mt-3 ml-15">

                                    <x-button href="{{ route('regulations.show', $regulation) }}" variant="ghost"
                                        size="sm">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                        Detail
                                    </x-button>
                                </div>
                            </li>
                        @endforeach
                        <div class="p-6">
                            {{ $regulations->links() }}

                        </div>
                    </ul>

                @endif
            </x-card>

            {{-- Sub Categories --}}
            <x-card :padding="false">
                <x-slot name="header">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-[#071833]">Sub Category</h3>
                            <p class="text-xs text-[#667085] mt-0.5">Daftar sub kategori di bawah kategori ini</p>
                        </div>
                        <span
                            class="px-3 py-1 rounded-full bg-[#f6f8fb] text-xs font-bold text-[#667085]">{{ $regulationCategory->subCategories->count() }}
                            item</span>
                    </div>
                </x-slot>

                @if ($regulationCategory->subCategories->isEmpty())
                    <div class="text-center py-10">
                        <div
                            class="mx-auto w-14 h-14 rounded-2xl bg-[#f6f8fb] flex items-center justify-center text-[#c99a3e]">
                            <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="1.4">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                            </svg>
                        </div>
                        <p class="mt-3 text-sm font-bold text-[#071833]">Belum ada sub category</p>
                        <p class="text-xs text-[#667085] mt-1">Gunakan form di samping untuk menambahkan sub category.</p>
                    </div>
                @else
                    <ul class="divide-y divide-[#f0f3f8]">
                        @foreach ($regulationCategory->subCategories as $sub)
                            <li class="flex items-center gap-4 px-6 py-4 hover:bg-[#f6f8fb]/60 transition">
                                <div
                                    class="shrink-0 w-10 h-10 rounded-xl bg-[#f6f8fb] text-[#c99a3e] flex items-center justify-center">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 0 1 0 2.828l-7 7a2 2 0 0 1-2.828 0l-7-7A2 2 0 0 1 3 12V7a4 4 0 0 1 4-4Z" />
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-[#071833]">{{ $sub->name }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if ($sub->is_active)
                                        <x-badge color="green">Aktif</x-badge>
                                    @else
                                        <x-badge color="gray">Nonaktif</x-badge>
                                    @endif
                                    <form method="POST" action="{{ route('sub-categories.toggle', $sub) }}">
                                        @csrf
                                        @method('PATCH')
                                        <x-button type="submit" variant="ghost" size="sm">
                                            {{ $sub->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                        </x-button>
                                    </form>
                                    <button type="button"
                                        class="inline-flex items-center justify-center w-9 h-9 rounded-xl text-[#667085] hover:bg-[#f6f8fb] transition"
                                        title="Edit"
                                        onclick="window._editSubId={{ $sub->id }}; document.getElementById('edit-sub-name-input').value='{{ addslashes($sub->name) }}'; document.getElementById('edit-sub-form').action='{{ route('sub-categories.update', '__ID__') }}'.replace('__ID__', {{ $sub->id }})"
                                        @click="$dispatch('open-modal-edit-sub-category')">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M16.862 4.487 18.55 2.8a2.121 2.121 0 1 1 3 3L19.863 7.487m-3-3L8.25 13.1l-1.5 4.5 4.5-1.5 8.613-8.613m-3-3 3 3" />
                                        </svg>
                                    </button>
                                    <form method="POST" action="{{ route('sub-categories.destroy', $sub) }}"
                                        id="delete-sub-form-{{ $sub->id }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button"
                                            class="inline-flex items-center justify-center w-9 h-9 rounded-xl text-rose-600 hover:bg-rose-50 transition"
                                            title="Hapus" onclick="window._deleteSubId={{ $sub->id }}"
                                            @click="$dispatch('open-modal-confirm-delete-sub')">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>


    </div>

    <x-modal name="confirm-delete-file" title="Delete File" maxWidth="md">
        <div class="flex items-start gap-4">
            <span class="shrink-0 flex items-center justify-center w-10 h-10 rounded-full bg-rose-50 text-rose-500">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                </svg>
            </span>
            <div>
                <p class="text-sm font-semibold text-[#071833]">Delete File</p>
                <p class="mt-1 text-sm text-[#667085] leading-relaxed">Are you sure you want to delete this file?</p>
            </div>
        </div>
        <x-slot name="footer">
            <x-button type="button" variant="outline"
                @click="$dispatch('close-modal-confirm-delete-file')">Cancel</x-button>
            <x-button type="button" variant="danger"
                onclick="document.getElementById('delete-file-form-' + window._deleteFileId).submit()">Delete</x-button>
        </x-slot>
    </x-modal>

    <x-modal name="confirm-delete-category" title="Delete Category" maxWidth="md">
        <div class="flex items-start gap-4">
            <span class="shrink-0 flex items-center justify-center w-10 h-10 rounded-full bg-rose-50 text-rose-500">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                </svg>
            </span>
            <div>
                <p class="text-sm font-semibold text-[#071833]">Delete Category</p>
                <p class="mt-1 text-sm text-[#667085] leading-relaxed">Are you sure you want to delete this category and
                    all its files? This action cannot be undone.</p>
            </div>
        </div>
        <x-slot name="footer">
            <x-button type="button" variant="outline"
                @click="$dispatch('close-modal-confirm-delete-category')">Cancel</x-button>
            <x-button type="button" variant="danger"
                onclick="document.getElementById('delete-category-form').submit()">Delete</x-button>
        </x-slot>
    </x-modal>

    {{-- Edit Sub Category Modal --}}
    <x-modal name="edit-sub-category" title="Edit Sub Category" maxWidth="md">
        <form method="POST" id="edit-sub-form" action="#">
            @csrf
            @method('PUT')
            <div>
                <label for="edit-sub-name" class="block text-sm font-semibold text-[#071833] mb-2">Nama Sub
                    Category</label>
                <input type="text" name="name" id="edit-sub-name-input" required class="input-premium">
            </div>
        </form>
        <x-slot name="footer">
            <x-button type="button" variant="outline"
                @click="$dispatch('close-modal-edit-sub-category')">Batal</x-button>
            <x-button type="button" variant="primary"
                onclick="document.getElementById('edit-sub-form').submit()">Simpan</x-button>
        </x-slot>
    </x-modal>

    {{-- Delete Sub Category Confirmation Modal --}}
    <x-modal name="confirm-delete-sub" title="Hapus Sub Category" maxWidth="md">
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
                @click="$dispatch('close-modal-confirm-delete-sub')">Batal</x-button>
            <x-button type="button" variant="danger"
                onclick="document.getElementById('delete-sub-form-' + window._deleteSubId).submit()">Hapus</x-button>
        </x-slot>
    </x-modal>
@endsection
