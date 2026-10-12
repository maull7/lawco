@extends('layouts.app')
@section('title', 'Riwayat Parse Otomatis')
@section('header', 'Riwayat Parse Otomatis')
@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-3xl font-bold text-[#071833]">Riwayat Parse Otomatis</h2>
            <p class="mt-2 text-sm text-[#667085]">Hasil batch dari jadwal malam dan tombol manual. Waktu ditampilkan dalam WIB.</p>
        </div>
        <x-button href="{{ route('regulations.index') }}" variant="outline">Kembali ke Regulasi</x-button>
    </div>
    <x-card class="mt-6">
        <div class="overflow-x-auto">
            <table class="table-premium w-full">
                <thead><tr><th>Dibuat / Selesai</th><th>Pemicu</th><th>Limit / Total</th><th>Hasil Dokumen</th><th>Status Batch</th><th>Aksi</th></tr></thead>
                <tbody>
                    @forelse ($history as $batch)
                        <tr>
                            <td><p class="font-semibold">{{ $batch['started_at'] }} WIB</p><p class="mt-1 text-xs">Selesai: {{ $batch['finished_at'] ? $batch['finished_at'].' WIB' : '—' }}</p></td>
                            <td>{{ $batch['source'] }}<p class="mt-1 text-xs">{{ $batch['creator'] }}</p></td>
                            <td>{{ $batch['limit'] }} / {{ $batch['total'] }}</td>
                            <td>
                                @if ($batch['has_results'])
                                    <p class="text-emerald-700">Berhasil: {{ $batch['complete'] }}</p>
                                    <p class="text-amber-700">Belum lengkap: {{ $batch['incomplete'] }}</p>
                                    <p class="text-rose-700">Gagal: {{ $batch['failed'] }}</p>
                                    <p class="text-xs mt-1">Dilewati: {{ $batch['skipped'] }} · Dibatalkan: {{ $batch['cancelled'] }}</p>
                                @else
                                    <p class="text-sm text-[#667085]">Hasil per dokumen belum dicatat pada batch lama.</p>
                                @endif
                            </td>
                            <td><span class="font-semibold">{{ $batch['status'] }}</span><p class="mt-1 text-xs">Progres {{ $batch['percent'] }}% · Tersisa {{ $batch['pending'] }}</p></td>
                            <td><x-button href="{{ route('regulations.automatic-parse.history.show', $batch['id']) }}" variant="outline" size="sm">Detail</x-button></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-10">Belum ada riwayat parse otomatis.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $history->links() }}</div>
    </x-card>
@endsection
