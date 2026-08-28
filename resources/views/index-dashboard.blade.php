@extends('layouts.app')

@section('title', 'Landing Page - Dashboard')
@section('header', 'Landing Page - Dashboard')

@section('content')
    {{-- Hero / Welcome panel --}}
    <section
        class="relative overflow-hidden rounded-[24px] bg-navy-gradient text-white p-7 sm:p-9 shadow-[0_18px_50px_rgba(7,27,58,.18)]">
        <div class="pointer-events-none absolute -top-24 -right-16 w-80 h-80 rounded-full bg-[#c99a3e]/20 blur-3xl"></div>
        <div class="pointer-events-none absolute inset-0 opacity-[0.07]"
            style="background-image: linear-gradient(rgba(255,255,255,.5) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.5) 1px, transparent 1px); background-size: 48px 48px;">
        </div>

        <div class="relative grid lg:grid-cols-3 gap-8 items-center">
            <div class="lg:col-span-2">
                <span
                    class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#c99a3e]/15 ring-1 ring-[#c99a3e]/30 text-[11px] font-semibold tracking-wider uppercase text-[#e6c06a]">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#e6c06a]"></span>
                    {{ now()->format('l, d F Y') }}
                </span>
                <h2 class="mt-4 text-3xl sm:text-4xl font-bold tracking-tight leading-tight">
                    Welcome back,
                </h2>
                <p class="mt-3 text-white/70 max-w-xl">Here's an institutional-grade overview of your compliance workspace —
                    documents, reviews, and regulatory categories in one elegant view.</p>

                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <x-button href="#" variant="primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Upload Document
                    </x-button>
                    <a href="#"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-semibold text-white border border-white/15 bg-white/5 hover:bg-white/10 backdrop-blur transition">
                        View Reviews
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                    @auth
                        @if (auth()->user()->hasPermission('manage_categories'))
                        <x-button href="{{ route('sectors.index') }}" variant="outline">
                            Sektor
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                            </svg>
                        </x-button>
                        @endif
                    @endif
                </div>
            </div>

            <div class="relative rounded-2xl border border-white/10 bg-white/5 backdrop-blur p-5">
                <p class="text-[11px] font-semibold tracking-[0.16em] uppercase text-white/55">Compliance Health</p>
                @php
                    $rate =
                        $stats['total_documents'] > 0
                            ? round(($stats['approved_documents'] / max($stats['total_documents'], 1)) * 100)
                            : 0;
                    $offset = 251.2 * (1 - $rate / 100);
                @endphp
                <div class="mt-4 flex items-center gap-4">
                    <div class="relative w-24 h-24">
                        <svg class="w-full h-full -rotate-90" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="40" fill="none" stroke="rgba(255,255,255,.12)"
                                stroke-width="9" />
                            <circle cx="50" cy="50" r="40" fill="none" stroke="url(#goldGrad)"
                                stroke-width="9" stroke-linecap="round" stroke-dasharray="251.2"
                                stroke-dashoffset="{{ $offset }}" />
                            <defs>
                                <linearGradient id="goldGrad" x1="0" y1="0" x2="1" y2="1">
                                    <stop offset="0%" stop-color="#c99a3e" />
                                    <stop offset="100%" stop-color="#e6c06a" />
                                </linearGradient>
                            </defs>
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-xl font-bold text-white">{{ $rate }}%</span>
                        </div>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-white">Approval Rate</p>
                        <p class="text-[11px] text-white/60 mt-0.5">{{ $stats['approved_documents'] }} of
                            {{ $stats['total_documents'] }} documents</p>
                        <div class="mt-3 flex items-center gap-2">
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-300 text-[10.5px] font-bold">
                                <svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                </svg>
                                Live
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Peraturan Terkini --}}
    @if ($latestRegulations->isNotEmpty())
        <x-card :padding="false" class="mt-7">
            <x-slot name="header">
                <div class="flex flex-wrap gap-3 justify-between items-center">
                    <div>
                        <h3 class="text-lg font-bold text-[#071833]">Peraturan Terkini</h3>
                        <p class="text-xs text-[#667085] mt-0.5">5 regulasi terbaru yang diundangkan</p>
                    </div>
                    <x-button href="{{ route('regulations.index') }}" variant="outline" size="sm">
                        Semua Regulasi
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </x-button>
                </div>
            </x-slot>

            <div class="overflow-x-auto">
                <table class="table-premium">
                    <thead>
                        <tr>
                            <th class="text-left">No. Regulasi</th>
                            <th class="text-left">Judul</th>
                            <th class="text-center">Jenis</th>
                            <th class="text-center">Kategori</th>
                            <th class="text-center">Tahun</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($latestRegulations as $reg)
                            <tr>
                                <td>
                                    <a href="{{ route('regulations.show', $reg) }}"
                                        class="font-semibold text-[#071833] hover:text-[#c99a3e] transition">{{ $reg->regulation_number }}</a>
                                </td>
                                <td>
                                    <a href="{{ route('regulations.show', $reg) }}"
                                        class="text-sm font-medium text-[#071833] hover:text-[#c99a3e] transition line-clamp-2">{{ $reg->title }}</a>
                                </td>
                                <td class="text-center">
                                    @if ($reg->type)
                                        <x-badge :color="$reg->type->levelBadgeColor()">{{ $reg->type->name }}</x-badge>
                                    @else
                                        <span class="text-xs text-[#667085]">-</span>
                                    @endif
                                </td>
                                <td class="text-center text-sm text-[#667085]">{{ $reg->category?->name ?? '-' }}</td>
                                <td class="text-center">
                                    <span class="font-semibold text-[#071833]">{{ $reg->year }}</span>
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('regulations.show', $reg) }}"
                                        class="inline-flex items-center gap-1.5 px-3 h-9 rounded-xl text-xs font-semibold text-[#071833] bg-[#f6f8fb] ring-1 ring-[#e7eaf0] hover:bg-white hover:ring-[#c99a3e]/40 transition">
                                        Detail
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                        </svg>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    {{-- Peraturan Terkait --}}
    @if ($regulationRelated->isNotEmpty())
        <x-card :padding="false" class="mt-6">
            <x-slot name="header">
                <div>
                    <h3 class="text-lg font-bold text-[#071833]">Peraturan Terkait</h3>
                    <p class="text-xs text-[#667085] mt-0.5">5 peraturan terkait terbaru berdasarkan data linkage
                    </p>
                </div>
            </x-slot>

            <div class="overflow-x-auto">
                <table class="table-premium">
                    <thead>
                        <tr>
                            <th class="text-left">Sumber Regulasi</th>
                            <th class="text-left">Nama Terkait</th>
                            <th class="text-center">Nomor</th>
                            <th class="text-center">Tahun</th>
                            <th class="text-center">Hubungan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($regulationRelated as $ref)
                            <tr>
                                <td>
                                    <a href="{{ route('regulations.show', $ref->regulation) }}"
                                        class="font-semibold text-[#071833] hover:text-[#c99a3e] transition">{{ $ref->regulation->regulation_number }}</a>
                                </td>
                                <td class="text-sm font-medium text-[#071833]">{{ $ref->name }}</td>
                                <td class="text-center text-sm text-[#667085]">{{ $ref->number ?? '-' }}</td>
                                <td class="text-center">
                                    <span class="font-semibold text-[#071833]">{{ $ref->year ?? '-' }}</span>
                                </td>
                                <td class="text-center">
                                    <x-badge :color="match ($ref->relationship) {
                                        'diubah' => 'amber',
                                        'dicabut' => 'rose',
                                        default => 'blue',
                                    }">{{ $ref->relationship }}</x-badge>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    {{-- Stat grid --}}
    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mt-7">
        <x-stat-card title="Total Documents" :value="number_format($stats['total_documents'])" color="navy" subtitle="All time uploads">
            <x-slot name="icon">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                </svg>
            </x-slot>
        </x-stat-card>

        <x-stat-card title="Pending Review" :value="number_format($stats['pending_documents'])" color="yellow" subtitle="Awaiting compliance review">
            <x-slot name="icon">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </x-slot>
        </x-stat-card>

        <x-stat-card title="Approved" :value="number_format($stats['approved_documents'])" color="green" subtitle="Cleared by reviewers">
            <x-slot name="icon">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </x-slot>
        </x-stat-card>

        <x-stat-card title="Total Reviews" :value="number_format($stats['total_reviews'])" color="gold" subtitle="Compliance assessments">
            <x-slot name="icon">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M9 12h6m-6 3h4m1.5 6H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.4 48.4 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15a2.25 2.25 0 0 1 2.15 1.586M8.25 8.25H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" />
                </svg>
            </x-slot>
        </x-stat-card>
    </section>

    {{-- Main content grid --}}
    <section class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">

        {{-- Recent Documents --}}
        <div class="lg:col-span-2">
            <x-card :padding="false">
                <x-slot name="header">
                    <div class="flex flex-wrap gap-3 justify-between items-center">
                        <div>
                            <h3 class="text-lg font-bold text-[#071833]">Recent Documents</h3>
                            <p class="text-xs text-[#667085] mt-0.5">Latest 5 uploads in your workspace</p>
                        </div>
                        <x-button href="{{ route('review-documents.index') }}" variant="outline" size="sm">
                            View All
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </x-button>
                    </div>
                </x-slot>

                <div class="overflow-x-auto">
                    <table class="table-premium">
                        <thead>
                            <tr>
                                <th>Document</th>
                                <th>Uploaded By</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($recentDocuments as $document)
                                <tr>
                                    <td>
                                        <a href="{{ route('review-documents.show', $document) }}"
                                            class="flex items-center gap-3 group">
                                            <div
                                                class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#f6f8fb] to-white ring-1 ring-[#e7eaf0] flex items-center justify-center text-[#c99a3e]">
                                                <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor" stroke-width="1.6">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                                </svg>
                                            </div>
                                            <span
                                                class="font-semibold text-[#071833] group-hover:text-[#c99a3e] transition">{{ $document->title }}</span>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2.5">
                                            <div
                                                class="w-7 h-7 rounded-full bg-gradient-to-br from-[#071b3a] to-[#0b2a55] text-white text-[11px] font-bold flex items-center justify-center">
                                                {{ strtoupper(substr($document->user->name, 0, 1)) }}
                                            </div>
                                            <span class="text-sm text-[#071833]">{{ $document->user->name }}</span>
                                        </div>
                                    </td>
                                    <td><x-badge :color="$document->status->color()">{{ $document->status->label() }}</x-badge></td>
                                    <td class="text-[#667085]">{{ $document->created_at->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center py-14">
                                        <div class="flex flex-col items-center gap-3 text-[#667085]">
                                            <div
                                                class="w-14 h-14 rounded-2xl bg-[#f6f8fb] flex items-center justify-center">
                                                <svg class="w-7 h-7 text-[#c99a3e]" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M9 13.5h6m-6 3h4m4.5-12H4.875c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h14.25c.621 0 1.125-.504 1.125-1.125V5.625c0-.621-.504-1.125-1.125-1.125Z" />
                                                </svg>
                                            </div>
                                            <p class="text-sm font-semibold text-[#071833]">No documents yet</p>
                                            <p class="text-xs">Upload your first document to get started.</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        {{-- Quick Actions --}}


    </section>
@endsection
