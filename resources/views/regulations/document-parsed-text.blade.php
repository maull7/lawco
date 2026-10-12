@extends('layouts.app')

@section('title', 'Hasil Parse — ' . $document->name)
@section('header', 'Hasil Parse Dokumen')

@section('content')
    <section class="relative overflow-hidden rounded-[24px] bg-navy-gradient text-white p-7 sm:p-9">
        <div class="pointer-events-none absolute -top-24 -right-16 w-80 h-80 rounded-full bg-[#c99a3e]/18 blur-3xl"></div>
        <div class="relative flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[10.5px] font-bold rounded-full bg-[#c99a3e]/20 ring-1 ring-[#c99a3e]/30 text-[#e6c06a] uppercase tracking-wider">
                        <span class="w-1 h-1 rounded-full bg-[#e6c06a]"></span>
                        Hasil Parse
                    </span>
                    @if($document->isParsed())
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 ring-1 ring-emerald-500/30">
                            {{ $document->parseStatusLabel() }}
                        </span>
                    @endif
                </div>
                <h2 class="mt-4 text-2xl sm:text-3xl font-bold tracking-tight">{{ $document->name }}</h2>
                <p class="mt-2 text-white/70 text-sm">{{ $document->document_type ?: '-' }} &middot; Regulasi: {{ $document->regulation->regulation_number }}</p>
            </div>
            <a href="{{ route('regulations.show', $document->regulation) }}#hasil-parse" class="shrink-0 inline-flex items-center gap-2 px-5 h-11 rounded-xl bg-white/10 backdrop-blur text-sm font-semibold text-white hover:bg-white/20 transition">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
                Kembali
            </a>
        </div>
    </section>

    <div class="mt-6">
        @php $stats = $document->parse_stats; @endphp
        <div x-data="{ tab: 'result' }" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <x-card>
                    <x-slot name="header">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-bold text-[#071833]">Hasil Ekstraksi Teks</h3>
                                <p class="text-xs text-[#667085] mt-0.5">Teks hasil parse dari file PDF</p>
                            </div>
                        </div>
                    </x-slot>
                    <div class="text-xs text-[#071833] leading-relaxed bg-[#f6f8fb] rounded-xl p-4 max-h-96 overflow-y-auto">@formatText($document->parsed_text)</div>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card>
                    <x-slot name="header">
                        <h3 class="text-base font-bold text-[#071833]">Statistik Parse</h3>
                    </x-slot>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-xl bg-[#f6f8fb] p-4">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Total Halaman</p>
                                <p class="mt-1.5 text-2xl font-bold text-[#071833]">{{ $stats['total_pages'] ?? '-' }}</p>
                            </div>
                            <div class="rounded-xl bg-[#f6f8fb] p-4">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Terdeteksi</p>
                                <p class="mt-1.5 text-2xl font-bold text-emerald-600">{{ $stats['parsed_pages'] ?? '-' }}</p>
                            </div>
                            <div class="rounded-xl bg-[#f6f8fb] p-4">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Belum Terbaca</p>
                                <p class="mt-1.5 text-2xl font-bold text-amber-600">{{ $stats['empty_pages'] ?? '-' }}</p>
                                @if (!empty($stats['unread_page_numbers']))
                                    <p class="mt-1 text-xs text-[#667085]">Halaman: {{ implode(', ', $stats['unread_page_numbers']) }}</p>
                                @endif
                            </div>
                            <div class="rounded-xl bg-[#f6f8fb] p-4">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Persentase</p>
                                <p class="mt-1.5 text-2xl font-bold text-[#071833]">{{ ($stats['percent_parsed'] ?? 0) . '%' }}</p>
                                @if (isset($stats['target_percent']))
                                    <p class="mt-1 text-xs text-[#667085]">Target minimal {{ $stats['target_percent'] }}% halaman terbaca</p>
                                @endif
                            </div>
                            <div class="rounded-xl bg-[#f6f8fb] p-4">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Normal Pages</p>
                                <p class="mt-1.5 text-2xl font-bold text-blue-600">{{ $stats['normal_pages'] ?? 0 }}</p>
                            </div>
                            <div class="rounded-xl bg-[#f6f8fb] p-4">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">OCR Pages</p>
                                <p class="mt-1.5 text-2xl font-bold text-purple-600">{{ $stats['ocr_pages'] ?? 0 }}</p>
                            </div>
                        </div>
                        <div class="rounded-xl bg-[#f6f8fb] p-4">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Total Karakter</p>
                            <p class="mt-1.5 text-2xl font-bold text-[#071833]">{{ number_format($stats['char_total'] ?? 0) }}</p>
                        </div>
                        <div class="rounded-xl bg-[#f6f8fb] p-4">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Tipe PDF</p>
                            <p class="mt-1.5">
                                @if(!empty($stats['used_ocr']))
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-700">OCR</span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-700">Normal</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </x-card>

                <x-card>
                    <x-slot name="header">
                        <h3 class="text-base font-bold text-[#071833]">Aksi</h3>
                    </x-slot>
                    <div class="space-y-2.5">
                        <a href="{{ route('regulations.documents.view', $document) }}" target="_blank" class="inline-flex items-center justify-center gap-2 w-full h-11 rounded-xl bg-[#f6f8fb] text-sm font-semibold text-[#071833] ring-1 ring-[#e7eaf0] hover:bg-white hover:ring-[#c99a3e]/40 transition">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                            Buka File PDF
                        </a>
                        <a href="{{ route('regulations.show', $document->regulation) }}" class="inline-flex items-center justify-center gap-2 w-full h-11 rounded-xl bg-[#f6f8fb] text-sm font-semibold text-[#071833] ring-1 ring-[#e7eaf0] hover:bg-white hover:ring-[#c99a3e]/40 transition">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
                            Kembali ke Regulasi
                        </a>
                    </div>
                </x-card>
            </div>
        </div>
    </div>
@endsection
