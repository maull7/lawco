@extends('layouts.app')
@section('title', 'Detail Riwayat Ekstrak')
@section('header', 'Detail Riwayat Ekstrak')
@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-3xl font-bold text-[#071833]">Detail Riwayat Ekstrak</h2>
            <p class="mt-2 text-sm text-[#667085]">{{ $batch['source'] }} · {{ $batch['started_at'] }} WIB · {{ $batch['status'] }}</p>
        </div>
        <x-button href="{{ route('regulations.automatic-extract.history') }}" variant="outline">Kembali ke Riwayat</x-button>
    </div>
    <x-card class="mt-6">
        <p class="text-sm mb-2">Batch {{ $batch['batch_index'] }} / {{ $batch['batch_count'] }} · Sektor: {{ $batch['sector'] }}</p>
        <p class="text-sm">Limit {{ $batch['limit'] }} · Total {{ $batch['total'] }} · Berhasil {{ $batch['complete'] }} · Gagal {{ $batch['failed'] }} · Referensi {{ $batch['references'] }} · Terhubung {{ $batch['linked'] }} · Progres {{ $batch['percent'] }}%</p>
        <p class="mt-2 text-xs text-[#667085]">Terhubung berarti referensi cocok persis dengan satu regulasi di Lawco (tahun sama) dan sudah ditambahkan ke Peraturan Terkait.</p>
        @if ($batch['active'])<p class="mt-2 text-sm">Batch masih berjalan. Muat ulang untuk memperbarui progres.</p>@endif
    </x-card>
    <x-card class="mt-6">
        <div class="overflow-x-auto">
            <table class="table-premium w-full">
                <thead><tr><th>Regulasi</th><th>Sektor</th><th>Hasil</th><th>Referensi / Terhubung</th><th>Waktu Selesai</th><th>Keterangan</th></tr></thead>
                <tbody>
                    @forelse ($documents as $document)
                        <tr>
                            <td><a href="{{ route('regulations.show', $document['id']) }}" class="font-semibold hover:underline">{{ $document['number'] }}</a><p class="mt-1 text-sm">{{ $document['title'] }}</p></td>
                            <td>{{ $document['sector'] ?? '—' }}</td>
                            <td>{{ ['queued' => 'Menunggu', 'processing' => 'Sedang diproses', 'complete' => 'Berhasil', 'failed' => 'Gagal', 'skipped' => 'Dilewati', 'cancelled' => 'Dibatalkan'][$document['status']] ?? $document['status'] }}</td>
                            <td>{{ $document['references'] ?? '—' }} / {{ $document['linked'] ?? '—' }}</td>
                            <td>{{ $document['finished_at'] ? \Illuminate\Support\Carbon::parse($document['finished_at'])->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB' : '—' }}</td>
                            <td class="text-sm">{{ $document['error'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-10">Riwayat per dokumen belum tersedia.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $documents->links() }}</div>
    </x-card>
@endsection
