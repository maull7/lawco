@extends('layouts.app')

@section('title', 'Monitor Traffic Website')
@section('header', 'Monitor Traffic Website')

@section('content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#c99a3e]">Analytics</p>
            <h2 class="mt-2 text-3xl font-bold tracking-tight text-[#071833]">Monitor Traffic Website</h2>
            <p class="mt-1.5 text-sm text-[#667085]">Pantau kunjungan, halaman yang dibuka, dan performa traffic website.</p>
        </div>
        @if ($reportUrl)
            <x-button href="{{ $reportUrl }}" target="_blank" rel="noopener noreferrer" variant="outline">
                Buka di Looker Studio
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5H19.5V10.5M19.5 4.5 10.5 13.5M18 13.5V18.75A.75.75 0 0 1 17.25 19.5H5.25A.75.75 0 0 1 4.5 18.75V6.75A.75.75 0 0 1 5.25 6H10.5" />
                </svg>
            </x-button>
        @endif
    </div>

    <x-card :padding="false" class="mt-6 overflow-hidden">
        @if ($reportUrl)
            <iframe title="Dashboard traffic website Looker Studio" src="{{ $reportUrl }}"
                class="h-[calc(100vh-15rem)] min-h-[680px] w-full" frameborder="0" style="border: 0" allowfullscreen
                sandbox="allow-storage-access-by-user-activation allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox"></iframe>
        @else
            <div class="py-16 text-center">
                <p class="text-base font-bold text-[#071833]">Report Looker Studio belum dikonfigurasi.</p>
                <p class="mt-2 text-sm text-[#667085]">Isi <code>LOOKER_STUDIO_TRAFFIC_REPORT_URL</code> pada file <code>.env</code>.</p>
            </div>
        @endif
    </x-card>
@endsection
