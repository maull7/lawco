@extends('layouts.app')
@section('title', $pageTitle ?? 'Putusan Mahkamah Agung')
@section('header', $pageTitle ?? 'Putusan Mahkamah Agung')
@section('content')
    @php
        $isArchivePage = ($listRoute ?? null) === 'legal-decisions.downloads';
    @endphp
    @if(!empty($scraper_error))
        <script>console.error('[Putusan MA] Live scraper gagal, menampilkan data lokal:', @js($scraper_error));</script>
    @endif
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-3xl font-bold text-[#071833]">{{ $pageTitle ?? 'Putusan Mahkamah Agung' }}</h2>
                @if(!empty($is_live))
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        Live Portal MA
                    </span>
                @endif
            </div>
            <p class="mt-2 text-sm text-[#667085]">{{ $pageDescription ?? 'Direktori database putusan hukum real-time dari Mahkamah Agung Republik Indonesia.' }}</p>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 lg:grid-cols-[320px_minmax(0,1fr)] gap-6 items-start">
        <div class="space-y-5">
            <!-- Form Pencarian (hanya arsip lokal; pencarian portal MA wajib captcha) -->
            @php
                $hasActiveFilter = !empty($filters['search']) || !empty($filters['kategori']) || !empty($filters['peradilan']) || !empty($filters['year']);
            @endphp
            @if($isArchivePage || $hasActiveFilter)
            <x-card>
                <h3 class="font-bold text-[#071833] text-sm flex items-center justify-between {{ $isArchivePage ? 'mb-3' : '' }}">
                    <span>{{ $isArchivePage ? 'Pencarian Kata Kunci' : 'Filter Aktif' }}</span>
                    @if($hasActiveFilter)
                        <a href="{{ route($listRoute ?? 'legal-decisions.index') }}" class="text-xs text-[#946d25] hover:underline font-normal">Reset Semua</a>
                    @endif
                </h3>
                @if($isArchivePage)
                <form method="GET" action="{{ route($listRoute ?? 'legal-decisions.index') }}" class="space-y-3">
                    <input type="hidden" name="kategori" value="{{ $filters['kategori'] ?? '' }}">
                    <input type="hidden" name="peradilan" value="{{ $filters['peradilan'] ?? '' }}">
                    <input type="hidden" name="periode" value="{{ $filters['periode'] ?? 'putus' }}">
                    <input type="hidden" name="year" value="{{ $filters['year'] ?? '' }}">
                    <div>
                        <input id="decision-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="250" placeholder="Nomor perkara / kata kunci..." class="input-premium w-full text-sm">
                    </div>
                    <x-button type="submit" variant="primary" class="w-full justify-center">Cari Putusan</x-button>
                </form>
                @endif
            </x-card>
            @endif

            <!-- Direktori Sidebar MA (Direktori, Peradilan, Tahun) -->
            @if(!empty($sidebar_filters))
                @foreach ($sidebar_filters as $card)
                    <x-card class="!p-4">
                        <h4 class="font-bold text-xs uppercase tracking-wider text-slate-500 mb-3 pb-2 border-b border-slate-100 flex items-center justify-between">
                            <span>{{ $card['title'] }}</span>
                        </h4>

                        @if(!empty($card['is_table']) && !empty($card['columns']))
                            <!-- Tabel 3 Kolom Tahun (Putus, Register, Upload) -->
                            <div class="overflow-x-auto -mx-2">
                                <table class="w-full text-xs text-left">
                                    <thead>
                                        <tr class="text-slate-400 font-semibold border-b border-slate-100">
                                            @foreach ($card['columns'] as $col)
                                                <th class="py-1 px-2 font-semibold text-center">{{ $col['name'] }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        @php
                                            $maxRows = max(array_map(fn($c) => count($c['items'] ?? []), $card['columns']));
                                        @endphp
                                        @for ($i = 0; $i < $maxRows; $i++)
                                            <tr>
                                                @foreach ($card['columns'] as $col)
                                                    @php $item = $col['items'][$i] ?? null; @endphp
                                                    <td class="py-1.5 px-2 text-center align-top">
                                                        @if($item)
                                                            @php
                                                                $isActiveYear = ($filters['year'] ?? '') == $item['tahun'] && ($filters['periode'] ?? 'putus') == $item['periode'];
                                                            @endphp
                                                            <a href="{{ route($listRoute ?? 'legal-decisions.index', array_merge(request()->query(), ['year' => $item['tahun'], 'periode' => $item['periode'], 'page' => 1])) }}"
                                                                class="block py-0.5 px-1 rounded transition-colors {{ $isActiveYear ? 'bg-[#946d25] text-white font-bold' : 'text-slate-700 hover:bg-slate-100' }}">
                                                                <div class="font-medium">{{ $item['label'] }}</div>
                                                                <div class="text-[10px] {{ $isActiveYear ? 'text-white/80' : 'text-slate-400' }}">{{ $item['count'] }}</div>
                                                            </a>
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endfor
                                    </tbody>
                                </table>
                            </div>
                        @elseif(!empty($card['items']))
                            <!-- Daftar List Badge (Direktori & Peradilan) -->
                            <div class="space-y-1 max-h-72 overflow-y-auto pr-1">
                                @foreach ($card['items'] as $item)
                                    @php
                                        $isSelected = false;
                                        $queryParams = request()->query();
                                        if (!empty($item['kategori'])) {
                                            $isSelected = ($filters['kategori'] ?? '') === $item['kategori'];
                                            $queryParams['kategori'] = $item['kategori'];
                                            // Saat memilih direktori/klasifikasi baru, reset filter peradilan/pengadilan spesifik agar tidak konflik
                                            if (in_array(strtolower($card['title']), ['direktori', 'klasifikasi'])) {
                                                unset($queryParams['peradilan']);
                                            }
                                        } elseif ($item['label'] === 'Semua Direktori') {
                                            $isSelected = empty($filters['kategori']);
                                            unset($queryParams['kategori']);
                                        }
                                        if (!empty($item['peradilan'])) {
                                            $isSelected = ($filters['peradilan'] ?? '') === $item['peradilan'];
                                            $queryParams['peradilan'] = $item['peradilan'];
                                        } elseif ($item['label'] === 'Semua Peradilan') {
                                            $isSelected = empty($filters['peradilan']);
                                            unset($queryParams['peradilan']);
                                        }
                                        $queryParams['page'] = 1;
                                    @endphp
                                    <a href="{{ route($listRoute ?? 'legal-decisions.index', $queryParams) }}"
                                        class="flex items-center justify-between text-xs py-1.5 px-2.5 rounded-lg transition-colors {{ $isSelected ? 'bg-[#946d25] text-white font-semibold' : 'text-slate-700 hover:bg-slate-50' }}">
                                        <span class="truncate">{{ $item['label'] }}</span>
                                        @if(!empty($item['count']))
                                            <span class="ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium {{ $isSelected ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' }}">
                                                {{ $item['count'] }}
                                            </span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </x-card>
                @endforeach
            @endif
        </div>

        <div x-data="putusanIndexManager()">
            <x-card>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                    <div class="flex items-center gap-3">
                        <p class="text-sm font-medium text-[#667085]">
                            <span class="font-bold text-[#071833]">{{ number_format($real_total_count ?? $decisions->total(), 0, ',', '.') }}</span> data putusan ditemukan
                        </p>
                        @if(!$isArchivePage)
                            <template x-if="selectedCount > 0">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-[#946d25]/15 text-[#946d25]">
                                    <span x-text="selectedCount"></span> putusan dipilih
                                </span>
                            </template>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        @if(!$isArchivePage)
                            {{-- Bulk Download Button (Hanya di Putusan MA Langsung) --}}
                            <button type="button"
                                @click="downloadSelected()"
                                :disabled="selectedCount === 0 || bulkLoading"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                                :class="selectedCount > 0 ? 'bg-[#946d25] text-white hover:bg-[#946d25]/90' : 'bg-slate-100 text-slate-400'">
                                <template x-if="bulkLoading">
                                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                </template>
                                <template x-if="!bulkLoading">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                    </svg>
                                </template>
                                <span x-text="bulkLoading ? 'Memproses antrian...' : (selectedCount > 0 ? 'Download Terpilih (' + selectedCount + ')' : 'Download Terpilih')"></span>
                            </button>
                        @endif

                        <a href="https://putusan3.mahkamahagung.go.id/beranda.html" target="_blank" rel="noopener noreferrer" class="text-xs font-semibold text-[#946d25] hover:underline flex items-center gap-1">
                            <span>Buka Portal MA Asli</span>
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                        </a>
                    </div>
                </div>

                {{-- Progress Bar Antrian Download Berurutan --}}
                <div x-show="queueRunning" x-cloak class="mb-4 p-4 rounded-xl bg-amber-50/70 border border-amber-200/80 shadow-sm space-y-2.5">
                    <div class="flex items-center justify-between text-xs">
                        <div class="flex items-center gap-2">
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                            </span>
                            <span class="font-bold text-[#071833]">Mengunduh Putusan Terpilih Berurutan</span>
                            <span class="text-slate-500">(<span x-text="queueCurrentIndex"></span> dari <span x-text="queueTotal"></span> selesai)</span>
                        </div>
                        <span class="font-mono font-bold text-[#946d25]" x-text="queuePercent + '%'"></span>
                    </div>

                    {{-- Bar Animasi --}}
                    <div class="w-full bg-amber-100 rounded-full h-2.5 overflow-hidden">
                        <div class="bg-gradient-to-r from-[#946d25] to-amber-500 h-2.5 rounded-full transition-all duration-300 ease-out"
                            :style="'width: ' + queuePercent + '%'"></div>
                    </div>

                    <div class="flex items-center justify-between text-[11px] text-slate-500">
                        <p class="truncate max-w-md">
                            <span class="text-slate-400">Sedang memproses:</span>
                            <span class="font-medium text-slate-700" x-text="queueCurrentTitle || 'Menyiapkan berkas...'"></span>
                        </p>
                        <span class="text-slate-400 italic">Otomatis berjalan satu per satu</span>
                    </div>
                </div>

                {{-- Alert Pesan Antrian Download --}}
                <div x-show="toastMessage" x-cloak x-transition class="mb-4 p-3 rounded-lg text-xs font-medium flex items-center justify-between"
                    :class="toastType === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200'">
                    <span x-text="toastMessage"></span>
                    <button type="button" @click="toastMessage = ''" class="ml-2 text-slate-400 hover:text-slate-600">&times;</button>
                </div>

                <div class="overflow-x-auto">
                    <table class="table-premium w-full">
                        <thead>
                            <tr>
                                @if(!$isArchivePage)
                                    <th class="w-10 text-center">
                                        <input type="checkbox"
                                            @change="toggleSelectAll($event.target.checked)"
                                            :checked="isAllSelected"
                                            class="w-4 h-4 rounded border-[#d0d5dd] text-[#946d25] focus:ring-[#946d25] cursor-pointer"
                                            title="Pilih semua putusan di halaman ini">
                                    </th>
                                @endif
                                <th>Nomor Putusan & Perkara</th>
                                <th>Lembaga</th>
                                <th>Klasifikasi</th>
                                <th>Tahun</th>
                                <th class="text-right pr-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($decisions as $dec)
                                <tr :class="isSelected(@js($dec->source_url)) ? 'bg-[#946d25]/5' : ''">
                                    @if(!$isArchivePage)
                                        <td class="text-center align-middle">
                                            @if(!empty($dec->source_url))
                                                <input type="checkbox"
                                                    :value="@js($dec->source_url)"
                                                    :checked="isSelected(@js($dec->source_url))"
                                                    @change="toggleSelect({
                                                        url: @js($dec->source_url),
                                                        decision_number: @js($dec->decision_number),
                                                        court_name: @js($dec->court_name ?? 'Mahkamah Agung'),
                                                        classification: @js($dec->classification ?? 'Putusan'),
                                                        year: @js($dec->year ?? null),
                                                        summary: @js($dec->summary ?? ''),
                                                        tgl_register: @js($dec->tgl_register ?? ''),
                                                        tgl_putus: @js($dec->tgl_putus ?? ''),
                                                        tgl_upload: @js($dec->tgl_upload ?? '')
                                                    }, $event.target.checked)"
                                                    class="w-4 h-4 rounded border-[#d0d5dd] text-[#946d25] focus:ring-[#946d25] cursor-pointer"
                                                    title="Pilih putusan ini">
                                            @endif
                                        </td>
                                    @endif
                                    <td class="max-w-md">
                                        <p class="font-bold text-[#071833]">{{ $dec->decision_number }}</p>
                                        @if($dec->summary && $dec->summary !== $dec->decision_number)
                                            <p class="text-xs text-slate-500 mt-1 line-clamp-2">{{ $dec->summary }}</p>
                                        @endif
                                        @if(!empty($dec->tgl_upload) || !empty($dec->tgl_putus) || !empty($dec->tgl_register))
                                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1.5 text-[11px] text-slate-500">
                                                @if(!empty($dec->tgl_register))
                                                    <span><span class="text-slate-400">Register:</span> {{ $dec->tgl_register }}</span>
                                                @endif
                                                @if(!empty($dec->tgl_putus))
                                                    <span><span class="text-slate-400">Putus:</span> {{ $dec->tgl_putus }}</span>
                                                @endif
                                                @if(!empty($dec->tgl_upload))
                                                    <span class="text-[#946d25] font-medium"><span class="text-[#946d25]/70">Upload:</span> {{ $dec->tgl_upload }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td><span class="text-xs font-medium px-2 py-0.5 rounded bg-slate-100 text-slate-700">{{ $dec->court_name ?? 'Mahkamah Agung' }}</span></td>
                                    <td><span class="text-xs text-slate-600">{{ $dec->classification ?? 'Putusan' }}</span></td>
                                    <td>{{ $dec->year ?? '—' }}</td>
                                    <td class="text-right pr-4">
                                        <div class="flex items-center justify-end gap-1.5">
                                            {{-- Tombol Preview --}}
                                            <button type="button"
                                                @click="openModal({
                                                    number: @js($dec->decision_number),
                                                    summary: @js($dec->summary ?? ''),
                                                    court: @js($dec->court_name ?? 'Mahkamah Agung'),
                                                    classification: @js($dec->classification ?? 'Putusan'),
                                                    year: @js($dec->year ?? '-'),
                                                    tglRegister: @js($dec->tgl_register ?? ''),
                                                    tglPutus: @js($dec->tgl_putus ?? ''),
                                                    tglUpload: @js($dec->tgl_upload ?? ''),
                                                    sourceUrl: @js($dec->source_url ?? ''),
                                                    pdfUrl: @js($dec->source_url ? route('legal-decisions.pdf', ['url' => $dec->source_url, 'title' => $dec->decision_number, 'court' => $dec->court_name ?? '', 'classification' => $dec->classification ?? '', 'year' => $dec->year ?? '', 'summary' => $dec->summary ?? '', 'tgl_register' => $dec->tgl_register ?? '', 'tgl_putus' => $dec->tgl_putus ?? '', 'tgl_upload' => $dec->tgl_upload ?? '']) : ($dec->file_url ?? '')),
                                                    isDownloaded: @js(! empty($dec->is_downloaded)),
                                                    downloadsUrl: @js(route('legal-decisions.downloads', ['search' => $dec->decision_number])),
                                                    downloadUrl: @js($dec->source_url ? route('legal-decisions.pdf', ['url' => $dec->source_url, 'download' => 1, 'archived' => $isArchivePage ? 1 : null, 'title' => $dec->decision_number, 'court' => $dec->court_name ?? '', 'classification' => $dec->classification ?? '', 'year' => $dec->year ?? '', 'summary' => $dec->summary ?? '', 'tgl_register' => $dec->tgl_register ?? '', 'tgl_putus' => $dec->tgl_putus ?? '', 'tgl_upload' => $dec->tgl_upload ?? '']) : ($dec->file_url ?? ''))
                                                })"
                                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-[#946d25]/10 text-[#946d25] hover:bg-[#946d25] hover:text-white transition-all shadow-sm group"
                                                title="Lihat ringkasan dan preview dokumen PDF">
                                                <svg class="w-3.5 h-3.5 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                <span>Preview</span>
                                            </button>

                                            {{-- Status Item Saat Antrian Sedang Berjalan (Hanya di Live Page) --}}
                                            @if(!$isArchivePage)
                                                <template x-if="queueRunning && itemStatus[@js($dec->source_url)]">
                                                    <div class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md text-xs font-semibold"
                                                        :class="{
                                                            'bg-amber-100 text-amber-800 animate-pulse': itemStatus[@js($dec->source_url)] === 'downloading',
                                                            'bg-emerald-100 text-emerald-800': itemStatus[@js($dec->source_url)] === 'done',
                                                            'bg-blue-100 text-blue-800': itemStatus[@js($dec->source_url)] === 'already',
                                                            'bg-red-100 text-red-800': itemStatus[@js($dec->source_url)] === 'error',
                                                            'bg-slate-100 text-slate-600': itemStatus[@js($dec->source_url)] === 'waiting'
                                                        }">
                                                        <span x-show="itemStatus[@js($dec->source_url)] === 'downloading'">Mengunduh...</span>
                                                        <span x-show="itemStatus[@js($dec->source_url)] === 'done'">Tersimpan</span>
                                                        <span x-show="itemStatus[@js($dec->source_url)] === 'already'">Sudah tersimpan</span>
                                                        <span x-show="itemStatus[@js($dec->source_url)] === 'error'">Gagal</span>
                                                        <span x-show="itemStatus[@js($dec->source_url)] === 'waiting'">Menunggu antrian</span>
                                                    </div>
                                                </template>
                                            @endif

                                            {{-- Tombol Download --}}
                                            @if(!$isArchivePage)
                                                <template x-if="!queueRunning || !itemStatus[@js($dec->source_url)]">
                                            @endif
                                            @if(! empty($dec->is_downloaded))
                                                <a href="{{ route('legal-decisions.downloads', ['search' => $dec->decision_number]) }}"
                                                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition-all"
                                                    title="Putusan ini sudah ada di Putusan MA (download)">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    <span>Sudah ada</span>
                                                </a>
                                            @elseif($dec->source_url || $dec->file_url)
                                                <a href="{{ $dec->source_url ? route('legal-decisions.pdf', array_filter(['url' => $dec->source_url, 'download' => 1, 'archived' => $isArchivePage ? 1 : null, 'title' => $dec->decision_number, 'court' => $dec->court_name ?? '', 'classification' => $dec->classification ?? '', 'year' => $dec->year ?? '', 'summary' => $dec->summary ?? '', 'tgl_register' => $dec->tgl_register ?? '', 'tgl_putus' => $dec->tgl_putus ?? '', 'tgl_upload' => $dec->tgl_upload ?? ''])) : $dec->file_url }}"
                                                    target="_blank"
                                                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-[#071833] text-white hover:bg-[#071833]/85 transition-all shadow-sm"
                                                    title="Download file PDF putusan">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                    </svg>
                                                    <span>Download</span>
                                                </a>
                                            @endif
                                            @if(!$isArchivePage)
                                                </template>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $isArchivePage ? 5 : 6 }}" class="text-center py-12">
                                        <div class="max-w-sm mx-auto text-center">
                                            <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                            </svg>
                                            <h4 class="font-semibold text-slate-700">Belum ada data putusan</h4>
                                            <p class="text-xs text-slate-500 mt-1">{{ $emptyMessage ?? 'Data putusan akan otomatis masuk setelah script sinkronisasi dijalankan.' }}</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($decisions->hasPages())
                    <div class="mt-6 pt-4 border-t border-[#e7eaf0]">
                        {{ $decisions->links('vendor.pagination.tailwind', ['realTotal' => $real_total_count ?? null]) }}
                    </div>
                @endif
            </x-card>

            {{-- Modal Preview Putusan (Ringkasan & PDF Viewer) dengan Teleport ke Body --}}
            <template x-teleport="body">
                <div x-show="isOpen"
                    x-cloak
                    @keydown.escape.window="closeModal()"
                    class="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-5 lg:p-6"
                    style="display: none;">
                    
                    {{-- Backdrop (menutupi seluruh layar termasuk sidebar) --}}
                    <div x-show="isOpen"
                        x-transition:enter="ease-out duration-300"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="ease-in duration-200"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        @click="closeModal()"
                        class="fixed inset-0 bg-[#071833]/60 backdrop-blur-sm transition-opacity"></div>

                    {{-- Dialog Panel --}}
                    <div x-show="isOpen"
                        x-transition:enter="ease-out duration-300"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="ease-in duration-200"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="relative w-full max-w-4xl transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all border border-slate-300 flex flex-col z-10 max-h-[calc(100dvh-1.5rem)] sm:max-h-[calc(100dvh-2.5rem)]">
                        
                        {{-- Modal Header --}}
                        <div class="flex items-start justify-between px-6 py-3.5 border-b border-slate-700 bg-[#071833] text-white shrink-0">
                            <div class="pr-6">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-[#946d25] text-white" x-text="activeDoc.court"></span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-white/10 text-slate-200" x-text="activeDoc.classification"></span>
                                    <span class="text-xs text-slate-300" x-text="'Tahun ' + activeDoc.year"></span>
                                </div>
                                <h3 class="text-base sm:text-lg font-bold leading-snug tracking-tight text-white line-clamp-1" x-text="activeDoc.number"></h3>
                            </div>
                            <button type="button" @click="closeModal()" class="text-slate-400 hover:text-white transition-colors p-1.5 rounded-lg hover:bg-white/10 shrink-0" title="Tutup">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        {{-- Tab Navigation --}}
                        <div class="flex items-center gap-1 px-6 pt-2 border-b border-slate-200 bg-slate-50 shrink-0">
                            <button type="button"
                                @click="activeTab = 'summary'"
                                :class="activeTab === 'summary' ? 'border-[#946d25] text-[#946d25] bg-white font-bold shadow-sm' : 'border-transparent text-slate-600 hover:text-slate-900 font-medium'"
                                class="inline-flex items-center gap-2 px-4 py-2 text-xs sm:text-sm border-b-2 rounded-t-lg transition-all">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                <span>Ringkasan Perkara</span>
                            </button>
                            <button type="button"
                                @click="activeTab = 'pdf'"
                                :class="activeTab === 'pdf' ? 'border-[#946d25] text-[#946d25] bg-white font-bold shadow-sm' : 'border-transparent text-slate-600 hover:text-slate-900 font-medium'"
                                class="inline-flex items-center gap-2 px-4 py-2 text-xs sm:text-sm border-b-2 rounded-t-lg transition-all">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                                <span>Preview Dokumen PDF</span>
                            </button>
                            <button type="button"
                                @click="activeTab = 'ai'"
                                :class="activeTab === 'ai' ? 'border-[#946d25] text-[#946d25] bg-white font-bold shadow-sm' : 'border-transparent text-slate-600 hover:text-slate-900 font-medium'"
                                class="inline-flex items-center gap-2 px-4 py-2 text-xs sm:text-sm border-b-2 rounded-t-lg transition-all">
                                <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                <span class="flex items-center gap-1.5">
                                    <span>Tanya AI Hukum</span>
                                    <span class="px-1.5 py-0.2 rounded text-[10px] font-extrabold bg-amber-100 text-amber-800 uppercase tracking-wider">GPT</span>
                                </span>
                            </button>
                        </div>

                        {{-- Modal Body --}}
                        <div class="relative flex-1 min-h-0 flex flex-col overflow-hidden bg-slate-900">
                            {{-- Tab 1: Ringkasan --}}
                            <div x-show="activeTab === 'summary'" class="p-5 sm:p-6 space-y-4 bg-slate-50 flex-1 min-h-0 overflow-y-auto overscroll-contain scroll-smooth">
                                {{-- Meta Badges --}}
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div class="p-3.5 rounded-xl bg-white border border-slate-200/80 shadow-sm">
                                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Tanggal Register</p>
                                        <p class="text-sm font-bold text-slate-800 mt-1" x-text="activeDoc.tglRegister || '—'"></p>
                                    </div>
                                    <div class="p-3.5 rounded-xl bg-white border border-slate-200/80 shadow-sm">
                                        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Tanggal Putus</p>
                                        <p class="text-sm font-bold text-slate-800 mt-1" x-text="activeDoc.tglPutus || '—'"></p>
                                    </div>
                                    <div class="p-3.5 rounded-xl bg-white border border-slate-200/80 shadow-sm">
                                        <p class="text-[11px] font-semibold text-[#946d25] uppercase tracking-wider">Tanggal Upload</p>
                                        <p class="text-sm font-bold text-[#946d25] mt-1" x-text="activeDoc.tglUpload || '—'"></p>
                                    </div>
                                </div>

                                {{-- Ringkasan Content --}}
                                <div class="p-4 rounded-xl bg-white border border-slate-200/80 shadow-sm space-y-2">
                                    <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider flex items-center gap-2">
                                        <svg class="w-4 h-4 text-[#946d25]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>Ringkasan / Catatan Putusan</span>
                                    </h4>
                                    <p class="text-sm text-slate-700 leading-relaxed whitespace-pre-line" x-text="activeDoc.summary || 'Tidak ada catatan ringkasan tambahan untuk perkara ini.'"></p>
                                </div>

                                {{-- Fast Action Card --}}
                                <div class="p-3.5 rounded-xl bg-amber-50/70 border border-amber-200/80 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-lg bg-[#946d25] text-white flex items-center justify-center font-bold shrink-0">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                                        </div>
                                        <div>
                                            <p class="text-xs font-bold text-slate-800">Lihat Salinan Resmi Dokumen Putusan</p>
                                            <p class="text-[11px] text-slate-500">Anda dapat langsung membaca dokumen PDF di tab berikutnya atau mengunduhnya.</p>
                                        </div>
                                    </div>
                                    <button type="button" @click="activeTab = 'pdf'" class="btn-primary !py-1.5 !px-3 text-xs self-start sm:self-auto whitespace-nowrap">
                                        Buka Tab PDF
                                    </button>
                                </div>
                            </div>

                            {{-- Tab 2: PDF Viewer --}}
                            <div x-show="activeTab === 'pdf'" class="w-full h-[70dvh] min-h-0 shrink relative flex flex-col bg-slate-900">
                                {{-- Loading Overlay --}}
                                <div x-show="pdfLoading" class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-slate-900/90 text-white space-y-3">
                                    <div class="w-10 h-10 border-4 border-[#946d25] border-t-transparent rounded-full animate-spin"></div>
                                    <p class="text-xs font-semibold tracking-wide text-slate-200">Mengambil dokumen PDF dari Mahkamah Agung...</p>
                                    <p class="text-[11px] text-slate-400">Proses ini membutuhkan beberapa detik pada pembukaan pertama kali.</p>
                                </div>

                                {{-- PDF Iframe --}}
                                <iframe x-ref="pdfFrame"
                                    :src="pdfSrc"
                                    @load="pdfLoading = false"
                                    class="w-full h-full border-0"
                                    title="PDF Putusan"></iframe>
                            </div>

                            {{-- Tab 3: Bedah & Tanya AI Hukum --}}
                            <div x-show="activeTab === 'ai'" class="w-full h-[70dvh] min-h-0 shrink relative flex flex-col bg-slate-50">
                                {{-- Header Konteks Perkara AI --}}
                                <div class="px-5 py-3 bg-white border-b border-slate-200 flex items-center justify-between gap-3 shrink-0">
                                    <div class="flex items-center gap-2.5 overflow-hidden">
                                        <div class="w-8 h-8 rounded-lg bg-amber-50 border border-amber-200 text-[#946d25] flex items-center justify-center shrink-0 text-xs font-bold shadow-2xs">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                            </svg>
                                        </div>
                                        <div class="min-w-0">
                                            <h4 class="text-xs font-bold text-slate-900 truncate" x-text="'Telaah Perkara: ' + activeDoc.number"></h4>
                                            <p class="text-[11px] text-slate-500">Analisis ratio decidendi, amar pertimbangan, dan kaidah hukum dengan ChatGPT</p>
                                        </div>
                                    </div>
                                    <button type="button" @click="resetAiChat()" class="text-[11px] text-slate-500 hover:text-slate-700 underline font-medium shrink-0">
                                        Mulai Ulang Chat
                                    </button>
                                </div>

                                {{-- Chat Messages Container --}}
                                <div x-ref="aiChatMessages" class="flex-grow p-4 sm:p-5 overflow-y-auto space-y-3.5 text-xs">
                                    {{-- Bot Welcome Bubble --}}
                                    <div class="flex items-start gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-[#071833] text-amber-400 flex items-center justify-center shrink-0 text-xs shadow-sm">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                            </svg>
                                        </div>
                                        <div class="bg-white p-4 rounded-2xl rounded-tl-none border border-slate-200 shadow-2xs text-slate-800 space-y-3 max-w-2xl leading-relaxed">
                                            <p class="font-bold text-slate-900 flex items-center gap-1.5">
                                                Halo! Saya siap membedah dokumen putusan perkara ini untuk Anda.
                                            </p>
                                            <p class="text-slate-600">
                                                Pilih telaah cepat berikut atau ketik pertanyaan hukum spesifik mengenai berkas perkara ini:
                                            </p>
                                            <div class="pt-1 flex flex-wrap gap-2">
                                                <button type="button"
                                                    @click="sendAiPrompt('Bedah dan jelaskan secara ringkas amar putusan perkara ini beserta implikasi hukumnya.')"
                                                    class="px-2.5 py-1.5 rounded-lg bg-slate-50 hover:bg-amber-50 hover:text-amber-950 text-slate-700 text-[11px] font-medium border border-slate-200 transition flex items-center gap-1.5 cursor-pointer">
                                                    <span>⚖️ Bedah Amar Putusan</span>
                                                </button>
                                                <button type="button"
                                                    @click="sendAiPrompt('Jelaskan konstruksi pertimbangan hukum hakim (ratio decidendi) dalam menjatuhkan putusan ini.')"
                                                    class="px-2.5 py-1.5 rounded-lg bg-slate-50 hover:bg-amber-50 hover:text-amber-950 text-slate-700 text-[11px] font-medium border border-slate-200 transition flex items-center gap-1.5 cursor-pointer">
                                                    <span>🧠 Pertimbangan Hukum Hakim</span>
                                                </button>
                                                <button type="button"
                                                    @click="sendAiPrompt('Apakah perkara ini memuat kaidah hukum penting atau berkaitan dengan yurisprudensi tetap MA terdahulu?')"
                                                    class="px-2.5 py-1.5 rounded-lg bg-slate-50 hover:bg-amber-50 hover:text-amber-950 text-slate-700 text-[11px] font-medium border border-slate-200 transition flex items-center gap-1.5 cursor-pointer">
                                                    <span>📜 Kaidah & Yurisprudensi</span>
                                                </button>
                                                <button type="button"
                                                    @click="sendAiPrompt('Bagaimana peluang dan analisis dasar hukum jika pihak yang kalah mengajukan upaya hukum lanjutan (Banding / Kasasi / PK)?')"
                                                    class="px-2.5 py-1.5 rounded-lg bg-slate-50 hover:bg-amber-50 hover:text-amber-950 text-slate-700 text-[11px] font-medium border border-slate-200 transition flex items-center gap-1.5 cursor-pointer">
                                                    <span>🛡️ Potensi Upaya Hukum</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Dinamis Chat Messages --}}
                                    <template x-for="(msg, index) in aiMessages" :key="index">
                                        <div>
                                            {{-- User Message --}}
                                            <div x-show="msg.role === 'user'" class="flex items-start justify-end gap-2.5">
                                                <div class="bg-[#071833] text-white p-3.5 rounded-2xl rounded-tr-none shadow-sm max-w-xl leading-relaxed text-xs whitespace-pre-wrap" x-text="msg.content"></div>
                                                <div class="w-7 h-7 rounded-lg bg-amber-100 text-[#946d25] font-bold flex items-center justify-center shrink-0 text-[10px] border border-amber-300">
                                                    Anda
                                                </div>
                                            </div>

                                            {{-- Assistant Message --}}
                                            <div x-show="msg.role === 'assistant'" class="flex items-start gap-3 mt-3">
                                                <div class="w-8 h-8 rounded-lg bg-[#071833] text-amber-400 flex items-center justify-center shrink-0 text-xs shadow-sm">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z" />
                                                    </svg>
                                                </div>
                                                <div class="bg-white p-4 rounded-2xl rounded-tl-none border border-slate-200 shadow-2xs text-slate-800 space-y-2 max-w-2xl leading-relaxed prose prose-sm prose-slate max-w-none text-xs" x-html="renderMarkdown(msg.content)"></div>
                                            </div>
                                        </div>
                                    </template>

                                    {{-- Loading indicator --}}
                                    <div x-show="aiLoading" class="flex items-start gap-3">
                                        <div class="w-8 h-8 rounded-lg bg-[#071833] text-amber-400 flex items-center justify-center shrink-0 text-xs shadow-sm animate-pulse">
                                            <svg class="w-4 h-4 animate-spin text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                        </div>
                                        <div class="bg-white p-3.5 rounded-2xl rounded-tl-none border border-slate-200 shadow-2xs text-slate-500 italic max-w-lg leading-relaxed text-xs flex items-center gap-2">
                                            <span>Menganalisis berkas perkara dengan ChatGPT...</span>
                                        </div>
                                    </div>
                                </div>

                                {{-- Input Pertanyaan AI --}}
                                <div class="p-3 sm:p-4 bg-white border-t border-slate-200 shrink-0">
                                    <form @submit.prevent="submitAiChat()" class="space-y-2">
                                        <div class="flex items-center gap-2">
                                            <input type="text"
                                                x-model="aiInputPrompt"
                                                :disabled="aiLoading"
                                                placeholder="Tanyakan hal spesifik tentang amar, ratio decidendi, atau kaidah perkara ini..."
                                                class="flex-grow bg-slate-50 border border-slate-300 rounded-xl px-4 py-2.5 text-xs text-slate-900 placeholder-slate-400 focus:outline-none focus:border-[#946d25] focus:bg-white focus:ring-1 focus:ring-[#946d25] transition disabled:opacity-50">
                                            <button type="submit"
                                                :disabled="aiLoading || !aiInputPrompt.trim()"
                                                class="px-4 py-2.5 rounded-xl bg-[#071833] hover:bg-[#071833]/90 text-white font-semibold text-xs transition flex items-center gap-1.5 shrink-0 shadow-sm disabled:opacity-40 disabled:cursor-not-allowed">
                                                <span>Kirim</span>
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                                </svg>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        {{-- Modal Footer --}}
                        <div class="px-6 py-3.5 border-t border-slate-200 bg-white flex flex-col sm:flex-row items-center justify-between gap-3 shrink-0">
                            <div class="text-xs text-slate-500 flex items-center gap-2">
                                <template x-if="activeDoc.sourceUrl">
                                    <a :href="activeDoc.sourceUrl" target="_blank" rel="noopener noreferrer" class="text-[#946d25] hover:underline font-semibold flex items-center gap-1">
                                        <span>Sumber Asli Portal MA</span>
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                    </a>
                                </template>
                            </div>

                            <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                                <template x-if="activeDoc.pdfUrl">
                                    <a :href="activeDoc.pdfUrl" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold bg-slate-100 text-slate-700 hover:bg-slate-200 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                        <span>Buka di Tab Baru</span>
                                    </a>
                                </template>

                                <template x-if="activeDoc.isDownloaded">
                                    <a :href="activeDoc.downloadsUrl" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-bold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        <span>File sudah ada di Putusan MA (download)</span>
                                    </a>
                                </template>

                                <template x-if="activeDoc.downloadUrl && !activeDoc.isDownloaded">
                                    <a :href="activeDoc.downloadUrl" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-xs font-bold bg-[#071833] text-white hover:bg-[#071833]/85 transition-colors shadow-sm">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                        <span>Download PDF</span>
                                    </a>
                                </template>

                                <button type="button" @click="closeModal()" class="px-3.5 py-2 rounded-lg text-xs font-medium text-slate-600 hover:bg-slate-100 transition-colors">
                                    Tutup
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </template>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
        function putusanIndexManager() {
            return {
                // Bulk Selection State
                selectedDecisions: {},
                bulkLoading: false,
                toastMessage: '',
                toastType: 'success',
                _toastTimer: null,

                // Animasi Antrian Berurutan (Sequential Queue Animation)
                queueRunning: false,
                queueTotal: 0,
                queueCurrentIndex: 0,
                queueCurrentTitle: '',
                queuePercent: 0,
                itemStatus: {},

                // Modal & AI Preview State
                isOpen: false,
                activeTab: 'summary',
                pdfLoading: false,
                pdfSrc: '',
                aiMessages: [],
                aiInputPrompt: '',
                aiLoading: false,
                activeDoc: {
                    number: '',
                    summary: '',
                    court: '',
                    classification: '',
                    year: '',
                    tglRegister: '',
                    tglPutus: '',
                    tglUpload: '',
                    sourceUrl: '',
                    pdfUrl: '',
                    downloadUrl: '',
                    downloadsUrl: '',
                    isDownloaded: false
                },

                get selectedCount() {
                    return Object.keys(this.selectedDecisions).length;
                },

                isSelected(url) {
                    return !!(url && this.selectedDecisions[url]);
                },

                toggleSelect(item, checked) {
                    if (!item || !item.url) return;
                    if (checked) {
                        this.selectedDecisions[item.url] = item;
                    } else {
                        delete this.selectedDecisions[item.url];
                    }
                },

                get pageItems() {
                    const items = [];
                    @foreach ($decisions as $dec)
                        @if(!empty($dec->source_url))
                            items.push({
                                url: @js($dec->source_url),
                                decision_number: @js($dec->decision_number),
                                court_name: @js($dec->court_name ?? 'Mahkamah Agung'),
                                classification: @js($dec->classification ?? 'Putusan'),
                                year: @js($dec->year ?? null),
                                summary: @js($dec->summary ?? ''),
                                tgl_register: @js($dec->tgl_register ?? ''),
                                tgl_putus: @js($dec->tgl_putus ?? ''),
                                tgl_upload: @js($dec->tgl_upload ?? '')
                            });
                        @endif
                    @endforeach
                    return items;
                },

                get isAllSelected() {
                    const items = this.pageItems;
                    if (items.length === 0) return false;
                    return items.every(item => !!this.selectedDecisions[item.url]);
                },

                toggleSelectAll(checked) {
                    const items = this.pageItems;
                    if (checked) {
                        items.forEach(item => {
                            this.selectedDecisions[item.url] = item;
                        });
                    } else {
                        items.forEach(item => {
                            delete this.selectedDecisions[item.url];
                        });
                    }
                },

                showToast(msg, type = 'success') {
                    this.toastMessage = msg;
                    this.toastType = type;
                    clearTimeout(this._toastTimer);
                    this._toastTimer = setTimeout(() => {
                        this.toastMessage = '';
                    }, 5000);
                },

                async downloadSelected() {
                    const items = Object.values(this.selectedDecisions);
                    if (items.length === 0 || this.bulkLoading || this.queueRunning) return;

                    this.bulkLoading = true;
                    this.queueRunning = true;
                    this.queueTotal = items.length;
                    this.queueCurrentIndex = 0;
                    this.queuePercent = 0;
                    this.itemStatus = {};

                    // Inisialisasi status awal antrian untuk semua item yang dipilih
                    items.forEach(it => {
                        this.itemStatus[it.url] = 'waiting';
                    });

                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
                    let successCount = 0;
                    let alreadyCount = 0;
                    let errorCount = 0;

                    for (let i = 0; i < items.length; i++) {
                        const item = items[i];
                        this.queueCurrentIndex = i + 1;
                        this.queueCurrentTitle = item.decision_number || ('Putusan ke-' + (i + 1));
                        this.itemStatus[item.url] = 'downloading';

                        try {
                            const res = await fetch('{{ route("legal-decisions.download-single") }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': token
                                },
                                body: JSON.stringify(item)
                            });

                            const data = await res.json();
                            if (res.ok && data.success) {
                                if (data.already_exists) {
                                    this.itemStatus[item.url] = 'already';
                                    alreadyCount++;
                                } else {
                                    this.itemStatus[item.url] = 'done';
                                    successCount++;
                                }
                            } else {
                                this.itemStatus[item.url] = 'error';
                                errorCount++;
                            }
                        } catch (err) {
                            this.itemStatus[item.url] = 'error';
                            errorCount++;
                        }

                        this.queuePercent = Math.round(((i + 1) / items.length) * 100);
                        // Delay jeda visual singkat antar unduhan
                        await new Promise(r => setTimeout(r, 450));
                    }

                    this.bulkLoading = false;
                    this.selectedDecisions = {};

                    let summaryMsg = `Selesai! ${successCount} putusan berhasil diunduh.`;
                    if (alreadyCount > 0) summaryMsg += ` (${alreadyCount} sudah ada sebelumnya)`;
                    if (errorCount > 0) summaryMsg += ` (${errorCount} gagal diunduh)`;

                    this.showToast(summaryMsg, errorCount > 0 && successCount === 0 ? 'error' : 'success');

                    setTimeout(() => {
                        this.queueRunning = false;
                        this.queueCurrentTitle = '';
                    }, 4000);
                },

                openModal(doc) {
                    this.activeDoc = doc;
                    this.activeTab = 'summary';
                    this.isOpen = true;
                    this.pdfLoading = true;
                    this.pdfSrc = doc.pdfUrl || '';
                    this.aiMessages = [];
                    this.aiInputPrompt = '';
                    this.aiLoading = false;
                    document.body.classList.add('overflow-hidden');
                },
                closeModal() {
                    this.isOpen = false;
                    this.pdfSrc = '';
                    document.body.classList.remove('overflow-hidden');
                },
                resetAiChat() {
                    this.aiMessages = [];
                    this.aiInputPrompt = '';
                    this.aiLoading = false;
                },
                renderMarkdown(text) {
                    if (typeof marked !== 'undefined' && marked.parse) {
                        try {
                            return marked.parse(text || '');
                        } catch (e) {
                            console.error(e);
                        }
                    }
                    return (text || '').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>');
                },
                sendAiPrompt(promptText) {
                    this.aiInputPrompt = promptText;
                    this.submitAiChat();
                },
                async submitAiChat() {
                    const prompt = this.aiInputPrompt.trim();
                    if (!prompt || this.aiLoading) return;

                    this.aiMessages.push({ role: 'user', content: prompt });
                    this.aiInputPrompt = '';
                    this.aiLoading = true;

                    this.$nextTick(() => {
                        this.scrollAiChatToBottom();
                    });

                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
                        const response = await fetch('{{ route("legal-decisions.ai-chat") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': token
                            },
                            body: JSON.stringify({
                                message: prompt,
                                context_putusan: this.activeDoc,
                                history: this.aiMessages.slice(-6)
                            })
                        });

                        const result = await response.json();
                        if (result.success && result.reply) {
                            this.aiMessages.push({ role: 'assistant', content: result.reply });
                        } else {
                            this.aiMessages.push({
                                role: 'assistant',
                                content: '⚠️ **Maaf**, ' + (result.message || 'Terjadi kendala saat menghubungkan ke AI.')
                            });
                        }
                    } catch (err) {
                        this.aiMessages.push({
                            role: 'assistant',
                            content: '⚠️ **Terjadi Kesalahan Jaringan**: ' + (err.message || 'Tidak dapat menghubungi server.')
                        });
                    } finally {
                        this.aiLoading = false;
                        this.$nextTick(() => {
                            this.scrollAiChatToBottom();
                        });
                    }
                },
                scrollAiChatToBottom() {
                    const container = this.$refs.aiChatMessages;
                    if (container) {
                        container.scrollTop = container.scrollHeight;
                    }
                }
            };
        }
    </script>
    @endpush
@endsection

