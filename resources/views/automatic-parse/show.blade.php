@extends('layouts.app')
@section('title', 'Detail Riwayat Parse')
@section('header', 'Detail Riwayat Parse')
@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-3xl font-bold text-[#071833]">Detail Riwayat Parse</h2>
            <p class="mt-2 text-sm text-[#667085]">{{ $batch['source'] }} · {{ $batch['started_at'] }} WIB · {{ $batch['status'] }}</p>
        </div>
        <x-button href="{{ route('regulations.automatic-parse.history') }}" variant="outline">Kembali ke Riwayat</x-button>
    </div>
    <x-card class="mt-6">
        <p class="text-sm mb-2">Sektor: {{ $batch['sector'] }}</p>
        <p class="text-sm">Limit {{ $batch['limit'] }} · Total {{ $batch['total'] }} · Berhasil {{ $batch['complete'] }} · Belum lengkap {{ $batch['incomplete'] }} · Gagal {{ $batch['failed'] }} · Progres {{ $batch['percent'] }}%</p>
        <p class="mt-2 text-xs text-[#667085]">Berhasil berarti minimal 90% halaman menghasilkan teks. Riwayat menyimpan hasil pada saat batch berjalan.</p>
        @if ($batch['active'])<p class="mt-2 text-sm">Batch masih berjalan. Muat ulang untuk memperbarui progres.</p>@endif
    </x-card>
    <x-card class="mt-6">
        <div class="overflow-x-auto">
            <table class="table-premium w-full">
                <thead><tr><th>Regulasi</th><th>Sektor</th><th>Hasil Parse</th><th>Cakupan</th><th>Waktu Selesai</th><th>Keterangan</th></tr></thead>
                <tbody>
                    @forelse ($documents as $document)
                        <tr>
                            <td><p class="font-semibold">{{ $document['number'] }}</p><p class="mt-1 text-sm">{{ $document['title'] }}</p></td>
                            <td>{{ $document['sector'] ?? '—' }}</td>
                            <td>{{ ['queued' => 'Menunggu', 'parsing' => 'Sedang diproses', 'complete' => 'Berhasil', 'incomplete' => 'Belum lengkap', 'failed' => 'Gagal', 'skipped' => 'Dilewati', 'cancelled' => 'Dibatalkan'][$document['status']] ?? $document['status'] }}</td>
                            <td>{{ $document['percent'] }}%<p class="mt-1 text-xs">{{ $document['parsed_pages'] }} / {{ $document['total_pages'] ?? '—' }} halaman</p></td>
                            <td>{{ $document['finished_at'] ? \Illuminate\Support\Carbon::parse($document['finished_at'])->timezone('Asia/Jakarta')->format('d/m/Y H:i').' WIB' : '—' }}</td>
                            <td class="text-sm">{{ $document['error'] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center py-10">Riwayat per dokumen belum tersedia untuk batch lama.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $documents->links() }}</div>
    </x-card>
@endsection
