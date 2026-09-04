@extends('layouts.app')

@section('title', 'Edit Regulasi')
@section('header', 'Edit Regulasi')

@section('content')
    @php
        $selectedSubIds = $regulation->subCategories->pluck('id')->toArray();
        $selectedRelatedData = $regulation->relatedRegulations
            ->map(
                fn($r) => [
                    'id' => $r->id,
                    'regulation_number' => $r->regulation_number,
                    'title' => $r->title,
                    'year' => $r->year,
                    'type_name' => $r->type?->name,
                ],
            )
            ->values()
            ->toArray();
    @endphp

    <div x-data="regulationEditForm({{ Js::from($categories->mapWithKeys(fn($c) => [$c->id => $c->subCategories->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'is_active' => $s->is_active])])) }}, {{ Js::from($selectedSubIds) }}, {{ Js::from($selectedRelatedData) }})" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <x-card>
                <x-slot name="header">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.16em] uppercase text-[#c99a3e]">Editing</p>
                        <h3 class="mt-1 text-xl font-bold text-[#071833]">{{ $regulation->regulation_number }}</h3>
                        <p class="text-sm text-[#667085] mt-1">{{ Str::limit($regulation->title, 80) }}</p>
                    </div>
                </x-slot>

                <form method="POST" action="{{ route('regulations.update', $regulation) }}" enctype="multipart/form-data"
                    class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="regulation_number" class="block text-sm font-semibold text-[#071833] mb-2">Nomor
                            Regulasi <span class="text-[#c99a3e]">*</span></label>
                        <input type="text" name="regulation_number" id="regulation_number"
                            value="{{ old('regulation_number', $regulation->regulation_number) }}" required
                            class="input-premium">
                        @error('regulation_number')
                            <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label for="year" class="block text-sm font-semibold text-[#071833] mb-2">Tahun Regulasi
                                <span class="text-[#c99a3e]">*</span></label>
                            <input type="number" name="year" id="year" value="{{ old('year', $regulation->year) }}"
                                required min="1900" max="{{ date('Y') + 1 }}" class="input-premium">
                            @error('year')
                                <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="effective_date" class="block text-sm font-semibold text-[#071833] mb-2">Tanggal
                                Berlaku</label>
                            <input type="date" name="effective_date" id="effective_date"
                                value="{{ old('effective_date', $regulation->effective_date?->format('Y-m-d')) }}"
                                class="input-premium">
                            @error('effective_date')
                                <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="tanggal_tetapkan" class="block text-sm font-semibold text-[#071833] mb-2">Tanggal
                                DiTetapkan</label>
                            <input type="date" name="tanggal_tetapkan" id="tanggal_tetapkan"
                                value="{{ old('tanggal_tetapkan', $regulation->tanggal_tetapkan?->format('Y-m-d')) }}"
                                class="input-premium">
                            @error('tanggal_tetapkan')
                                <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="tanggal_diundangkan" class="block text-sm font-semibold text-[#071833] mb-2">Tanggal
                                DiUndangkan</label>
                            <input type="date" name="tanggal_diundangkan" id="tanggal_diundangkan"
                                value="{{ old('tanggal_diundangkan', $regulation->tanggal_diundangkan?->format('Y-m-d')) }}"
                                class="input-premium">
                            @error('tanggal_diundangkan')
                                <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="title" class="block text-sm font-semibold text-[#071833] mb-2">Judul Regulasi <span
                                class="text-[#c99a3e]">*</span></label>
                        <input type="text" name="title" id="title" value="{{ old('title', $regulation->title) }}"
                            required class="input-premium">
                        @error('title')
                            <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label for="regulation_type_id" class="block text-sm font-semibold text-[#071833] mb-2">Jenis
                                Regulasi <span class="text-[#c99a3e]">*</span></label>
                            <select name="regulation_type_id" id="regulation_type_id" required class="select-premium">
                                <option value="">-- Pilih Jenis --</option>
                                @foreach ($types as $type)
                                    <option value="{{ $type->id }}"
                                        {{ old('regulation_type_id', $regulation->regulation_type_id) == $type->id ? 'selected' : '' }}>
                                        {{ $type->name }} (Level {{ $type->level }})
                                    </option>
                                @endforeach
                            </select>
                            @error('regulation_type_id')
                                <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="category_id" class="block text-sm font-semibold text-[#071833] mb-2">Category</label>
                            <select name="category_id" id="category_id" class="select-premium"
                                x-on:change="updateSubCategories($event.target.value)">
                                <option value="">-- Pilih Category --</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}"
                                        {{ old('category_id', $regulation->category_id) == $cat->id ? 'selected' : '' }}>
                                        {{ $cat->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id')
                                <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="file" class="block text-sm font-semibold text-[#071833] mb-2">File Regulasi
                            (PDF)</label>
                        <input type="file" name="file" id="file" accept=".pdf" class="file-premium"
                            @change="previewFile($event)">
                        <p class="mt-1.5 text-xs text-[#667085]">Kosongkan jika tidak ingin mengganti file. Format: PDF
                            (maks. 20MB)</p>
                        @error('file')
                            <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Sub Categories --}}
                    <div x-show="subCategories.length > 0" x-cloak>
                        <label class="block text-sm font-semibold text-[#071833] mb-3">Sub Category</label>
                        <p class="text-xs text-[#667085] mb-3">Pilih satu atau lebih sub category yang sesuai.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <template x-for="sub in subCategories" :key="sub.id">
                                <label
                                    class="flex items-center gap-3 p-3 rounded-xl border border-[#e7eaf0] hover:border-[#c99a3e]/40 hover:bg-[#f6f8fb] transition cursor-pointer">
                                    <input type="checkbox" name="sub_categories[]" :value="sub.id"
                                        :checked="selectedSubIds.includes(sub.id)" class="checkbox-premium">
                                    <span class="text-sm text-[#071833]" x-text="sub.name"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="flex flex-wrap gap-3 pt-3 border-t border-[#e7eaf0]">
                        <x-button type="button" variant="secondary" size="md"
                            @click="$dispatch('open-modal-related-regulations')">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m9.86-2.04a4.5 4.5 0 0 0-1.242-7.244l-4.5-4.5a4.5 4.5 0 0 0-6.364 6.364L4.34 8.598" />
                            </svg>
                            Peraturan Terkait
                            <span x-show="selectedRelated.length > 0" x-cloak
                                class="ml-1 px-2 py-0.5 rounded-full bg-white/20 text-xs"
                                x-text="selectedRelated.length"></span>
                        </x-button>
                        <x-button type="button" variant="outline" size="md"
                            @click="$dispatch('open-modal-add-document')">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12-3-3m0 0-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            Dokumen Tambahan
                        </x-button>
                    </div>

                    {{-- Hidden inputs for related regulations --}}
                    <template x-for="rel in selectedRelated" :key="rel.id">
                        <input type="hidden" name="related_regulations[]" :value="rel.id">
                    </template>

                    <div class="flex flex-col sm:flex-row gap-3 pt-3 border-t border-[#e7eaf0]">
                        <x-button type="submit" variant="primary" size="lg">Perbarui Regulasi</x-button>
                        <x-button href="{{ route('regulations.show', $regulation) }}" variant="outline"
                            size="lg">Batal</x-button>
                    </div>
                </form>
            </x-card>

            {{-- Dokumen Tambahan Quick Upload --}}
            <x-card>
                <x-slot name="header">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-[#071833]">Dokumen Tambahan</h3>
                            <p class="text-xs text-[#667085] mt-0.5">Upload dokumen pendukung untuk regulasi ini</p>
                        </div>
                        <x-button type="button" variant="outline" size="sm"
                            @click="$dispatch('open-modal-add-document')">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            Tambah
                        </x-button>
                    </div>
                </x-slot>

                @if ($regulation->documents->isEmpty())
                    <div class="text-center py-8">
                        <p class="text-sm text-[#667085]">Belum ada dokumen tambahan. Klik "Tambah" untuk mengunggah.</p>
                    </div>
                @else
                    <ul class="divide-y divide-[#f0f3f8]">
                        @foreach ($regulation->documents as $doc)
                            @php $ext = pathinfo($doc->file_path, PATHINFO_EXTENSION); @endphp
                            <li class="py-3 space-y-3">
                                @if ($ext === 'pdf')
                                    <div class="rounded-xl overflow-hidden border border-[#e7eaf0] h-[200px] bg-[#f6f8fb]">
                                        <iframe src="{{ route('regulations.documents.view', $doc) }}"
                                            class="w-full h-full" frameborder="0"></iframe>
                                    </div>
                                @endif
                                <div class="flex items-center gap-4">
                                    <div
                                        class="shrink-0 w-10 h-10 rounded-xl bg-sky-50 text-sky-500 flex items-center justify-center">
                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                            <path
                                                d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zM14 3.5L18.5 8H14V3.5zM6 20V4h7v5h5v11H6z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-[#071833]">{{ $doc->name }}</p>
                                        <p class="text-xs text-[#667085]">{{ $doc->document_type }} ·
                                            {{ strtoupper($ext) }}</p>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <button type="button"
                                            @click="openEditModal({{ Js::from(['id' => $doc->id, 'name' => $doc->name, 'document_type' => $doc->document_type]) }})"
                                            class="inline-flex items-center gap-1.5 px-3 h-9 rounded-xl text-xs font-semibold text-[#071833] bg-[#f6f8fb] ring-1 ring-[#e7eaf0] hover:bg-white hover:ring-[#c99a3e]/40 transition">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                            </svg>
                                            Edit
                                        </button>
                                        <a href="{{ route('regulations.documents.view', $doc) }}" target="_blank"
                                            class="inline-flex items-center gap-1.5 px-3 h-9 rounded-xl text-xs font-semibold text-[#071833] bg-[#f6f8fb] ring-1 ring-[#e7eaf0] hover:bg-white hover:ring-[#c99a3e]/40 transition">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            </svg>
                                            View
                                        </a>
                                        <form method="POST" action="{{ route('regulations.documents.destroy', $doc) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="inline-flex items-center justify-center w-9 h-9 rounded-xl text-rose-600 hover:bg-rose-50 transition"
                                                title="Hapus" onclick="return confirm('Hapus dokumen ini?')">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        <aside class="space-y-6">
            <x-card>
                <x-slot name="header">
                    <h3 class="text-base font-bold text-[#071833]">Peraturan Terkait Terpilih</h3>
                </x-slot>
                <template x-if="selectedRelated.length === 0">
                    <p class="text-sm text-[#667085]">Belum ada peraturan terkait yang dipilih.</p>
                </template>
                <ul class="space-y-3">
                    <template x-for="(rel, index) in selectedRelated" :key="rel.id">
                        <li class="flex items-start gap-3 p-3 rounded-xl bg-[#f6f8fb]">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-[#071833]" x-text="rel.regulation_number"></p>
                                <p class="text-xs text-[#667085] mt-0.5 line-clamp-1" x-text="rel.title"></p>
                            </div>
                            <button type="button" @click="removeRelated(index)"
                                class="shrink-0 p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </li>
                    </template>
                </ul>
            </x-card>

            <x-card>
                <x-slot name="header">
                    <h3 class="text-base font-bold text-[#071833]">Informasi</h3>
                </x-slot>
                <dl class="space-y-4">
                    <div>
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Dibuat</dt>
                        <dd class="mt-1 text-sm font-semibold text-[#071833]">
                            {{ $regulation->created_at->format('d F Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Terakhir Diperbarui</dt>
                        <dd class="mt-1 text-sm font-semibold text-[#071833]">
                            {{ $regulation->updated_at->diffForHumans() }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-card x-show="pdfPreviewUrl" x-cloak>
                <x-slot name="header">
                    <h3 class="text-base font-bold text-[#071833]">Preview PDF</h3>
                </x-slot>
                <div class="aspect-[3/4] rounded-xl overflow-hidden border border-[#e7eaf0]">
                    <iframe :src="pdfPreviewUrl" class="w-full h-full" frameborder="0"></iframe>
                </div>
            </x-card>
        </aside>

        {{-- Related Regulations Modal --}}
        <x-modal name="related-regulations" title="Cari Peraturan Terkait" maxWidth="3xl">
            <div class="space-y-4">
                <div class="flex gap-2">
                    <input type="text" x-model="searchQuery" @keyup.enter="searchRegulations()"
                        class="input-premium flex-1" placeholder="Cari berdasarkan nomor, judul, tahun, atau jenis...">
                    <x-button type="button" variant="primary" @click="searchRegulations()">Cari</x-button>
                </div>

                <div x-show="searchLoading" class="text-center py-8">
                    <div
                        class="inline-block w-8 h-8 border-4 border-[#e7eaf0] border-t-[#c99a3e] rounded-full animate-spin">
                    </div>
                    <p class="mt-2 text-sm text-[#667085]">Mencari...</p>
                </div>

                <div x-show="!searchLoading && searchResults.length > 0"
                    class="max-h-80 overflow-y-auto rounded-xl border border-[#e7eaf0]">
                    <table class="w-full text-sm">
                        <thead class="bg-[#f6f8fb]">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-[#667085] uppercase">Pilih</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-[#667085] uppercase">No. Regulasi
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-[#667085] uppercase">Judul</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-[#667085] uppercase">Tahun</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-[#667085] uppercase">Jenis</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#f0f3f8]">
                            <template x-for="result in searchResults" :key="result.id">
                                <tr class="hover:bg-[#f6f8fb] transition">
                                    <td class="px-4 py-3">
                                        <input type="checkbox" class="checkbox-premium" :checked="isSelected(result.id)"
                                            @change="toggleRelated(result)">
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-[#071833]" x-text="result.regulation_number">
                                    </td>
                                    <td class="px-4 py-3 text-[#071833] max-w-[200px] truncate" x-text="result.title">
                                    </td>
                                    <td class="px-4 py-3 text-[#667085]" x-text="result.year"></td>
                                    <td class="px-4 py-3 text-[#667085]" x-text="result.type_name"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div x-show="!searchLoading && searchResults.length === 0 && searchQuery.length > 0"
                    class="text-center py-8">
                    <p class="text-sm text-[#667085]">Tidak ditemukan hasil untuk pencarian ini.</p>
                </div>
            </div>
            <x-slot name="footer">
                <x-button type="button" variant="outline"
                    @click="$dispatch('close-modal-related-regulations')">Tutup</x-button>
            </x-slot>
        </x-modal>

        {{-- Add Document Modal --}}
        <x-modal name="add-document" title="Upload Dokumen Tambahan" maxWidth="lg">
            <form method="POST" action="{{ route('regulations.documents.store', $regulation) }}"
                enctype="multipart/form-data" class="space-y-5">
                @csrf
                <div>
                    <label for="doc-name" class="block text-sm font-semibold text-[#071833] mb-2">Nama Dokumen <span
                            class="text-[#c99a3e]">*</span></label>
                    <input type="text" name="name" id="doc-name" required class="input-premium"
                        placeholder="Contoh: Ringkasan Regulasi">
                </div>
                <div>
                    <label for="doc-type" class="block text-sm font-semibold text-[#071833] mb-2">Jenis Dokumen <span
                            class="text-[#c99a3e]">*</span></label>
                    <select name="document_type" id="doc-type" required class="select-premium">
                        <option value="">-- Pilih Jenis --</option>
                        <option value="Ringkasan Regulasi">Ringkasan Regulasi</option>
                        <option value="Penjelasan Regulasi">Penjelasan Regulasi</option>
                        <option value="Interpretasi Hukum">Interpretasi Hukum</option>
                        <option value="FAQ">FAQ</option>
                        <option value="Dokumen Sosialisasi">Dokumen Sosialisasi</option>
                        <option value="Lampiran">Lampiran</option>
                        <option value="Dokumen Pendukung">Dokumen Pendukung Lainnya</option>
                        <option value="Resume Regulasi">Resume Regulasi</option>
                    </select>
                </div>
                <div>
                    <label for="doc-file" class="block text-sm font-semibold text-[#071833] mb-2">File <span
                            class="text-[#c99a3e]">*</span></label>
                    <input type="file" name="file" id="doc-file" required
                        accept=".pdf,.docx,.doc,.xlsx,.xls,.pptx,.ppt" class="file-premium">
                    <p class="mt-1.5 text-xs text-[#667085]">Format: PDF, DOCX, XLSX, PPTX (maks. 20MB)</p>
                </div>
                <div class="flex justify-end gap-3 pt-3 border-t border-[#e7eaf0]">
                    <x-button type="button" variant="outline"
                        @click="$dispatch('close-modal-add-document')">Batal</x-button>
                    <x-button type="submit" variant="primary">Upload</x-button>
                </div>
            </form>
        </x-modal>

        {{-- Edit Document Modal --}}
        <x-modal name="edit-document" title="Edit Dokumen Tambahan" maxWidth="lg">
            <form method="POST" :action="`/regulations/documents/${editDocument?.id}`" enctype="multipart/form-data"
                class="space-y-5">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-sm font-semibold text-[#071833] mb-2">Nama Dokumen <span
                            class="text-[#c99a3e]">*</span></label>
                    <input type="text" name="name" x-model="editDocument.name" required class="input-premium">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-[#071833] mb-2">Jenis Dokumen <span
                            class="text-[#c99a3e]">*</span></label>
                    <select name="document_type" x-model="editDocument.document_type" required class="select-premium">
                        <option value="">-- Pilih Jenis --</option>
                        <option value="Ringkasan Regulasi">Ringkasan Regulasi</option>
                        <option value="Penjelasan Regulasi">Penjelasan Regulasi</option>
                        <option value="Interpretasi Hukum">Interpretasi Hukum</option>
                        <option value="FAQ">FAQ</option>
                        <option value="Dokumen Sosialisasi">Dokumen Sosialisasi</option>
                        <option value="Lampiran">Lampiran</option>
                        <option value="Dokumen Pendukung">Dokumen Pendukung Lainnya</option>
                        <option value="Resume Regulasi">Resume Regulasi</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-[#071833] mb-2">Ganti File <span
                            class="text-[#667085] text-xs">(opsional)</span></label>
                    <input type="file" name="file" accept=".pdf,.docx,.doc,.xlsx,.xls,.pptx,.ppt"
                        class="file-premium">
                    <p class="mt-1.5 text-xs text-[#667085]">Kosongkan jika tidak ingin mengganti file.</p>
                </div>
                <div class="flex justify-end gap-3 pt-3 border-t border-[#e7eaf0]">
                    <x-button type="button" variant="outline"
                        @click="$dispatch('close-modal-edit-document')">Batal</x-button>
                    <x-button type="submit" variant="primary">Simpan</x-button>
                </div>
            </form>
        </x-modal>
    </div>
@endsection

@push('scripts')
    <script>
        function regulationEditForm(subCategoriesMap, selectedSubIds, selectedRelated) {
            return {
                subCategories: [],
                selectedSubIds: selectedSubIds,
                selectedRelated: selectedRelated,
                searchQuery: '',
                searchResults: [],
                searchLoading: false,
                pdfPreviewUrl: '{{ Storage::disk('public')->url($regulation->file_path) }}',
                editDocument: null,

                previewFile(event) {
                    if (this.pdfPreviewUrl && !this.pdfPreviewUrl.startsWith('http')) {
                        URL.revokeObjectURL(this.pdfPreviewUrl);
                    }
                    const file = event.target.files[0];
                    if (file && file.type === 'application/pdf') {
                        this.pdfPreviewUrl = URL.createObjectURL(file);
                    }
                },

                openEditModal(doc) {
                    this.editDocument = {
                        ...doc
                    };
                    this.$dispatch('open-modal-edit-document');
                },

                init() {
                    const catSelect = document.getElementById('category_id');
                    if (catSelect && catSelect.value) {
                        this.updateSubCategories(catSelect.value);
                    }
                },

                updateSubCategories(categoryId) {
                    this.subCategories = Object.values(subCategoriesMap).flat();
                },

                async searchRegulations() {
                    if (this.searchQuery.length < 2) return;
                    this.searchLoading = true;
                    try {
                        const response = await fetch(
                            `{{ route('regulations.search') }}?q=${encodeURIComponent(this.searchQuery)}&exclude_id={{ $regulation->id }}`
                        );
                        this.searchResults = await response.json();
                    } catch (e) {
                        this.searchResults = [];
                    }
                    this.searchLoading = false;
                },

                isSelected(id) {
                    return this.selectedRelated.some(r => r.id === id);
                },

                toggleRelated(result) {
                    const index = this.selectedRelated.findIndex(r => r.id === result.id);
                    if (index > -1) {
                        this.selectedRelated.splice(index, 1);
                    } else {
                        this.selectedRelated.push(result);
                    }
                },

                removeRelated(index) {
                    this.selectedRelated.splice(index, 1);
                },
            };
        }
    </script>
@endpush
