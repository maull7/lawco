@extends('layouts.app')

@section('title', 'Riwayat Scraping Gagal')
@section('header', 'Riwayat Scraping Gagal')

@section('content')
    <h2 class="text-3xl font-bold tracking-tight text-[#071833]">Riwayat Scraping Gagal</h2>
    <p class="mt-2 text-sm text-[#667085]">Riwayat kegagalan antrean sinkronisasi JDIH ke Lawco, terbaru terlebih dahulu. Satu proses dapat mencakup beberapa dokumen.</p>
    <p class="mt-2 text-sm text-[#667085]">Kegagalan scraping di website sumber dan sinkronisasi manual tidak tercatat di halaman ini. Riwayat tersedia selama catatan antrean gagal belum dibersihkan.</p>
    <p class="mt-2 text-sm text-[#667085]">Retry memeriksa PDF yang masih tersedia di folder scraper untuk sumber ini, lalu menyinkronkan dokumen yang belum masuk. Metadata dokumen tetap dicocokkan dari database; dokumen yang sudah masuk dilewati.</p>
    <p class="mt-2 text-sm text-[#667085]">Folder scraper hanya berisi file yang tersisa. File yang sudah masuk boleh dihapus dari folder tersebut karena salinannya ada di Lawco. File yang belum masuk tetapi hilang perlu diunduh ulang dari sumber.</p>

    <x-card class="mt-5">
        <form method="GET" action="{{ route('scraping-failures.index') }}" class="flex flex-col gap-4 md:flex-row md:items-end">
            <div class="flex-1">
                <label for="q" class="mb-2 block text-sm font-semibold">Cari riwayat</label>
                <input id="q" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="200" placeholder="Nama sumber, sektor, ID proses, atau pesan error" class="w-full rounded-xl border border-[#e7eaf0] px-4 py-2 text-sm">
            </div>
            <div>
                <label for="sector_id" class="mb-2 block text-sm font-semibold">Sektor</label>
                <select id="sector_id" name="sector_id" class="w-full rounded-xl border border-[#e7eaf0] px-4 py-2 text-sm">
                    <option value="">Semua sektor</option>
                    @foreach ($sectors as $sector)
                        <option value="{{ $sector->id }}" @selected(($filters['sector_id'] ?? '') == $sector->id)>{{ $sector->name }}</option>
                    @endforeach
                </select>
            </div>
            <x-button type="submit" variant="primary">Cari</x-button>
            <x-button href="{{ route('scraping-failures.index') }}" variant="outline">Reset</x-button>
        </form>
        @foreach ($errors->all() as $error)
            <p class="mt-2 text-sm text-rose-700">{{ $error }}</p>
        @endforeach
    </x-card>
    <p class="mt-4 text-sm text-[#667085]">{{ $failures->total() }} proses gagal ditemukan. Jumlah gagal dihitung per dokumen saat percobaan tersebut; belum masuk juga dapat mencakup dokumen yang belum sempat diproses. Angka yang tidak tersimpan ditampilkan sebagai “Tidak tercatat”.</p>

    @if (session('success'))
        <x-card class="mt-5 border border-emerald-200 bg-emerald-50 text-sm text-emerald-800">{{ session('success') }}</x-card>
    @endif
    @if (session('error'))
        <x-card class="mt-5 border border-rose-200 bg-rose-50 text-sm text-rose-800">{{ session('error') }}</x-card>
    @endif

    <x-card :padding="false" class="mt-6">
        <div class="overflow-x-auto">
            <table class="table-premium min-w-[900px]">
                <thead><tr><th>Waktu Gagal</th><th>Sumber / Sektor</th><th>Jumlah Dokumen</th><th>Penyebab Kegagalan</th><th>Aksi</th></tr></thead>
                <tbody>
                    @forelse ($failures as $failure)
                        <tr>
                            <td class="whitespace-nowrap">{{ $failure->failed_at }}</td>
                            <td class="max-w-xs break-words">
                                <p class="font-semibold text-[#071833]">{{ $failure->source_name }}</p>
                                <p class="mt-1 text-sm">Sektor: {{ $failure->sector_name }}</p>
                                <p class="mt-1 text-xs text-[#667085]">{{ $failure->job_name }}</p>
                                <p class="mt-1 text-xs text-[#667085]">{{ $failure->uuid }}</p>
                            </td>
                            <td class="whitespace-nowrap text-sm">
                                <p>Total proses: {{ $failure->summary['total'] ?? 'Tidak tercatat' }}</p>
                                <p class="mt-1 text-emerald-700">Sudah masuk saat ini: {{ $failure->imported_count ?? 'Tidak tercatat' }}</p>
                                <p class="mt-1 text-rose-700">Gagal saat percobaan: {{ $failure->summary['failed'] ?? 'Tidak tercatat' }}</p>
                                <p class="mt-1">Belum masuk: {{ $failure->remaining_count ?? 'Tidak tercatat' }}</p>
                            </td>
                            <td class="max-w-xl break-words">
                                <x-badge color="red">Gagal</x-badge>
                                <p class="mt-2 text-sm text-[#667085]">{{ $failure->summary['message'] }}</p>
                                <details class="mt-3">
                                    <summary class="cursor-pointer text-sm font-semibold text-[#071833]">Detail teknis</summary>
                                    <pre class="mt-2 max-h-80 overflow-auto whitespace-pre-wrap break-words text-xs">{{ $failure->exception }}</pre>
                                </details>
                            </td>
                            <td>
                                <form method="POST" action="{{ route('scraping-failures.retry', $failure->uuid) }}">
                                    @csrf
                                    <x-button type="submit" variant="primary" size="sm">Retry dari folder</x-button>
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
