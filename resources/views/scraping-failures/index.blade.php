@extends('layouts.app')

@section('title', 'Riwayat Scraping Gagal')
@section('header', 'Riwayat Scraping Gagal')

@section('content')
    <h2 class="text-3xl font-bold tracking-tight text-[#071833]">Riwayat Scraping Gagal</h2>
    <p class="mt-2 text-sm text-[#667085]">Riwayat kegagalan antrean sinkronisasi JDIH ke Lawco, terbaru terlebih dahulu. Satu proses dapat mencakup beberapa dokumen.</p>
    <p class="mt-2 text-sm text-[#667085]">Kegagalan scraping di website sumber dan sinkronisasi manual tidak tercatat di halaman ini. Riwayat tersedia selama catatan antrean gagal belum dibersihkan.</p>
    <p class="mt-2 text-sm text-[#667085]">Retry menjalankan ulang sinkronisasi hasil scraper ke Lawco. Dokumen yang sudah berhasil masuk akan dilewati.</p>

    @if (session('success'))
        <x-card class="mt-5 border border-emerald-200 bg-emerald-50 text-sm text-emerald-800">{{ session('success') }}</x-card>
    @endif
    @if (session('error'))
        <x-card class="mt-5 border border-rose-200 bg-rose-50 text-sm text-rose-800">{{ session('error') }}</x-card>
    @endif

    <x-card :padding="false" class="mt-6">
        <div class="overflow-x-auto">
            <table class="table-premium min-w-[900px]">
                <thead><tr><th>Waktu Gagal</th><th>Proses / Sumber</th><th>Antrean</th><th>Penyebab Kegagalan</th><th>Aksi</th></tr></thead>
                <tbody>
                    @forelse ($failures as $failure)
                        <tr>
                            <td class="whitespace-nowrap">{{ $failure->failed_at }}</td>
                            <td class="max-w-xs break-words">
                                <p class="font-semibold text-[#071833]">{{ $failure->job_name }}</p>
                                <p class="mt-1 text-xs text-[#667085]">{{ $failure->uuid }}</p>
                            </td>
                            <td>{{ $failure->connection }} / {{ $failure->queue }}</td>
                            <td class="max-w-xl break-words">
                                <x-badge color="red">Gagal</x-badge>
                                <p class="mt-2 text-sm text-[#667085]">{{ $failure->error_message }}</p>
                                <details class="mt-3">
                                    <summary class="cursor-pointer text-sm font-semibold text-[#071833]">Detail error</summary>
                                    <pre class="mt-2 max-h-80 overflow-auto whitespace-pre-wrap break-words text-xs">{{ $failure->exception }}</pre>
                                </details>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('scraping-failures.retry', $failure->uuid) }}">
                                    @csrf
                                    <x-button type="submit" variant="primary" size="sm">Retry</x-button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-10 text-center text-sm text-[#667085]">Belum ada riwayat sinkronisasi scraping yang gagal.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
    <div class="mt-5">{{ $failures->links() }}</div>
@endsection
