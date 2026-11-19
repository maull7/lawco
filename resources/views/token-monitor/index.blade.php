@extends('layouts.app')

@section('title', 'Monitoring Token AI')
@section('header', 'Monitoring Token AI')

@section('content')
    @php
        $fmt = fn ($n) => number_format((int) $n, 0, ',', '.');
        $label = fn ($s) => str($s)->replace(['_', '.', '-'], ' ')->title();
        $maxTokens = max(1, (int) $bySource->max('tokens'));
        $th = 'py-3.5 px-4 text-[11px] font-bold uppercase tracking-wider text-[#667085]';
    @endphp

    <div class="mb-6">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#c99a3e]">Analytics</p>
        <h2 class="mt-2 text-3xl font-bold tracking-tight text-[#071833]">Monitoring Token AI</h2>
        <p class="mt-1.5 text-sm text-[#667085]">Pemakaian token semua panggilan AI menurut fitur, model, pengguna, tanggal, dan jam.</p>
    </div>

    <x-card class="mb-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-base font-bold text-[#071833]">Status AI: {{ $aiHalted ? 'Nonaktif' : 'Aktif' }}</h3>
                <p class="mt-1 text-sm text-[#667085]">Halt memblokir semua panggilan AI baru untuk seluruh pengguna dan antrean. Permintaan yang sudah terkirim ke provider dapat tetap selesai.</p>
            </div>
            <form method="POST" action="{{ route($aiHalted ? 'token-monitor.activate' : 'token-monitor.halt') }}">
                @csrf
                <x-button :variant="$aiHalted ? 'success' : 'danger'">{{ $aiHalted ? 'Aktifkan AI' : 'Halt' }}</x-button>
            </form>
        </div>
    </x-card>

    <x-card>
        <form method="GET" action="{{ route('token-monitor.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto] lg:items-end">
            <div>
                <label for="from" class="block text-sm font-semibold text-[#071833] mb-2">Dari tanggal & jam</label>
                <input type="datetime-local" name="from" id="from" value="{{ $filters['from'] ?? '' }}" class="input-premium">
            </div>
            <div>
                <label for="until" class="block text-sm font-semibold text-[#071833] mb-2">Sampai tanggal & jam</label>
                <input type="datetime-local" name="until" id="until" value="{{ $filters['until'] ?? '' }}" class="input-premium">
            </div>
            <div class="flex items-center gap-2">
                <x-button variant="primary">Terapkan</x-button>
                <x-button :href="route('token-monitor.index')" variant="outline">Reset</x-button>
            </div>
        </form>
        @foreach (['from', 'until'] as $field)
            @error($field)
                <p class="mt-2 text-xs font-medium text-rose-600">{{ $message }}</p>
            @enderror
        @endforeach
    </x-card>

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <x-stat-card title="Total Token" :value="$fmt($totalTokens)" color="gold">
            <x-slot:icon>
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            </x-slot:icon>
        </x-stat-card>
        <x-stat-card title="Panggilan AI" :value="$fmt($totalRequests)" color="navy">
            <x-slot:icon>
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" /></svg>
            </x-slot:icon>
        </x-stat-card>
        <x-stat-card title="Rata-rata / Panggilan" :value="$fmt($totalRequests ? round($totalTokens / $totalRequests) : 0)" color="blue">
            <x-slot:icon>
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
            </x-slot:icon>
        </x-stat-card>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-card>
            <h3 class="mb-5 text-base font-bold text-[#071833]">Penggunaan per fitur</h3>
            <div class="space-y-4">
                @forelse ($bySource as $row)
                    <div>
                        <div class="flex justify-between gap-3 text-sm">
                            <span class="truncate font-semibold text-[#071833]">{{ $label($row->source) }}</span>
                            <span class="shrink-0 text-xs text-[#667085]">{{ $fmt($row->tokens) }} token · {{ $fmt($row->requests) }}x</span>
                        </div>
                        <div class="mt-1.5 h-2 rounded-full bg-[#f6f8fb] ring-1 ring-[#e7eaf0]">
                            <div class="h-2 rounded-full bg-gradient-to-r from-[#c99a3e] to-[#e6c06a]" style="width: {{ max(2, round($row->tokens / $maxTokens * 100)) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="py-10 text-center text-sm text-[#667085]">Belum ada penggunaan pada rentang ini.</p>
                @endforelse
            </div>
        </x-card>

        <x-card>
            <h3 class="mb-3 text-base font-bold text-[#071833]">Penggunaan per model</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-[#e7eaf0]">
                            <th class="text-left {{ $th }}">Provider / Model</th>
                            <th class="text-right {{ $th }}">Panggilan</th>
                            <th class="text-right {{ $th }}">Token</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#e7eaf0]">
                        @forelse ($byProvider as $row)
                            <tr class="hover:bg-[#f6f8fb] transition">
                                <td class="py-3.5 px-4">
                                    <span class="font-semibold text-[#071833]">{{ $row->provider }}</span>
                                    <span class="block text-xs text-[#667085]">{{ $row->model }}</span>
                                </td>
                                <td class="py-3.5 px-4 text-right text-[#667085]">{{ $fmt($row->requests) }}</td>
                                <td class="py-3.5 px-4 text-right font-bold text-[#071833]">{{ $fmt($row->tokens) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-10 text-center text-sm text-[#667085]">Belum ada data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>

    <x-card class="mt-6">
        <h3 class="mb-3 text-base font-bold text-[#071833]">Riwayat penggunaan</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-[#e7eaf0]">
                        <th class="text-left {{ $th }}">Tanggal & Jam</th>
                        <th class="text-left {{ $th }}">Pengguna</th>
                        <th class="text-left {{ $th }}">Fitur</th>
                        <th class="text-left {{ $th }}">Model</th>
                        <th class="text-right {{ $th }}">Token</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e7eaf0]">
                    @forelse ($usages as $usage)
                        <tr class="hover:bg-[#f6f8fb] transition">
                            <td class="py-3.5 px-4 whitespace-nowrap text-[#667085]">{{ $usage->created_at->format('d/m/Y H:i:s') }}</td>
                            <td class="py-3.5 px-4 font-semibold text-[#071833]">{{ $usage->user?->name ?? 'Sistem / antrean' }}</td>
                            <td class="py-3.5 px-4 text-[#071833]">{{ $label($usage->source) }}</td>
                            <td class="py-3.5 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-gold/15 text-[#8c6a25]">{{ $usage->provider }}</span>
                                <span class="block text-xs text-[#667085] mt-0.5">{{ $usage->model }}</span>
                            </td>
                            <td class="py-3.5 px-4 text-right font-bold text-[#071833]">{{ $fmt($usage->tokens) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-10 text-center text-sm text-[#667085]">Belum ada penggunaan pada rentang ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($usages->hasPages())
            <div class="mt-4">{{ $usages->links() }}</div>
        @endif
    </x-card>
@endsection
