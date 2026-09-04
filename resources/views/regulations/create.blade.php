@extends('layouts.app')

@section('title', 'Tambah Regulasi')
@section('header', 'Tambah Regulasi')

@section('content')
    <div x-data="regulationForm({{ Js::from($sectors->mapWithKeys(fn ($sector) => [$sector->id => $sector->categories->mapWithKeys(fn ($category) => [$category->id => ['name' => $category->name, 'subCategories' => $category->subCategories->map(fn ($subCategory) => ['id' => $subCategory->id, 'name' => $subCategory->name])]])])) }})" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <x-card>
                <x-slot name="header">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.16em] uppercase text-[#c99a3e]">Manajemen Regulasi</p>
                        <h3 class="mt-1 text-xl font-bold text-[#071833]">Tambah Regulasi Baru</h3>
                        <p class="text-sm text-[#667085] mt-1">Lengkapi metadata regulasi berikut untuk pengelolaan yang
                            lebih baik.</p>
                    </div>
                </x-slot>

                <form method="POST" action="{{ route('regulations.store') }}" enctype="multipart/form-data" class="space-y-6"
                    @submit.prevent="submitForm">
                    @csrf

                    <div>
                        <label for="regulation_number" class="block text-sm font-semibold text-[#071833] mb-2">Nomor
                            Regulasi <span class="text-[#c99a3e]">*</span></label>
                        <input type="text" name="regulation_number" id="regulation_number"
                            value="{{ old('regulation_number') }}" required class="input-premium"
                            placeholder="Contoh: No. 33/POJK.04/2014">
                        @error('regulation_number')
                            <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label for="sector_id" class="block text-sm font-semibold text-[#071833] mb-2">Sektor <span
                                    class="text-[#c99a3e]">*</span></label>
                            <select name="sector_id" id="sector_id" required class="select-premium"
                                x-model="selectedSector" @change="updateCategories($event.target.value)">
                                <option value="">-- Pilih Sektor --</option>
                                @foreach ($sectors as $sector)
                                    <option value="{{ $sector->id }}" {{ old('sector_id') == $sector->id ? 'selected' : '' }}>
                                        {{ $sector->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('sector_id')
                                <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="year" class="block text-sm font-semibold text-[#071833] mb-2">Tahun Regulasi
                                <span class="text-[#c99a3e]">*</span></label>
                            <input type="number" name="year" id="year" value="{{ old('year', date('Y')) }}"
                                required min="1900" max="{{ date('Y') + 1 }}" class="input-premium">
                            @error('year')
                                <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="effective_date" class="block text-sm font-semibold text-[#071833] mb-2">Tanggal
                                Berlaku</label>
                            <input type="date" name="effective_date" id="effective_date"
                                value="{{ old('effective_date') }}" class="input-premium">
                            @error('effective_date')
                                <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label for="tanggal_tetapkan" class="block text-sm font-semibold text-[#071833] mb-2">Tanggal
                                DiTetapkan</label>
                            <input type="date" name="tanggal_tetapkan" id="tanggal_tetapkan"
                                value="{{ old('tanggal_tetapkan') }}" class="input-premium">
                            @error('tanggal_tetapkan')
                                <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="tanggal_diundangkan" class="block text-sm font-semibold text-[#071833] mb-2">Tanggal
                                DiUndangkan</label>
                            <input type="date" name="tanggal_diundangkan" id="tanggal_diundangkan"
                                value="{{ old('tanggal_diundangkan') }}" class="input-premium">
                            @error('tanggal_diundangkan')
                                <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="title" class="block text-sm font-semibold text-[#071833] mb-2">Judul Regulasi <span
                                class="text-[#c99a3e]">*</span></label>
                        <input type="text" name="title" id="title" value="{{ old('title') }}" required
                            class="input-premium" placeholder="Judul lengkap regulasi">
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
                                        {{ old('regulation_type_id') == $type->id ? 'selected' : '' }}>
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
                                x-model="selectedCategory" x-on:change="updateSubCategories($event.target.value)" :disabled="categories.length === 0">
                                <option value="">-- Pilih Category --</option>
                                <template x-for="category in categories" :key="category.id">
                                    <option :value="category.id" x-text="category.name"></option>
                                </template>
                            </select>
                            @error('category_id')
                                <p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="file" class="block text-sm font-semibold text-[#071833] mb-2">File Regulasi (PDF)
                            <span class="text-[#c99a3e]">*</span></label>
                        <input type="file" name="file" id="file" accept=".pdf" required class="file-premium"
                            @change="previewFile($event)">
                        <p class="mt-1.5 text-xs text-[#667085]">Format yang didukung: PDF (maks. 20MB)</p>
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
                                        class="checkbox-premium">
                                    <span class="text-sm text-[#071833]" x-text="sub.name"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="flex flex-wrap gap-3 pt-3 border-t border-[#e7eaf0]">
                        <x-button type="button" variant="secondary" size="md"
                            @click="$dispatch('open-modal-related-regulations')">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
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
                            <span x-show="documents.length > 0" x-cloak
                                class="ml-1 px-2 py-0.5 rounded-full bg-white/20 text-xs"
                                x-text="documents.length"></span>
                        </x-button>
                    </div>

                    {{-- Hidden inputs for related regulations --}}
                    <template x-for="rel in selectedRelated" :key="rel.id">
                        <input type="hidden" name="related_regulations[]" :value="rel.id">
                    </template>

                    {{-- Dokumen Tambahan --}}
                    <div x-show="documents.length > 0" x-cloak class="pt-3 border-t border-[#e7eaf0] space-y-3">
                        <label class="block text-sm font-semibold text-[#071833] mb-1">Dokumen Tambahan</label>
                        <template x-for="(doc, index) in documents" :key="index">
                            <div class="flex items-start gap-3 p-3 rounded-xl bg-[#f6f8fb]">
                                <div
                                    class="shrink-0 mt-1.5 w-8 h-8 rounded-lg bg-sky-50 text-sky-500 flex items-center justify-center">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zM14 3.5L18.5 8H14V3.5zM6 20V4h7v5h5v11H6z" />
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-[#071833]" x-text="doc.name"></p>
                                    <p class="text-xs text-[#667085]" x-text="doc.document_type"></p>
                                    <p class="text-xs text-[#667085] mt-0.5">
                                        <span x-text="doc.file.name"></span>
                                        <span x-text="`(${(doc.file.size / 1024 / 1024).toFixed(1)} MB)`"></span>
                                    </p>
                                </div>
                                <button type="button" @click="removeDocument(index)"
                                    class="shrink-0 mt-1.5 p-1.5 rounded-lg text-rose-500 hover:bg-rose-50 transition">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </template>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3 pt-3 border-t border-[#e7eaf0]">
                        <x-button type="submit" variant="primary" size="lg">Simpan Regulasi</x-button>
                        <x-button href="{{ route('regulations.index') }}" variant="outline"
                            size="lg">Batal</x-button>
                    </div>
                </form>
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
                    <h3 class="text-base font-bold text-[#071833]">Panduan</h3>
                </x-slot>
                <div class="space-y-3 text-sm text-[#667085] leading-relaxed">
                    <p><strong class="text-[#071833]">Nomor Regulasi</strong> — Gunakan format resmi seperti "No.
                        33/POJK.04/2014".</p>
                    <p><strong class="text-[#071833]">Sub Category</strong> — Pilih category terlebih dahulu untuk
                        menampilkan daftar sub category yang sesuai.</p>
                    <p><strong class="text-[#071833]">Peraturan Terkait</strong> — Hubungkan regulasi yang saling berkaitan
                        untuk analisis hierarki.</p>
                </div>
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

                <div x-show="!searchLoading && searchQuery.length === 0" class="text-center py-8">
                    <p class="text-sm text-[#667085]">Ketik kata kunci lalu tekan Enter atau klik tombol Cari.</p>
                </div>
            </div>
            <x-slot name="footer">
                <x-button type="button" variant="outline"
                    @click="$dispatch('close-modal-related-regulations')">Tutup</x-button>
            </x-slot>
        </x-modal>

        {{-- Add Document Modal --}}
        <x-modal name="add-document" title="Tambah Dokumen Tambahan" maxWidth="lg">
            <form @submit.prevent="addDocument" class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-[#071833] mb-2">Nama Dokumen <span
                            class="text-[#c99a3e]">*</span></label>
                    <input type="text" x-model="newDoc.name" required class="input-premium"
                        placeholder="Contoh: Ringkasan Regulasi">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-[#071833] mb-2">Jenis Dokumen <span
                            class="text-[#c99a3e]">*</span></label>
                    <select x-model="newDoc.document_type" required class="select-premium">
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
                    <label class="block text-sm font-semibold text-[#071833] mb-2">File <span
                            class="text-[#c99a3e]">*</span></label>
                    <input type="file" x-ref="modalFile" @change="newDoc.file = $event.target.files[0]" required
                        accept=".pdf,.docx,.doc,.xlsx,.xls,.pptx,.ppt" class="file-premium">
                    <p class="mt-1.5 text-xs text-[#667085]">Format: PDF, DOCX, XLSX, PPTX (maks. 20MB)</p>
                </div>
                <div class="flex justify-end gap-3 pt-3 border-t border-[#e7eaf0]">
                    <x-button type="button" variant="outline"
                        @click="$dispatch('close-modal-add-document')">Batal</x-button>
                    <x-button type="submit" variant="primary">Tambah</x-button>
                </div>
            </form>
        </x-modal>
    </div>
@endsection

@push('scripts')
    <script>
        function regulationForm(subCategoriesMap) {
            return {
                categories: [],
                subCategories: [],
                selectedSector: @js(old('sector_id', '')),
                selectedCategory: @js(old('category_id', '')),
                selectedRelated: [],
                searchQuery: '',
                searchResults: [],
                searchLoading: false,
                pdfPreviewUrl: null,
                documents: [],
                newDoc: {
                    name: '',
                    document_type: '',
                    file: null
                },
                submitting: false,

                previewFile(event) {
                    if (this.pdfPreviewUrl) {
                        URL.revokeObjectURL(this.pdfPreviewUrl);
                    }
                    const file = event.target.files[0];
                    if (file && file.type === 'application/pdf') {
                        this.pdfPreviewUrl = URL.createObjectURL(file);
                    } else {
                        this.pdfPreviewUrl = null;
                    }
                },

                addDocument() {
                    if (!this.newDoc.name || !this.newDoc.document_type || !this.newDoc.file) return;
                    this.documents.push({
                        ...this.newDoc
                    });
                    this.newDoc = {
                        name: '',
                        document_type: '',
                        file: null
                    };
                    if (this.$refs.modalFile) this.$refs.modalFile.value = '';
                    this.$dispatch('close-modal-add-document');
                },

                removeDocument(index) {
                    this.documents.splice(index, 1);
                },

                async submitForm(event) {
                    if (this.submitting) return;
                    this.submitting = true;

                    const form = event.target;
                    const formData = new FormData(form);

                    this.documents.forEach((doc, i) => {
                        formData.set(`documents[${i}][name]`, doc.name);
                        formData.set(`documents[${i}][document_type]`, doc.document_type);
                        formData.append(`documents[${i}][file]`, doc.file);
                    });

                    try {
                        const response = await fetch(form.action, {
                            method: form.method,
                            body: formData,
                        });

                        this.submitting = false;
                        window.location.href = response.url;
                    } catch (e) {
                        this.submitting = false;
                    }
                },

                updateCategories(sectorId) {
                    this.categories = Object.entries(subCategoriesMap[sectorId] || {}).map(([id, category]) => ({
                        id: Number(id),
                        name: category.name,
                        subCategories: category.subCategories,
                    }));
                    this.selectedCategory = '';
                    this.subCategories = [];
                },

                updateSubCategories(categoryId) {
                    const category = this.categories.find(category => category.id === Number(categoryId));
                    this.subCategories = category?.subCategories || [];
                },

                init() {
                    const oldCategory = this.selectedCategory;
                    this.updateCategories(this.selectedSector);
                    this.selectedCategory = oldCategory;
                    this.updateSubCategories(this.selectedCategory);
                },

                async searchRegulations() {
                    if (this.searchQuery.length < 2) return;
                    this.searchLoading = true;
                    try {
                        const response = await fetch(
                            `{{ route('regulations.search') }}?q=${encodeURIComponent(this.searchQuery)}`);
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
