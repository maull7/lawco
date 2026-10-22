@extends('layouts.app')

@section('title', 'Dashboard')
@section('header', 'Dashboard')

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
                    Welcome back, <span class="text-gold-gradient">{{ auth()->user()->name }}</span>
                </h2>
                <p class="mt-3 text-white/70 max-w-xl">Here's an institutional-grade overview of your compliance workspace —
                    documents, reviews, and regulatory categories in one elegant view.</p>

                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <x-button href="{{ route('review-documents.create') }}" variant="primary">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Upload Document
                    </x-button>
                    <a href="{{ route('reviews.index') }}"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-full text-sm font-semibold text-white border border-white/15 bg-white/5 hover:bg-white/10 backdrop-blur transition">
                        View Reviews
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
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

    @include('dashboard._regulation-tables', ['filterRoute' => 'dashboard', 'aiSearchRoute' => 'dashboard.search'])

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
        @if (Auth()->user()->role == 'admin')
            <div>
                <x-card>
                    <x-slot name="header">
                        <div>
                            <h3 class="text-lg font-bold text-[#071833]">Quick Actions</h3>
                            <p class="text-xs text-[#667085] mt-0.5">Shortcuts to common workflows</p>
                        </div>
                    </x-slot>

                    <div class="space-y-3">
                        <a href="{{ route('review-documents.create') }}"
                            class="group flex items-center gap-4 p-4 rounded-2xl bg-gradient-to-r from-[#c99a3e]/8 to-transparent ring-1 ring-[#c99a3e]/15 hover:from-[#c99a3e]/15 hover:ring-[#c99a3e]/30 transition">
                            <div
                                class="w-11 h-11 rounded-xl bg-gradient-to-br from-[#c99a3e] to-[#e6c06a] text-white flex items-center justify-center shadow-[0_8px_20px_rgba(201,154,62,.3)]">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-[#071833]">Upload Document</p>
                                <p class="text-xs text-[#667085] mt-0.5">Submit a new file for review</p>
                            </div>
                            <svg class="w-4 h-4 text-[#667085] group-hover:text-[#c99a3e] group-hover:translate-x-1 transition"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>

                        <a href="{{ route('regulation-categories.create') }}"
                            class="group flex items-center gap-4 p-4 rounded-2xl bg-white ring-1 ring-[#e7eaf0] hover:ring-[#071b3a]/30 hover:bg-[#f6f8fb] transition">
                            <div
                                class="w-11 h-11 rounded-xl bg-gradient-to-br from-[#071b3a] to-[#0b2a55] text-white flex items-center justify-center shadow-[0_8px_20px_rgba(7,27,58,.2)]">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-[#071833]">Add Category</p>
                                <p class="text-xs text-[#667085] mt-0.5">Create a new regulation category</p>
                            </div>
                            <svg class="w-4 h-4 text-[#667085] group-hover:text-[#071833] group-hover:translate-x-1 transition"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                        <a href="{{ route('sub-categories.create') }}"
                            class="group flex items-center gap-4 p-4 rounded-2xl bg-white ring-1 ring-[#e7eaf0] hover:ring-[#071b3a]/30 hover:bg-[#f6f8fb] transition">
                            <div
                                class="w-11 h-11 rounded-xl bg-gradient-to-br from-[#071b3a] to-[#0b2a55] text-white flex items-center justify-center shadow-[0_8px_20px_rgba(7,27,58,.2)]">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-[#071833]">Add Sub Category</p>
                                <p class="text-xs text-[#667085] mt-0.5">Create a new sub category</p>
                            </div>
                            <svg class="w-4 h-4 text-[#667085] group-hover:text-[#071833] group-hover:translate-x-1 transition"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>

                        <a href="{{ route('reviews.index') }}"
                            class="group flex items-center gap-4 p-4 rounded-2xl bg-white ring-1 ring-[#e7eaf0] hover:ring-[#071b3a]/30 hover:bg-[#f6f8fb] transition">
                            <div
                                class="w-11 h-11 rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600 text-white flex items-center justify-center shadow-[0_8px_20px_rgba(16,185,129,.25)]">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 12h6m-6 3h4M5 6h14v15l-3-2-2 2-2-2-2 2-2-2-3 2V6Z" />
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-[#071833]">Browse Reviews</p>
                                <p class="text-xs text-[#667085] mt-0.5">Explore compliance reports</p>
                            </div>
                            <svg class="w-4 h-4 text-[#667085] group-hover:text-[#071833] group-hover:translate-x-1 transition"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>
                </x-card>

                {{-- Compliance breakdown mini-chart --}}
                <div class="mt-6">
                    <x-card>
                        <x-slot name="header">
                            <div>
                                <h3 class="text-lg font-bold text-[#071833]">Pipeline Snapshot</h3>
                                <p class="text-xs text-[#667085] mt-0.5">Distribution of document statuses</p>
                            </div>
                        </x-slot>
                        @php
                            $total = max($stats['total_documents'], 1);
                            $approvedPct = round(($stats['approved_documents'] / $total) * 100);
                            $pendingPct = round(($stats['pending_documents'] / $total) * 100);
                            $reviewsPct =
                                $stats['total_documents'] > 0
                                    ? round(($stats['total_reviews'] / $stats['total_documents']) * 100)
                                    : 0;
                        @endphp
                        <div class="space-y-5">
                            <div>
                                <div class="flex justify-between text-xs mb-1.5">
                                    <span class="font-semibold text-[#071833]">Approved</span>
                                    <span class="font-bold text-emerald-600">{{ $approvedPct }}%</span>
                                </div>
                                <div class="h-2 rounded-full bg-[#f6f8fb] overflow-hidden">
                                    <div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-600 transition-all"
                                        style="width: {{ $approvedPct }}%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-xs mb-1.5">
                                    <span class="font-semibold text-[#071833]">Pending Review</span>
                                    <span class="font-bold text-amber-600">{{ $pendingPct }}%</span>
                                </div>
                                <div class="h-2 rounded-full bg-[#f6f8fb] overflow-hidden">
                                    <div class="h-full rounded-full bg-gradient-to-r from-amber-300 to-amber-500 transition-all"
                                        style="width: {{ $pendingPct }}%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-xs mb-1.5">
                                    <span class="font-semibold text-[#071833]">Review Coverage</span>
                                    <span class="font-bold text-[#c99a3e]">{{ min($reviewsPct, 100) }}%</span>
                                </div>
                                <div class="h-2 rounded-full bg-[#f6f8fb] overflow-hidden">
                                    <div class="h-full rounded-full bg-gradient-to-r from-[#c99a3e] to-[#e6c06a] transition-all"
                                        style="width: {{ min($reviewsPct, 100) }}%"></div>
                                </div>
                            </div>
                        </div>
                    </x-card>
                </div>
            </div>
        @endif

    </section>
@endsection
