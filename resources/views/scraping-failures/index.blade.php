@extends('layouts.app')

@section('title', 'Riwayat Scraping Gagal')
@section('header', 'Riwayat Scraping Gagal')

@section('content')
    <h2 class="text-3xl font-bold tracking-tight text-[#071833]">Riwayat Scraping Gagal</h2>
    <x-card class="mt-5 border border-[#e7eaf0] bg-slate-50 text-sm">
        <p class="font-semibold">Sinkronisasi otomatis: setiap hari pukul 01.00 WIB</p>
        <p class="mt-2 text-[#667085]">Proses otomatis di server produksi memeriksa sisa PDF di folder scraper untuk setiap target aktif. Hanya file yang tersedia, memiliki metadata JDIH, dan belum masuk Lawco yang diproses. File yang sudah dipindahkan tidak dihitung gagal atau pending.</p>
        <p class="mt-2 text-[#667085]">Folder kosong berarti tidak ada dokumen tersisa. File tanpa metadata atau jenis dokumen yang belum dikenali tetap disimpan untuk diperiksa. Kegagalan pemindahan tercatat di halaman ini; berhasil masuk berarti file sudah disalin dan regulasinya tersimpan di Lawco.</p>
    </x-card>
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
    <x-card class="mt-6">
        <h3 class="text-xl font-bold">Dokumen belum masuk karena jenis atau kategori</h3>
        <p class="mt-2 text-sm text-[#667085]">{{ $review['documents']->total() }} dokumen tersedia di folder sumber, tetapi jenis atau kategorinya belum dikenali. Riwayat lama bisa tampil Completed di Horizon. Mulai perubahan ini, batch dengan jenis atau kategori yang tidak dikenali akan berstatus Failed. Daftar ini mengikuti filter pencarian dan sektor di atas.</p>
        @if ($review['warning'])
            <p class="mt-3 text-sm text-amber-800">{{ $review['warning'] }}</p>
        @endif
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach ($review['categories'] as $category)
                <x-badge color="red">Kategori kurang: {{ $category['name'] }} — {{ $category['sector'] }} ({{ $category['count'] }} dokumen)</x-badge>
            @endforeach
        </div>
        <div class="mt-4 flex flex-wrap gap-2">
            @foreach ($review['types'] as $slug => $type)
                <x-badge color="yellow">{{ $type['name'] }} ({{ $slug ?: 'jenis kosong' }}): {{ $type['count'] }} dokumen</x-badge>
            @endforeach
        </div>
        <p class="mt-3 text-sm text-[#667085]">Menambah master Jenis Regulasi saja belum mengaktifkan retry untuk jenis yang belum dipetakan. Input manual dapat dilakukan dengan jenis yang sesuai; perbaikan pemetaan tetap diperlukan untuk sinkronisasi otomatis.</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <x-button href="{{ route('regulation-types.index') }}" variant="outline" size="sm">Kelola Jenis Regulasi</x-button>
            <x-button href="{{ route('regulation-categories.index') }}" variant="outline" size="sm">Kelola Kategori</x-button>
            <x-button href="{{ route('regulations.create') }}" variant="primary" size="sm">Tambah Regulasi Manual</x-button>
        </div>
        <div class="mt-4 overflow-x-auto">
            <table class="table-premium min-w-[900px]">
                <thead><tr><th>Dokumen / File</th><th>Sumber / Sektor</th><th>Jenis / Kategori JDIH</th><th>Yang perlu dilengkapi</th></tr></thead>
                <tbody>
                    @forelse ($review['documents'] as $document)
                        <tr>
                            <td class="max-w-md break-words">
                                <p class="font-semibold">{{ $document['title'] }}</p>
                                <p class="mt-1 text-sm">File: {{ $document['filename'] }}</p>
                                <p class="mt-1 text-xs text-[#667085]">ID JDIH: {{ $document['id'] }}</p>
                                <p class="mt-1 text-xs text-emerald-700">PDF masih tersedia di folder sumber</p>
                            </td>
                            <td>{{ $document['source_name'] }}<p class="mt-1 text-sm">{{ $document['sector'] }}</p></td>
                            <td><code>{{ $document['slug'] }}</code><p class="mt-1 text-sm">{{ $document['suggestion'] }}</p><p class="mt-2 text-sm">Kategori: {{ $document['category'] }}</p></td>
                            <td class="max-w-md text-sm"><p>{{ $document['reason'] }}</p><p class="mt-2 font-semibold">{{ $document['action'] }}</p></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-sm text-[#667085]">Tidak ada dokumen tertahan karena jenis atau kategori yang cocok dengan filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $review['documents']->links() }}</div>
    </x-card>

    <p class="mt-4 text-sm text-[#667085]">{{ $failures->total() }} proses gagal ditemukan. Jumlah gagal dihitung per dokumen saat percobaan tersebut; belum masuk juga dapat mencakup dokumen yang belum sempat diproses. Angka yang tidak tersimpan ditampilkan sebagai “Tidak tercatat”.</p>
    <p class="mt-2 text-sm text-[#667085]">Total database JDIH adalah seluruh regulasi yang tercatat untuk sumber tersebut saat ini, termasuk semua status. Jumlah ini berbeda dengan total dokumen dalam satu proses.</p>
    @if ($jdihWarning)
        <x-card class="mt-5 border border-amber-200 bg-amber-50 text-sm text-amber-800">{{ $jdihWarning }}</x-card>
    @endif

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
                                <p class="mb-2 font-semibold">Total database JDIH {{ $failure->summary['all_sources'] ? '(semua sumber)' : '(sumber ini)' }}: {{ $failure->jdih_total ?? 'Tidak tersedia' }}</p>
                                <p>Total proses: {{ $failure->summary['total'] ?? 'Tidak tercatat' }}</p>
                                <p class="mt-1 text-emerald-700">Sudah masuk saat ini: {{ $failure->imported_count ?? 'Tidak tercatat' }}</p>
                                <p class="mt-1 text-rose-700">Gagal saat percobaan: {{ $failure->summary['failed'] ?? 'Tidak tercatat' }}</p>
                                <p class="mt-1">Belum masuk: {{ $failure->remaining_count ?? 'Tidak tercatat' }}</p>
                            </td>
                            <td class="max-w-xl break-words">
                                <x-badge color="red">Gagal</x-badge>
                                <p class="mt-2 text-sm text-[#667085]">{{ $failure->summary['message'] }}</p>
                                <details class="mt-3">
                                    <summary class="cursor-pointer text-sm font-semibold text-[#071833]">Lihat file gagal / belum masuk ({{ count($failure->failed_documents) }})</summary>
                                    <div class="mt-3 max-h-96 overflow-auto space-y-3">
                                        @forelse ($failure->failed_documents as $document)
                                            <div class="rounded-xl border border-[#e7eaf0] p-3 text-sm">
                                                <p class="font-semibold">{{ $document['title'] }}</p>
                                                <p class="mt-1">File: {{ $document['filename'] }}</p>
                                                <p class="mt-1 text-xs text-[#667085]">{{ $document['source'] }} / {{ $document['id'] }}</p>
                                                <p class="mt-2 font-semibold">{{ $document['status'] }}</p>
                                                <p class="mt-1">{{ $document['file_status'] }}</p>
                                                <p class="mt-1 text-[#667085]">{{ $document['reason'] }}</p>
                                            </div>
                                        @empty
                                            <p class="text-sm text-[#667085]">Daftar file gagal tidak tersimpan pada riwayat ini, atau semua dokumen dalam batch sudah masuk. Jumlah gagal saja tidak cukup untuk menentukan nama filenya.</p>
                                        @endforelse
                                    </div>
                                </details>
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
