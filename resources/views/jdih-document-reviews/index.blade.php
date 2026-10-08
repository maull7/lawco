@extends('layouts.app')

@section('title', 'Pemeriksaan Jenis JDIH')
@section('header', 'Pemeriksaan Jenis JDIH')

@section('content')
    <h2 class="text-3xl font-bold text-[#071833]">Pemeriksaan Jenis JDIH</h2>
    <p class="mt-2 text-sm text-[#667085]">Periksa dokumen yang jenisnya belum ditentukan. Pilih jenis yang sudah ada atau tambahkan jenis baru berdasarkan isi PDF. Keputusan berlaku hanya untuk sumber dan ID dokumen tersebut.</p>
    @foreach (['success' => 'emerald', 'error' => 'rose'] as $status => $color)
        @if (session($status))
            <x-card class="mt-4 text-sm" role="status">{{ session($status) }}</x-card>
        @endif
    @endforeach
    @if ($errors->any())
        <x-card class="mt-4 text-sm text-rose-700">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-card>
    @endif
    <x-card class="mt-5">
        <form method="GET" action="{{ route('jdih-document-reviews.index') }}" class="flex flex-col gap-3 md:flex-row md:items-end">
            <div class="flex-1">
                <label for="review-search" class="mb-2 block text-sm font-semibold">Pencarian</label>
                <input id="review-search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="200" class="input-premium" placeholder="Cari judul, sumber, sektor, atau ID dokumen...">
            </div>
            <div>
                <label for="review-sector" class="mb-2 block text-sm font-semibold">Sektor</label>
                <select id="review-sector" name="sector_id" class="select-premium">
                    <option value="">Semua sektor</option>
                    @foreach ($sectors as $sector)
                        <option value="{{ $sector->id }}" @selected(($filters['sector_id'] ?? '') == $sector->id)>{{ $sector->name }}</option>
                    @endforeach
                </select>
            </div>
            <x-button>Cari</x-button>
            <x-button :href="route('jdih-document-reviews.index')" variant="outline">Reset</x-button>
        </form>
    </x-card>
    @if ($review['warning'])
        <x-card class="mt-4 text-sm text-amber-800">{{ $review['warning'] }}</x-card>
    @endif
    <p class="mt-4 text-sm text-[#667085]">{{ $review['documents']->total() }} dokumen belum masuk Lawco. Impor tetap memeriksa metadata dan kategori dari seluruh master; kategori yang kurang dapat ditambahkan melalui master Kategori.</p>
    <div class="mt-3 flex flex-wrap gap-2">
        <x-button :href="route('regulation-categories.index')" variant="outline" size="sm">Kelola Kategori</x-button>
        <x-button :href="route('regulation-types.index')" variant="outline" size="sm">Kelola Jenis Regulasi</x-button>
    </div>
    @forelse ($review['documents'] as $document)
        @php
            $choice = $choices->get($document['source'].':'.$document['id']);
            $key = $loop->index;
            $isPrevious = old('source') === $document['source'] && old('document_id') === $document['id'];
            $mode = $isPrevious ? old('type_mode', 'existing') : 'existing';
            $selectedType = $isPrevious ? old('regulation_type_id') : $choice?->regulation_type_id;
        @endphp
        <x-card class="mt-5">
            <h3 class="text-lg font-bold break-words">{{ $document['title'] }}</h3>
            <p class="mt-2 text-sm">Sumber: {{ $document['source_name'] }} ({{ $document['source'] }}) · Sektor: {{ $document['sector'] }}</p>
            <p class="mt-1 text-sm">ID: {{ $document['id'] }} · Jenis JDIH: {{ $document['slug'] }} · Kategori: {{ $document['category'] }}</p>
            <p class="mt-1 text-sm text-[#667085]">File: {{ $document['filename'] }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <x-button :href="route('scraping-failures.document', ['source' => $document['source'], 'document_id' => $document['id']])" target="_blank" rel="noopener noreferrer" variant="outline" size="sm">Lihat PDF</x-button>
                @if ($document['source_url'])
                    <x-button :href="$document['source_url']" target="_blank" rel="noopener noreferrer" variant="outline" size="sm">Website Sumber JDIH</x-button>
                @endif
            </div>
            @if ($choice)
                <p class="mt-3 text-sm text-emerald-800">Pilihan tersimpan: {{ $choice->type?->name ?? 'Jenis sudah dihapus' }}. @if ($choice->category_id) Kategori: {{ $choice->category?->name ?? 'Kategori sudah dihapus' }}. @endif</p>
                @if (! $choice->type?->is_active)
                    <p class="mt-1 text-sm text-amber-800">Jenis pilihan tidak aktif. Pilih jenis aktif sebelum impor.</p>
                @endif
            @endif
            <form method="POST" action="{{ route('jdih-document-reviews.store') }}" class="mt-4" x-data="{ mode: @js($mode) }">
                @csrf
                <input type="hidden" name="source" value="{{ $document['source'] }}">
                <input type="hidden" name="document_id" value="{{ $document['id'] }}">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label for="type-mode-{{ $key }}" class="mb-2 block text-sm font-semibold">Cara menentukan jenis</label>
                        <select id="type-mode-{{ $key }}" name="type_mode" class="select-premium" x-model="mode">
                            <option value="existing" @selected($mode === 'existing')>Pilih jenis yang sudah ada</option>
                            <option value="new" @selected($mode === 'new')>Tambahkan jenis baru</option>
                        </select>
                    </div>
                    <div x-show="mode === 'existing'">
                        <label for="type-{{ $key }}" class="mb-2 block text-sm font-semibold">Jenis regulasi</label>
                        <select id="type-{{ $key }}" name="regulation_type_id" class="select-premium" :disabled="mode !== 'existing'" :required="mode === 'existing'">
                            <option value="">Pilih jenis regulasi</option>
                            @foreach ($types as $type)
                                <option value="{{ $type->id }}" @selected($selectedType == $type->id)>{{ $type->name }} (level {{ $type->level }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div x-show="mode === 'new'" x-cloak>
                        <label for="new-type-{{ $key }}" class="mb-2 block text-sm font-semibold">Nama jenis baru</label>
                        <input id="new-type-{{ $key }}" name="new_type_name" maxlength="255" value="{{ $isPrevious ? old('new_type_name') : '' }}" class="input-premium" :disabled="mode !== 'new'" :required="mode === 'new'" placeholder="Contoh: Keputusan Menteri">
                    </div>
                    <div x-show="mode === 'new'" x-cloak>
                        <label for="level-{{ $key }}" class="mb-2 block text-sm font-semibold">Level hierarki (default 4)</label>
                        <select id="level-{{ $key }}" name="level" class="select-premium" :disabled="mode !== 'new'">
                            @foreach (range(1, 5) as $level)
                                <option value="{{ $level }}" @selected(($isPrevious ? old('level', 4) : 4) == $level)>Level {{ $level }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <label for="category-{{ $key }}" class="mb-2 block text-sm font-semibold">Kategori (lintas sektor)</label>
                    <select id="category-{{ $key }}" name="category_id" class="select-premium">
                        <option value="">Otomatis berdasarkan nama kategori JDIH</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(($isPrevious ? old('category_id') : $choice?->category_id) == $category->id)>{{ $category->name }} — ID {{ $category->id }}{{ $category->sector ? ' · '.$category->sector->name : '' }}</option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs text-[#667085]">Kategori dapat dipakai lintas sektor. Jika ada nama yang sama, pilih ID kategori yang sesuai. Sektor regulasi tetap mengikuti sumber JDIH.</p>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-button name="action" value="save" variant="outline">Simpan Pilihan</x-button>
                    <x-button name="action" value="import">Simpan &amp; Impor</x-button>
                </div>
            </form>
        </x-card>
    @empty
        <x-card class="mt-5 text-center text-sm">Tidak ada dokumen yang perlu ditentukan jenisnya sesuai filter.</x-card>
    @endforelse
    <div class="mt-5">{{ $review['documents']->links() }}</div>
@endsection
