@extends('layouts.app')
@section('title', $pageTitle ?? 'Putusan Mahkamah Agung')
@section('header', $pageTitle ?? 'Putusan Mahkamah Agung')
@section('content')
    @php
        $isArchivePage = ($listRoute ?? null) === 'legal-decisions.downloads';
    @endphp
    @if(!empty($scraper_error))
        <script>console.error('[Putusan MA] Live scraper info:', @js($scraper_error));</script>
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
            <!-- Filter Status Download (Khusus Putusan MA Langsung) -->
            @if(!$isArchivePage)
                <x-card class="!p-4">
                    <h4 class="font-bold text-xs uppercase tracking-wider text-slate-500 mb-3 pb-2 border-b border-slate-100 flex items-center justify-between">
                        <span>Status Download</span>
                        @if(!empty($filters['download_status']))
                            @php
                                $resetDownloadParams = request()->query();
                                unset($resetDownloadParams['download_status']);
                                $resetDownloadParams['page'] = 1;
                            @endphp
                            <a href="{{ route($listRoute ?? 'legal-decisions.index', $resetDownloadParams) }}" class="text-[11px] text-[#946d25] hover:underline font-normal capitalize">Reset</a>
                        @endif
                    </h4>
                    <div class="space-y-1">
                        @php
                            $currentStatus = $filters['download_status'] ?? '';
                            $statusOptions = [
                                '' => ['label' => 'Semua Putusan', 'icon' => 'M4 6h16M4 12h16M4 18h16'],
                                'not_downloaded' => ['label' => 'Belum Di-download', 'icon' => 'M12 4v16m8-8H4'],
                                'downloaded' => ['label' => 'Sudah Di-download', 'icon' => 'M5 13l4 4L19 7'],
                            ];
                        @endphp
                        @foreach ($statusOptions as $val => $opt)
                            @php
                                $statusParams = request()->query();
                                if ($val === '') {
                                    unset($statusParams['download_status']);
                                } else {
                                    $statusParams['download_status'] = $val;
                                }
                                $statusParams['page'] = 1;
                                $isStatusSelected = $currentStatus === $val;
                            @endphp
                            <a href="{{ route($listRoute ?? 'legal-decisions.index', $statusParams) }}"
                                class="flex items-center justify-between text-xs py-2 px-2.5 rounded-lg transition-colors {{ $isStatusSelected ? 'bg-[#946d25] text-white font-semibold shadow-xs' : 'text-slate-700 hover:bg-slate-50' }}">
                                <span class="flex items-center gap-2">
                                    <svg class="w-3.5 h-3.5 opacity-75" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $opt['icon'] }}" />
                                    </svg>
                                    <span>{{ $opt['label'] }}</span>
                                </span>
                                @if($isStatusSelected)
                                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-white"></span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </x-card>
            @else
                {{-- Filter Pengunduh (Khusus Halaman Download/Arsip) --}}
                @if(!empty($userOptions) && $userOptions->isNotEmpty())
                    <x-card class="!p-4">
                        <h4 class="font-bold text-xs uppercase tracking-wider text-slate-500 mb-3 pb-2 border-b border-slate-100 flex items-center justify-between">
                            <span>Filter Pengunduh</span>
                            @if(!empty($selectedUserId))
                                @php
                                    $resetUserParams = request()->query();
                                    unset($resetUserParams['user_id']);
                                    $resetUserParams['page'] = 1;
                                @endphp
                                <a href="{{ route($listRoute ?? 'legal-decisions.downloads', $resetUserParams) }}" class="text-[11px] text-[#946d25] hover:underline font-normal">Semua</a>
                            @endif
                        </h4>
                        <div class="space-y-1">
                            @php
                                $isAllUsers = empty($selectedUserId);
                                $allUserParams = request()->query();
                                unset($allUserParams['user_id']);
                                $allUserParams['page'] = 1;
                            @endphp
                            <a href="{{ route($listRoute ?? 'legal-decisions.downloads', $allUserParams) }}"
                                class="flex items-center justify-between text-xs py-2 px-2.5 rounded-lg transition-colors {{ $isAllUsers ? 'bg-[#946d25] text-white font-semibold shadow-xs' : 'text-slate-700 hover:bg-slate-50' }}">
                                <span class="flex items-center gap-2">
                                    <svg class="w-3.5 h-3.5 opacity-75" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                    <span>Semua Pengguna</span>
                                </span>
                                <span class="text-[11px] px-1.5 py-0.5 rounded font-bold {{ $isAllUsers ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' }}">
                                    {{ number_format($summaryStats->total_success ?? 0, 0, ',', '.') }}
                                </span>
                            </a>

                            @foreach ($userOptions as $uStat)
                                @if($uStat->user_id > 0)
                                    @php
                                        $isThisSelected = ((string) ($selectedUserId ?? '')) === ((string) $uStat->user_id);
                                        $uParams = request()->query();
                                        $uParams['user_id'] = $uStat->user_id;
                                        $uParams['page'] = 1;
                                    @endphp
                                    <a href="{{ route($listRoute ?? 'legal-decisions.downloads', $uParams) }}"
                                        class="flex items-center justify-between text-xs py-2 px-2.5 rounded-lg transition-colors {{ $isThisSelected ? 'bg-[#946d25] text-white font-semibold shadow-xs' : 'text-slate-700 hover:bg-slate-50' }}">
                                        <span class="truncate mr-2" title="{{ $uStat->name }} ({{ $uStat->email }})">{{ $uStat->name }}</span>
                                        <span class="shrink-0 text-[11px] px-1.5 py-0.5 rounded font-bold {{ $isThisSelected ? 'bg-white/20 text-white' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}">
                                            {{ number_format($uStat->success_count, 0, ',', '.') }}
                                        </span>
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </x-card>
                @endif
            @endif

            <!-- Form Pencarian (hanya arsip lokal; pencarian portal MA wajib captcha) -->
            @php
                $hasActiveFilter = !empty($filters['search']) || !empty($filters['kategori']) || !empty($filters['peradilan']) || !empty($filters['year']) || !empty($filters['download_status']) || !empty($filters['user_id']);
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
                    @if(!empty($filters['user_id']))
                        <input type="hidden" name="user_id" value="{{ $filters['user_id'] }}">
                    @endif
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

        <div x-data="putusanIndexManager()" class="space-y-6">
            {{-- Rekapitulasi Hasil Download per User (Khusus Halaman Download/Arsip) --}}
            @if($isArchivePage && !empty($userDownloadStats))
                <x-card class="!p-5 border-slate-200/80 shadow-xs overflow-visible!">
                    <div class="flex flex-col gap-3 pb-4 mb-4 border-b border-slate-100">
                        <div class="flex items-start gap-3 min-w-0">
                            <span class="p-2 rounded-xl bg-[#946d25]/10 text-[#946d25] shrink-0 mt-0.5">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                            </span>
                            <div class="min-w-0">
                                <h3 class="text-base font-bold text-[#071833]">Rekap Hasil Download per Pengguna</h3>
                                <p class="text-xs text-[#667085] mt-0.5">Statistik ringkasan riwayat pengunduhan putusan MA (berhasil, gagal, dan total) oleh setiap akun pengguna.</p>
                            </div>
                        </div>

                        {{-- Filter rekap: tanggal & jam (WIB) berdasarkan waktu status terakhir file --}}
                        <form method="GET" action="{{ route($listRoute ?? 'legal-decisions.downloads') }}"
                            class="flex flex-wrap items-center gap-2.5 p-2 rounded-xl bg-slate-50 border border-slate-200/80 shrink-0">
                            @foreach(request()->except(['recap_from', 'recap_to', 'recap_time_from', 'recap_time_to', 'page']) as $queryKey => $queryValue)
                                @if(is_scalar($queryValue) && $queryValue !== '')
                                    <input type="hidden" name="{{ $queryKey }}" value="{{ $queryValue }}">
                                @endif
                            @endforeach

                            <div class="flex items-center gap-1.5">
                                <span class="text-xs font-semibold text-slate-500 whitespace-nowrap">Tanggal:</span>
                                <x-date-range-picker from-name="recap_from" to-name="recap_to" :from="$filters['recap_from'] ?? null" :to="$filters['recap_to'] ?? null" placeholder="Semua Tanggal" />
                            </div>

                            <span class="text-slate-300 hidden sm:inline">|</span>

                            <div class="flex items-center gap-1.5">
                                <label for="recap_time_from" class="text-xs font-semibold text-slate-500 whitespace-nowrap">Jam (WIB):</label>
                                <div class="inline-flex items-center gap-1 bg-white border border-[#e7eaf0] rounded-lg px-2 py-0.5 shadow-xs focus-within:ring-2 focus-within:ring-[#946d25]/20 focus-within:border-[#946d25]">
                                    <input type="time" id="recap_time_from" name="recap_time_from" value="{{ $filters['recap_time_from'] ?? '' }}" aria-label="Jam awal"
                                        class="w-18 bg-transparent text-xs text-[#071833] focus:outline-none">
                                    <span class="text-xs text-slate-400">–</span>
                                    <input type="time" name="recap_time_to" value="{{ $filters['recap_time_to'] ?? '' }}" aria-label="Jam akhir"
                                        class="w-18 bg-transparent text-xs text-[#071833] focus:outline-none">
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5">
                                <button type="submit" class="h-8 px-3.5 inline-flex items-center justify-center rounded-lg text-xs font-semibold bg-[#946d25] text-white hover:bg-[#946d25]/90 transition shadow-xs">
                                    Terapkan
                                </button>
                                @if(!empty($filters['recap_from']) || !empty($filters['recap_time_from']) || !empty($filters['recap_time_to']))
                                    <a href="{{ route($listRoute ?? 'legal-decisions.downloads', request()->except(['recap_from', 'recap_to', 'recap_time_from', 'recap_time_to', 'page'])) }}"
                                        class="h-8 px-3 inline-flex items-center justify-center rounded-lg text-xs font-semibold border border-slate-300 bg-white text-slate-600 hover:bg-slate-100 transition whitespace-nowrap">
                                        Reset
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>

                    {{-- Tabel Rekapitulasi User --}}
                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-slate-50 text-slate-600 uppercase font-semibold text-[11px] tracking-wider border-b border-slate-200">
                                <tr>
                                    <th class="py-3 px-4">Nama Pengguna</th>
                                    <th class="py-3 px-4 text-center">Berhasil Di-download</th>
                                    <th class="py-3 px-4 text-center">Gagal Download</th>
                                    <th class="py-3 px-4 text-center">Total Percobaan</th>
                                    <th class="py-3 px-4 text-center" title="Total percobaan dibagi durasi aktif (unduhan pertama s.d. terakhir) dalam periode">Rata-rata (file/menit)</th>
                                    <th class="py-3 px-4 text-center border-l border-slate-200">
                                        Jam Ini
                                        <span class="block normal-case font-normal text-[10px] text-slate-400">{{ $currentHourLabel ?? '' }} (Berhasil / Gagal)</span>
                                    </th>
                                    @if($canSendWorkAlert ?? false)
                                        <th class="py-3 px-4 text-center">Peringatan</th>
                                    @endif
                                </tr>
                                <tr class="normal-case tracking-normal">
                                    <th colspan="5" class="pb-2 px-4 font-normal text-[11px] text-slate-500 text-center">
                                        Periode: <span class="font-semibold text-[#946d25]">{{ $recapPeriodLabel ?? 'Semua Waktu' }}</span>
                                    </th>
                                    <th class="border-l border-slate-200"></th>
                                    @if($canSendWorkAlert ?? false)
                                        <th></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse ($userDownloadStats as $stat)
                                    @php
                                        $isThisUserActive = ((string) ($selectedUserId ?? '')) === ((string) $stat->user_id);
                                        $isCurrentUser = ($currentUserId ?? 0) === $stat->user_id;
                                    @endphp
                                    <tr class="hover:bg-slate-50 transition-colors {{ $isThisUserActive ? 'bg-[#946d25]/5' : '' }}">
                                        <td class="py-3 px-4 align-middle">
                                            <div class="flex items-center gap-3">
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs shrink-0 {{ $isThisUserActive ? 'bg-[#946d25] text-white shadow-xs' : 'bg-slate-100 text-slate-700' }}">
                                                    {{ strtoupper(substr($stat->name, 0, 1)) }}
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="text-[#071833] font-semibold flex items-center gap-2">
                                                        <span class="truncate">{{ $stat->name }}</span>
                                                        @if($isThisUserActive)
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-[#946d25] text-white shrink-0">Aktif</span>
                                                        @endif
                                                        @if($isCurrentUser)
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600 shrink-0">Anda</span>
                                                        @endif
                                                    </div>
                                                    <div class="text-[11px] text-slate-400 font-normal truncate">{{ $stat->email }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4 text-center align-middle">
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                                {{ number_format($stat->success_count, 0, ',', '.') }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-center align-middle">
                                            @if($stat->failure_count > 0)
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                                    <svg class="w-3.5 h-3.5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                                    {{ number_format($stat->failure_count, 0, ',', '.') }}
                                                </span>
                                            @else
                                                <span class="text-slate-400 font-medium">0</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-4 text-center align-middle">
                                            <span class="text-xs font-bold text-slate-800">
                                                {{ number_format($stat->total_count, 0, ',', '.') }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-center align-middle font-semibold text-slate-700">
                                            {{ number_format($stat->per_minute, 0, ',', '.') }} <span class="text-[11px] font-normal text-slate-400">file/menit</span>
                                        </td>
                                        <td class="py-3 px-4 text-center align-middle border-l border-slate-100 whitespace-nowrap">
                                            <span class="font-bold text-emerald-700">{{ number_format($stat->hour_success_count, 0, ',', '.') }}</span>
                                            <span class="text-slate-300 mx-1">/</span>
                                            <span class="font-bold {{ $stat->hour_failure_count > 0 ? 'text-rose-700' : 'text-slate-400' }}">{{ number_format($stat->hour_failure_count, 0, ',', '.') }}</span>
                                        </td>
                                        @if($canSendWorkAlert ?? false)
                                            <td class="py-3 px-4 text-center align-middle">
                                                @if($stat->user_id > 0)
                                                    <button type="button"
                                                        data-url="{{ route('legal-decisions.work-alert.send', $stat->user_id) }}"
                                                        data-label="{{ $isCurrentUser ? 'Tes Alert' : 'Peringatkan' }}"
                                                        title="{{ $isCurrentUser ? 'Uji coba: bunyikan peringatan di browser Anda sendiri (muncul dalam 15 detik)' : 'Bunyikan peringatan suara di browser '.$stat->name }}"
                                                        x-data="workAlertButton()"
                                                        x-on:click="send"
                                                        x-bind:disabled="sending"
                                                        x-bind:class="sent ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-800 border-amber-200 hover:bg-amber-100'"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold border transition disabled:opacity-50">
                                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5L6 9H2v6h4l5 4V5zm4.54 3.46a5 5 0 010 7.07M19.07 4.93a10 10 0 010 14.14" /></svg>
                                                        <span x-text="text">{{ $isCurrentUser ? 'Tes Alert' : 'Peringatkan' }}</span>
                                                    </button>
                                                @else
                                                    <span class="text-[11px] text-slate-400">-</span>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ ($canSendWorkAlert ?? false) ? 7 : 6 }}" class="py-6 text-center text-slate-400">{{ !empty($filters['recap_from']) ? 'Tidak ada aktivitas download pada tanggal/jam yang dipilih.' : 'Belum ada riwayat aktivitas download pengguna.' }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            @if(!empty($userAverages) && $userDownloadStats->count() > 1)
                                <tfoot class="bg-slate-50 border-t border-slate-200 text-xs font-semibold text-slate-700">
                                    <tr>
                                        <td class="py-3 px-4">Rata-rata per Pengguna</td>
                                        <td class="py-3 px-4 text-center text-emerald-700">{{ number_format($userAverages->success, 0, ',', '.') }}</td>
                                        <td class="py-3 px-4 text-center text-rose-700">{{ number_format($userAverages->failure, 0, ',', '.') }}</td>
                                        <td class="py-3 px-4 text-center">{{ number_format($userAverages->total, 0, ',', '.') }}</td>
                                        <td class="py-3 px-4 text-center">{{ number_format($userAverages->per_minute, 0, ',', '.') }} <span class="text-[11px] font-normal text-slate-400">file/menit</span></td>
                                        <td class="py-3 px-4 border-l border-slate-200"></td>
                                        @if($canSendWorkAlert ?? false)
                                            <td></td>
                                        @endif
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>

                    {{-- Dropdown Filter Pengguna di Bawah Tabel Rekap (Khusus Admin) --}}
                    @if(!empty($userOptions) && $userOptions->isNotEmpty())
                    <div class="mt-4 pt-3.5 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                        <div class="flex items-center gap-2 text-slate-600">
                            <span class="font-semibold text-[#071833]">Filter Pengguna:</span>
                            <span class="text-slate-400">Pilih akun pengguna untuk menyaring putusan di bawah.</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <form method="GET" action="{{ route($listRoute ?? 'legal-decisions.downloads') }}" class="inline-flex items-center gap-2">
                                @if(!empty($filters['search']))
                                    <input type="hidden" name="search" value="{{ $filters['search'] }}">
                                @endif
                                @if(!empty($filters['kategori']))
                                    <input type="hidden" name="kategori" value="{{ $filters['kategori'] }}">
                                @endif
                                @if(!empty($filters['peradilan']))
                                    <input type="hidden" name="peradilan" value="{{ $filters['peradilan'] }}">
                                @endif
                                @if(!empty($filters['year']))
                                    <input type="hidden" name="year" value="{{ $filters['year'] }}">
                                @endif
                                @if(!empty($filters['file_status']))
                                    <input type="hidden" name="file_status" value="{{ $filters['file_status'] }}">
                                @endif
                                @if(!empty($filters['recap_from']))
                                    <input type="hidden" name="recap_from" value="{{ $filters['recap_from'] }}">
                                    <input type="hidden" name="recap_to" value="{{ $filters['recap_to'] }}">
                                    <input type="hidden" name="recap_time_from" value="{{ $filters['recap_time_from'] ?? '' }}">
                                    <input type="hidden" name="recap_time_to" value="{{ $filters['recap_time_to'] ?? '' }}">
                                @endif
                                <input type="hidden" name="page" value="1">

                                <div class="relative">
                                    <select name="user_id" onchange="this.form.submit()"
                                        class="bg-white border border-slate-300 hover:border-[#946d25] rounded-xl px-3 py-1.5 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-1 focus:ring-[#946d25] focus:border-[#946d25] transition cursor-pointer shadow-xs">
                                        <option value="">— Tampilkan Semua Pengguna ({{ number_format($summaryStats->total_all ?? 0, 0, ',', '.') }} Percobaan) —</option>
                                        @foreach ($userOptions as $uOpt)
                                            @if($uOpt->user_id > 0)
                                                <option value="{{ $uOpt->user_id }}" {{ ((string) ($selectedUserId ?? '')) === ((string) $uOpt->user_id) ? 'selected' : '' }}>
                                                    {{ $uOpt->name }} ({{ number_format($uOpt->success_count, 0, ',', '.') }} berhasil / {{ number_format($uOpt->failure_count, 0, ',', '.') }} gagal)
                                                </option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>

                                @if(!empty($selectedUserId))
                                    @php
                                        $resetParams = request()->query();
                                        unset($resetParams['user_id']);
                                        $resetParams['page'] = 1;
                                    @endphp
                                    <a href="{{ route($listRoute ?? 'legal-decisions.downloads', $resetParams) }}"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold transition"
                                        title="Reset ke semua pengguna">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                        <span>Reset</span>
                                    </a>
                                @endif
                            </form>
                        </div>
                    </div>
                    @endif
                </x-card>
            @endif

            <x-card>
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                    <div class="flex items-center gap-3">
                        <p class="text-sm font-medium text-[#667085]">
                            <span class="font-bold text-[#071833]">{{ number_format($real_total_count ?? $decisions->total(), 0, ',', '.') }}</span> data putusan ditemukan
                        </p>
                        @if(!$isArchivePage && !empty($cached_at))
                            <span class="text-[11px] text-slate-400" title="Daftar diambil dari cache; Perbarui menjadwalkan pengambilan ulang di belakang layar">
                                Cache {{ \Illuminate\Support\Carbon::parse($cached_at)->timezone('Asia/Jakarta')->format('d/m H:i') }} WIB
                                @if(!empty($blocked_until))
                                    · portal MA membatasi akses, pembaruan ditunda sampai {{ $blocked_until->timezone('Asia/Jakarta')->format('H:i') }} WIB
                                @elseif(!empty($is_refresh_queued))
                                    · sedang diperbarui di belakang layar
                                @else
                                    · <a href="{{ request()->fullUrlWithQuery(['refresh' => 1]) }}" class="text-[#946d25] hover:underline">Perbarui</a>
                                @endif
                            </span>
                        @endif
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
                            {{-- Dropdown Filter Status Download di Header Tabel --}}
                            @php
                                $currentDlStatus = $filters['download_status'] ?? '';
                                $allParams = request()->query();
                                unset($allParams['download_status']);
                                $allParams['page'] = 1;

                                $notDlParams = request()->query();
                                $notDlParams['download_status'] = 'not_downloaded';
                                $notDlParams['page'] = 1;

                                $dlParams = request()->query();
                                $dlParams['download_status'] = 'downloaded';
                                $dlParams['page'] = 1;
                            @endphp
                            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                <button type="button"
                                    @click="open = !open"
                                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all shadow-xs"
                                    :class="open ? 'bg-slate-50 border-[#946d25] text-[#946d25]' : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                    </svg>
                                    <span>
                                        @if($currentDlStatus === 'not_downloaded')
                                            Belum Di-download
                                        @elseif($currentDlStatus === 'downloaded')
                                            Sudah Di-download
                                        @else
                                            Status: Semua
                                        @endif
                                    </span>
                                    <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <div x-show="open"
                                    x-cloak
                                    x-transition:enter="transition ease-out duration-100"
                                    x-transition:enter-start="transform opacity-0 scale-95"
                                    x-transition:enter-end="transform opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-75"
                                    x-transition:leave-start="transform opacity-100 scale-100"
                                    x-transition:leave-end="transform opacity-0 scale-95"
                                    class="absolute left-0 sm:right-0 sm:left-auto mt-1.5 w-48 rounded-xl bg-white p-1.5 shadow-lg border border-slate-100 z-30 focus:outline-none">
                                    
                                    <a href="{{ route($listRoute ?? 'legal-decisions.index', $allParams) }}"
                                        class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors {{ $currentDlStatus === '' ? 'bg-[#946d25]/10 text-[#946d25] font-semibold' : 'text-slate-700 hover:bg-slate-50' }}">
                                        <span class="flex items-center gap-2">
                                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                                            Semua Putusan
                                        </span>
                                        @if($currentDlStatus === '')
                                            <svg class="w-3.5 h-3.5 text-[#946d25]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        @endif
                                    </a>

                                    <a href="{{ route($listRoute ?? 'legal-decisions.index', $notDlParams) }}"
                                        class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors {{ $currentDlStatus === 'not_downloaded' ? 'bg-[#946d25]/10 text-[#946d25] font-semibold' : 'text-slate-700 hover:bg-slate-50' }}">
                                        <span class="flex items-center gap-2">
                                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                            Belum Di-download
                                        </span>
                                        @if($currentDlStatus === 'not_downloaded')
                                            <svg class="w-3.5 h-3.5 text-[#946d25]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        @endif
                                    </a>

                                    <a href="{{ route($listRoute ?? 'legal-decisions.index', $dlParams) }}"
                                        class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors {{ $currentDlStatus === 'downloaded' ? 'bg-emerald-50 text-emerald-700 font-semibold' : 'text-slate-700 hover:bg-slate-50' }}">
                                        <span class="flex items-center gap-2">
                                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                            Sudah Di-download
                                        </span>
                                        @if($currentDlStatus === 'downloaded')
                                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                        @endif
                                    </a>
                                </div>
                            </div>

                        @else
                            {{-- Dropdown Filter Status File (Hanya di Putusan MA Download) --}}
                            @php
                                $currentFileStatus = $filters['file_status'] ?? '';
                                $fileStatusOptions = [
                                    '' => ['label' => 'Semua Putusan', 'button' => 'Status: Semua', 'active' => 'bg-[#946d25]/10 text-[#946d25]', 'icon' => 'M4 6h16M4 12h16M4 18h16', 'iconClass' => 'text-slate-400'],
                                    'available' => ['label' => 'Berhasil Di-download', 'button' => 'Berhasil Di-download', 'active' => 'bg-emerald-50 text-emerald-700', 'icon' => 'M5 13l4 4L19 7', 'iconClass' => 'text-emerald-600'],
                                    'missing' => ['label' => 'Gagal Terunduh', 'button' => 'Gagal Terunduh', 'active' => 'bg-red-50 text-red-700', 'icon' => 'M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z', 'iconClass' => 'text-red-600'],
                                ];
                            @endphp
                            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                <button type="button"
                                    @click="open = !open"
                                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold border transition-all shadow-xs"
                                    :class="open ? 'bg-slate-50 border-[#946d25] text-[#946d25]' : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'">
                                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                                    </svg>
                                    <span>{{ $fileStatusOptions[$currentFileStatus]['button'] ?? 'Status: Semua' }}</span>
                                    <svg class="w-3.5 h-3.5 text-slate-400 transition-transform duration-200" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>

                                <div x-show="open" x-cloak x-transition
                                    class="absolute left-0 sm:right-0 sm:left-auto mt-1.5 w-52 rounded-xl bg-white p-1.5 shadow-lg border border-slate-100 z-30 focus:outline-none">
                                    @foreach($fileStatusOptions as $value => $option)
                                        @php
                                            $optionParams = request()->query();
                                            unset($optionParams['file_status'], $optionParams['page']);
                                            if ($value !== '') {
                                                $optionParams['file_status'] = $value;
                                            }
                                        @endphp
                                        <a href="{{ route('legal-decisions.downloads', $optionParams) }}"
                                            class="flex items-center justify-between px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors {{ $currentFileStatus === $value ? $option['active'].' font-semibold' : 'text-slate-700 hover:bg-slate-50' }}">
                                            <span class="flex items-center gap-2">
                                                <svg class="w-3.5 h-3.5 {{ $option['iconClass'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $option['icon'] }}" /></svg>
                                                {{ $option['label'] }}
                                            </span>
                                            @if($currentFileStatus === $value)
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Bulk Delete Button (Hanya di Putusan MA Download) --}}
                            <form id="bulkDeleteForm" action="{{ route('legal-decisions.downloads.bulk-destroy') }}" method="POST"
                                @submit.prevent="confirmBulkDelete($event)">
                                @csrf
                                <template x-for="id in Object.keys(selectedIds)" :key="id">
                                    <input type="hidden" name="ids[]" :value="id">
                                </template>
                                <button type="submit"
                                    :disabled="selectedIdsCount === 0 || isDeletingBulk"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all shadow-sm disabled:opacity-50 disabled:cursor-not-allowed"
                                    :class="selectedIdsCount > 0 ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-slate-100 text-slate-400'">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    <span x-text="selectedIdsCount > 0 ? 'Hapus Terpilih (' + selectedIdsCount + ')' : 'Hapus Terpilih'"></span>
                                </button>
                            </form>
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
                            <span class="text-slate-500">(<span x-text="queueCurrentIndex"></span> dari <span x-text="queueTotal"></span>)</span>
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

                {{-- Ringkasan Putusan yang Gagal Diunduh (detail bisa dibuka/disembunyikan) --}}
                <div x-show="failedItems.length > 0 && !queueRunning" x-cloak class="mb-4 p-3 rounded-xl bg-red-50 border border-red-200 text-xs">
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-semibold text-red-800"><span x-text="failedItems.length"></span> file gagal didownload dan tidak disimpan ke arsip.</p>
                        <div class="flex items-center gap-3 shrink-0">
                            <button type="button" @click="showFailedDetails = !showFailedDetails" class="font-semibold text-red-700 hover:underline"
                                x-text="showFailedDetails ? 'Sembunyikan detail' : 'Lihat detail'"></button>
                            <button type="button" @click="failedItems = []; showFailedDetails = false" class="text-red-400 hover:text-red-600 text-base leading-none" title="Tutup">&times;</button>
                        </div>
                    </div>
                    <ul x-show="showFailedDetails" x-collapse class="mt-2 pt-2 border-t border-red-200 space-y-1.5 max-h-48 overflow-y-auto">
                        <template x-for="(failed, index) in failedItems" :key="index">
                            <li class="text-red-700">
                                <span class="font-semibold" x-text="failed.title"></span>
                                <span class="text-red-600/80">&mdash; <span x-text="failed.reason"></span></span>
                            </li>
                        </template>
                    </ul>
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
                                @if($isArchivePage)
                                    <th class="w-10 text-center">
                                        <input type="checkbox"
                                            @change="toggleSelectAllIds($event.target.checked)"
                                            :checked="isAllIdsSelected"
                                            class="w-4 h-4 rounded border-[#d0d5dd] text-red-600 focus:ring-red-500 cursor-pointer"
                                            title="Pilih semua putusan untuk dihapus">
                                    </th>
                                @endif
                                <th>Nomor Putusan & Perkara</th>
                                <th>Lembaga</th>
                                <th>Klasifikasi</th>
                                <th>Tahun</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($decisions as $dec)
                                <tr :class="isSelected(@js($dec->source_url)) || isIdSelected(@js($dec->id ?? null)) ? 'bg-red-50/30' : ''">
                                    @if($isArchivePage)
                                        <td class="text-center align-middle">
                                            <input type="checkbox"
                                                :value="@js($dec->id)"
                                                :checked="isIdSelected(@js($dec->id))"
                                                @change="toggleSelectId(@js($dec->id), $event.target.checked)"
                                                class="w-4 h-4 rounded border-[#d0d5dd] text-red-600 focus:ring-red-500 cursor-pointer"
                                                title="Pilih putusan ini untuk dihapus">
                                        </td>
                                    @endif
                                    <td class="max-w-md">
                                        <p class="font-bold text-[#071833]">{{ $dec->decision_number }}</p>
                                        @if($isArchivePage || ! empty($dec->is_downloaded) || ! empty($dec->is_download_failed))
                                            <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                                @if(! empty($dec->downloaded_by) || ($isArchivePage && ! empty(data_get($dec, 'user.name'))))
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700" title="Pengguna yang mengunduh atau mencoba mengunduh putusan ini">
                                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                        </svg>
                                                        Diunduh oleh {{ $dec->downloaded_by ?? data_get($dec, 'user.name') }}
                                                    </span>
                                                @elseif($isArchivePage || ! empty($dec->is_downloaded))
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700" title="Pengguna yang mengunduh putusan ini">
                                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                        </svg>
                                                        Diunduh oleh pengguna tidak diketahui
                                                    </span>
                                                @endif
                                                @if(! empty($dec->is_pdf_missing))
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-50 text-red-700" title="Putusan sudah tercatat, tetapi file PDF-nya tidak ditemukan di Cloudflare R2.">
                                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                                                        </svg>
                                                        File gagal terunduh
                                                    </span>
                                                @elseif(! empty($dec->is_download_failed))
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold bg-red-50 text-red-700 border border-red-200" title="{{ $dec->download_error ?? 'Pengunduhan file PDF sebelumnya gagal' }}">
                                                        <svg class="w-3 h-3 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                                                        </svg>
                                                        File gagal terunduh
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
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
                                    <td class="text-center">
                                        <div class="flex items-center justify-center gap-1.5">
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
                                                    pdfSource: @js(! empty($dec->is_in_cloud) || data_get($dec, 'metadata.r2_path') ? 'cloud' : 'ma'),
                                                    downloadsUrl: @js(route('legal-decisions.downloads', ['search' => $dec->decision_number])),
                                                    downloadUrl: @js($dec->source_url ? route('legal-decisions.pdf', ['url' => $dec->source_url, 'download' => 1, 'archived' => $isArchivePage ? 1 : null, 'title' => $dec->decision_number, 'court' => $dec->court_name ?? '', 'classification' => $dec->classification ?? '', 'year' => $dec->year ?? '', 'summary' => $dec->summary ?? '', 'tgl_register' => $dec->tgl_register ?? '', 'tgl_putus' => $dec->tgl_putus ?? '', 'tgl_upload' => $dec->tgl_upload ?? '']) : ($dec->file_url ?? ''))
                                                })"
                                                class="inline-flex items-center justify-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-[#946d25]/10 text-[#946d25] hover:bg-[#946d25] hover:text-white transition-all shadow-sm group leading-none whitespace-nowrap"
                                                title="Lihat ringkasan dan preview dokumen PDF">
                                                <svg class="w-3.5 h-3.5 group-hover:scale-110 transition-transform flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                                <span>Preview</span>
                                            </button>

                                            {{-- Status Item Saat Antrian Sedang Berjalan (Hanya di Live Page) --}}
                                            @if(!$isArchivePage)
                                                <template x-if="itemStatus[@js($dec->source_url)]">
                                                    <div class="inline-flex items-center justify-center gap-1.5 px-2 py-1 rounded-md text-xs font-semibold leading-none whitespace-nowrap"
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
                                                        <span x-show="itemStatus[@js($dec->source_url)] === 'error'" :title="itemError[@js($dec->source_url)] || ''">Gagal</span>
                                                        <span x-show="itemStatus[@js($dec->source_url)] === 'waiting'">Menunggu antrian</span>
                                                    </div>
                                                </template>
                                            @endif

                                            {{-- Aksi Download (Hanya di Live Page) / Delete (Hanya di Archive Page) --}}
                                            @if(!$isArchivePage)
                                                <template x-if="!itemStatus[@js($dec->source_url)] || itemStatus[@js($dec->source_url)] === 'error'">
                                                    <div class="inline-flex items-center">
                                                        @if(! empty($dec->is_downloaded) && empty($dec->is_pdf_missing))
                                                            <a href="{{ route('legal-decisions.downloads', ['search' => $dec->decision_number]) }}"
                                                                class="inline-flex items-center justify-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition-all leading-none whitespace-nowrap"
                                                                title="Putusan ini sudah ada di Putusan MA (download)">
                                                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                                </svg>
                                                                <span>Sudah ada</span>
                                                            </a>
                                                        @elseif(! empty($dec->is_download_failed) || ! empty($dec->is_pdf_missing))
                                                            <a href="{{ $dec->source_url ? route('legal-decisions.pdf', array_filter(['url' => $dec->source_url, 'download' => 1, 'archived' => $isArchivePage ? 1 : null, 'title' => $dec->decision_number, 'court' => $dec->court_name ?? '', 'classification' => $dec->classification ?? '', 'year' => $dec->year ?? '', 'summary' => $dec->summary ?? '', 'tgl_register' => $dec->tgl_register ?? '', 'tgl_putus' => $dec->tgl_putus ?? '', 'tgl_upload' => $dec->tgl_upload ?? ''])) : $dec->file_url }}"
                                                                target="_blank"
                                                                class="inline-flex items-center justify-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-red-50 text-red-700 hover:bg-red-100 hover:text-red-800 border border-red-200/80 transition-all shadow-sm group leading-none whitespace-nowrap"
                                                                title="{{ $dec->download_error ? 'Gagal sebelumnya: ' . $dec->download_error . ' — Klik untuk mencoba unduh ulang' : 'Download sebelumnya gagal — Klik untuk mencoba unduh ulang' }}">
                                                                <svg class="w-3.5 h-3.5 text-red-600 group-hover:text-red-700 group-hover:rotate-180 transition-transform duration-300 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                                </svg>
                                                                <span class="text-red-700 group-hover:text-red-800">Coba Lagi</span>
                                                            </a>
                                                        @elseif($dec->source_url || $dec->file_url)
                                                            <a href="{{ $dec->source_url ? route('legal-decisions.pdf', array_filter(['url' => $dec->source_url, 'download' => 1, 'archived' => $isArchivePage ? 1 : null, 'title' => $dec->decision_number, 'court' => $dec->court_name ?? '', 'classification' => $dec->classification ?? '', 'year' => $dec->year ?? '', 'summary' => $dec->summary ?? '', 'tgl_register' => $dec->tgl_register ?? '', 'tgl_putus' => $dec->tgl_putus ?? '', 'tgl_upload' => $dec->tgl_upload ?? ''])) : $dec->file_url }}"
                                                                target="_blank"
                                                                class="inline-flex items-center justify-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-[#071833] text-white hover:bg-[#071833]/85 transition-all shadow-sm leading-none whitespace-nowrap"
                                                                title="Download file PDF putusan">
                                                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                                                                </svg>
                                                                <span>Download</span>
                                                            </a>
                                                        @endif
                                                    </div>
                                                </template>
                                            @else
                                                @if(! empty($dec->is_pdf_missing) && $dec->source_url)
                                                    <a href="{{ route('legal-decisions.pdf', array_filter(['url' => $dec->source_url, 'download' => 1, 'archived' => 1, 'title' => $dec->decision_number])) }}"
                                                        target="_blank"
                                                        class="inline-flex items-center justify-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200/80 transition-all shadow-sm leading-none whitespace-nowrap"
                                                        title="File PDF tidak ditemukan di cloud — Klik untuk mengunduh ulang">
                                                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                        </svg>
                                                        <span>Unduh Ulang</span>
                                                    </a>
                                                @endif
                                                {{-- Tombol Delete Putusan di Halaman Putusan MA (Download) --}}
                                                <form action="{{ route('legal-decisions.downloads.destroy', $dec->id) }}" method="POST"
                                                    onsubmit="return confirm('Apakah Anda yakin ingin menghapus putusan {{ addslashes($dec->decision_number) }} dari arsip? Berkas terkait di cloud juga akan dihapus.');"
                                                    class="inline-block m-0 p-0">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="inline-flex items-center justify-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-red-50 text-red-700 hover:bg-red-100 hover:text-red-800 border border-red-200/80 transition-all shadow-sm group leading-none whitespace-nowrap cursor-pointer"
                                                        title="Hapus putusan dari arsip">
                                                        <svg class="w-3.5 h-3.5 text-red-600 group-hover:text-red-700 flex-shrink-0 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                        <span class="text-red-700 group-hover:text-red-800">Delete</span>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $isArchivePage ? 6 : 5 }}" class="text-center py-12">
                                        <div class="max-w-sm mx-auto text-center">
                                            <svg class="w-12 h-12 mx-auto text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                            </svg>
                                            @if(! empty($download_notice))
                                                <h4 class="font-semibold text-slate-700">Tidak ada putusan yang belum diunduh</h4>
                                                <p class="text-xs text-slate-500 mt-1">{{ $download_notice['message'] }}</p>
                                                @if($download_notice['next_page'])
                                                    <a href="{{ route('legal-decisions.index', [...request()->query(), 'page' => $download_notice['next_page']]) }}"
                                                        class="inline-flex items-center gap-1 mt-3 px-3 py-1.5 rounded-lg text-xs font-semibold bg-[#946d25] text-white hover:bg-[#946d25]/90">
                                                        Cari mulai halaman {{ $download_notice['next_page'] }}
                                                    </a>
                                                @endif
                                            @elseif(! empty($is_loading))
                                                <h4 class="font-semibold text-slate-700">Sedang mengambil data dari portal MA…</h4>
                                                <p class="text-xs text-slate-500 mt-1">
                                                    @if(! empty($blocked_until))
                                                        Portal MA sedang membatasi akses. Pengambilan dilanjutkan otomatis setelah {{ $blocked_until->timezone('Asia/Jakarta')->format('H:i') }} WIB.
                                                    @elseif(! empty($loading_error))
                                                        Percobaan terakhir gagal ({{ $loading_error }}). Dicoba lagi otomatis di belakang layar.
                                                    @else
                                                        Halaman ini belum ada di cache. Data diambil di belakang layar, halaman dimuat ulang otomatis.
                                                    @endif
                                                </p>
                                                <script>setTimeout(() => window.location.reload(), 15000);</script>
                                            @elseif(! empty($scraper_error))
                                                <h4 class="font-semibold text-slate-700">Portal MA sedang tidak dapat diakses</h4>
                                                <p class="text-xs text-slate-500 mt-1">{{ $scraper_error }}</p>
                                                <a href="{{ request()->fullUrl() }}"
                                                    class="inline-flex items-center gap-1 mt-3 px-3 py-1.5 rounded-lg text-xs font-semibold bg-[#946d25] text-white hover:bg-[#946d25]/90">
                                                    Muat Ulang
                                                </a>
                                            @elseif(!$isArchivePage && ($filters['download_status'] ?? '') === 'downloaded')
                                                <h4 class="font-semibold text-slate-700">Belum ada putusan yang sudah di-download</h4>
                                                <p class="text-xs text-slate-500 mt-1">Tidak ada putusan di halaman portal ini yang sudah diunduh.</p>
                                            @else
                                                <h4 class="font-semibold text-slate-700">Belum ada data putusan</h4>
                                                <p class="text-xs text-slate-500 mt-1">{{ $emptyMessage ?? 'Data putusan akan otomatis masuk setelah script sinkronisasi dijalankan.' }}</p>
                                            @endif
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
                                    <p class="text-xs font-semibold tracking-wide text-slate-200"
                                        x-text="activeDoc.pdfSource === 'cloud' ? 'Mengambil dokumen PDF dari arsip Cloud...' : 'Mengambil dokumen PDF dari Mahkamah Agung...'"></p>
                                    <p class="text-[11px] text-slate-400" x-show="activeDoc.pdfSource !== 'cloud'">Proses ini membutuhkan beberapa detik pada pembukaan pertama kali.</p>
                                </div>

                                {{-- Sumber PDF --}}
                                <div class="flex items-center gap-2 px-4 py-2 text-[11px] font-semibold border-b border-white/10"
                                    :class="activeDoc.pdfSource === 'cloud' ? 'bg-emerald-900/60 text-emerald-200' : 'bg-slate-800 text-slate-200'">
                                    <span class="w-2 h-2 rounded-full" :class="activeDoc.pdfSource === 'cloud' ? 'bg-emerald-400' : 'bg-amber-400'"></span>
                                    <span x-text="activeDoc.pdfSource === 'cloud'
                                        ? 'Sumber preview: Arsip Cloud (Cloudflare R2), sudah tersimpan'
                                        : 'Sumber preview: Website Direktori Putusan Mahkamah Agung (langsung)'"></span>
                                </div>

                                {{-- PDF Iframe --}}
                                <iframe x-ref="pdfFrame"
                                    :src="pdfSrc"
                                    @load="pdfLoading = false"
                                    class="w-full flex-1 min-h-0 border-0"
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
        // Tombol peringatan kerja (admin): URL & label dari data-* agar tidak ada directive Blade di dalam atribut Alpine.
        function workAlertButton() {
            return {
                sending: false,
                sent: false,
                url: '',
                label: '',
                init() {
                    this.url = this.$el.dataset.url;
                    this.label = this.$el.dataset.label;
                },
                get text() {
                    return this.sent ? 'Terkirim' : (this.sending ? 'Mengirim...' : this.label);
                },
                send() {
                    this.sending = true;
                    fetch(this.url, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                    })
                        .then((response) => { this.sent = response.ok; })
                        .finally(() => {
                            this.sending = false;
                            setTimeout(() => { this.sent = false; }, 4000);
                        });
                },
            };
        }

        function putusanIndexManager() {
            return {
                // Bulk Selection State
                selectedDecisions: {},
                selectedIds: {},
                isDeletingBulk: false,
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
                itemError: {},
                failedItems: [],
                showFailedDetails: false,

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
                    isDownloaded: false,
                    pdfSource: 'ma'
                },

                get selectedCount() {
                    return Object.keys(this.selectedDecisions).length;
                },

                get selectedIdsCount() {
                    return Object.keys(this.selectedIds).length;
                },

                isIdSelected(id) {
                    return !!(id && this.selectedIds[id]);
                },

                toggleSelectId(id, checked) {
                    if (!id) return;
                    if (checked) {
                        this.selectedIds[id] = true;
                    } else {
                        delete this.selectedIds[id];
                    }
                },

                get pageIds() {
                    const ids = [];
                    @if($isArchivePage)
                        @foreach ($decisions as $dec)
                            ids.push(@js($dec->id));
                        @endforeach
                    @endif
                    return ids;
                },

                get isAllIdsSelected() {
                    const ids = this.pageIds;
                    if (ids.length === 0) return false;
                    return ids.every(id => !!this.selectedIds[id]);
                },

                toggleSelectAllIds(checked) {
                    const ids = this.pageIds;
                    if (checked) {
                        ids.forEach(id => {
                            this.selectedIds[id] = true;
                        });
                    } else {
                        this.selectedIds = {};
                    }
                },

                confirmBulkDelete(event) {
                    const count = this.selectedIdsCount;
                    if (count === 0) return;
                    if (confirm(`Apakah Anda yakin ingin menghapus ${count} putusan yang dipilih dari arsip? Berkas terkait di cloud juga akan dihapus.`)) {
                        this.isDeletingBulk = true;
                        event.target.submit();
                    }
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
                        @if(!empty($dec->source_url) && empty($dec->is_downloaded))
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
                    return items.filter(item => !['done', 'already'].includes(this.itemStatus[item.url]));
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
                    this.failedItems = [];
                    this.showFailedDetails = false;

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
                        delete this.itemError[item.url];

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

                            const data = await res.json().catch(() => ({}));
                            if (res.ok && data.success) {
                                if (data.already_exists) {
                                    this.itemStatus[item.url] = 'already';
                                    alreadyCount++;
                                } else {
                                    this.itemStatus[item.url] = 'done';
                                    successCount++;
                                }
                            } else {
                                this.markFailed(item, data.message || this.httpErrorReason(res.status));
                                errorCount++;
                            }
                        } catch (err) {
                            this.markFailed(item, 'Tidak dapat terhubung ke server aplikasi. Periksa koneksi internet Anda.');
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

                markFailed(item, reason) {
                    this.itemStatus[item.url] = 'error';
                    this.itemError[item.url] = reason;
                    this.failedItems.push({ title: item.decision_number || item.url, reason });
                },

                httpErrorReason(status) {
                    if (status === 419) return 'Sesi login kedaluwarsa. Muat ulang halaman lalu coba lagi.';
                    if (status === 429) return 'Terlalu banyak permintaan dalam waktu singkat. Tunggu sebentar lalu coba lagi.';
                    if (status === 401 || status === 403) return 'Anda tidak memiliki akses untuk mengunduh putusan.';
                    if (status >= 500) return `Terjadi kesalahan di server aplikasi (HTTP ${status}).`;
                    return `Gagal mengunduh (HTTP ${status}).`;
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

