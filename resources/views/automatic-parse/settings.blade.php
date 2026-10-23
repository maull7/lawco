@extends('layouts.app')
@section('title', 'Pengaturan Parse Otomatis')
@section('header', 'Pengaturan Parse Otomatis')
@section('content')
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-3xl font-bold text-[#071833]">Pengaturan Parse Otomatis</h2>
            <p class="mt-2 text-sm text-[#667085]">Pilih sektor untuk parse otomatis setiap hari pukul 20.00 WIB.</p>
        </div>
        <x-button href="{{ route('regulations.index') }}" variant="outline">Kembali ke Regulasi</x-button>
    </div>
    <x-card class="mt-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="text-xl font-bold text-[#071833]">Sinkronisasi Manual</h3>
            <x-button href="{{ route('scraping-failures.index') }}" variant="outline" size="sm">Riwayat Sinkronisasi</x-button>
        </div>
        <p class="mt-2 text-sm text-[#667085]">Jalankan proses yang sama dengan jadwal pukul 01.00 sekarang. PDF yang sudah tersedia di folder scraper akan dimasukkan ke Lawco melalui antrean. Pilih satu sumber atau semua sumber aktif. Proses berjalan di latar belakang sehingga halaman ini tetap ringan.</p>
        <form x-data="{ submitting: false }" @submit="submitting = true" method="POST" action="{{ route('scraping-failures.run-sync') }}" class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
            @csrf
            <div class="flex-1">
                <label for="sync_source" class="mb-2 block text-sm font-semibold">Sumber sinkronisasi</label>
                <select id="sync_source" name="source" class="w-full rounded-xl border border-[#e7eaf0] px-4 py-2 text-sm" @disabled($activeTargets->isEmpty())>
                    <option value="">Semua sumber aktif ({{ $activeTargets->count() }})</option>
                    @foreach ($activeTargets as $target)
                        <option value="{{ $target->source }}" @selected(old('source') === $target->source)>{{ $target->name }} — {{ $target->sector?->name ?? 'Belum dipetakan' }}</option>
                    @endforeach
                </select>
                @error('source')
                    <p class="mt-2 text-sm text-rose-700">{{ $message }}</p>
                @enderror
            </div>
            <x-button :disabled="$activeTargets->isEmpty()" x-bind:disabled="submitting || @js($activeTargets->isEmpty())">
                <span x-show="!submitting">Jalankan Sinkronisasi Sekarang</span>
                <span x-show="submitting" x-cloak>Memasukkan ke antrean...</span>
            </x-button>
        </form>
        @if ($activeTargets->isEmpty())
            <p class="mt-2 text-sm text-amber-800">Belum ada sumber JDIH aktif. Aktifkan sumber di menu Target JDIH terlebih dahulu.</p>
        @endif
    </x-card>
    <x-card class="mt-6">
        <h3 class="font-bold text-[#071833]">Sektor Jadwal Harian</h3>
        @if ($selectedSectors->isNotEmpty() && $selectedSectors->count() === count($selectedSectorIds))
            <p class="mt-2 text-sm text-[#667085]">{{ $selectedSectors->count() }} sektor tersimpan · Maksimal 3.000 regulasi per malam · 500 dokumen per batch.</p>
            <details class="mt-2 text-sm text-[#667085]"><summary class="cursor-pointer">Lihat sektor tersimpan</summary><p class="mt-2 max-h-24 overflow-y-auto break-words">{{ $selectedSectors->pluck('name')->implode(', ') }}</p></details>
        @else
            <p class="mt-2 text-sm text-amber-700">{{ $selectedSectorIds !== [] ? 'Sektor tersimpan sudah tidak tersedia atau tidak aktif. Pilih sektor aktif kembali.' : 'Belum ada sektor yang dipilih. Simpan sektor untuk mengaktifkan parse terjadwal.' }}</p>
        @endif
        <form method="POST" action="{{ route('regulations.automatic-parse.settings.update') }}" class="mt-5 space-y-4 max-w-3xl">
            @csrf
            @method('PUT')
            <fieldset>
                <legend class="block text-sm font-semibold text-[#071833] mb-2">Sektor yang Diparse Pukul 20.00 WIB</legend>
                <p class="mb-3 text-xs text-[#667085]">Centang satu atau beberapa sektor.</p>
                <x-sector-selector :sectors="$sectors" :selected="old('sector_ids', $selectedSectorIds)" empty-label="Pilih minimal satu sektor untuk jadwal harian." />
                @error('sector_ids')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                @error('sector_ids.*')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
            </fieldset>
            <p class="text-sm text-[#667085]">Pilihan ini disimpan untuk jadwal setiap malam sampai Anda mengubahnya. Jika sektor tidak memiliki PDF yang belum diparse, jadwal dilewati.</p>
            <x-button type="submit" variant="primary" :disabled="$sectors->isEmpty()">Simpan Sektor Jadwal</x-button>
        </form>
    </x-card>
        <x-card class="mt-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="font-bold text-[#071833]">Jalankan Parse Sekarang</h3>
                <div class="flex flex-wrap gap-2">
                    <x-button href="{{ route('regulations.automatic-parse.history') }}" variant="outline" size="sm">Riwayat Parse</x-button>
                </div>
            </div>
            <p class="mt-1 text-sm text-[#667085]">Jadwal pukul 20.00 WIB mengikuti sektor yang disimpan di menu Pengaturan Parse Otomatis, maksimal 3.000 regulasi dalam batch berisi maksimal 500 dokumen. Form di bawah untuk menjalankan parse langsung.</p>
            <form method="POST" action="{{ route('regulations.automatic-parse') }}" class="mt-5 grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
                @csrf
                <div>
                    <label for="automatic-parse-limit" class="block text-sm font-semibold mb-1">Limit dokumen (1–3.000)</label>
                    <input id="automatic-parse-limit" name="limit" type="number" min="1" max="3000" required value="{{ old('limit', 3000) }}" class="input-premium" @disabled($automaticParseSummary['active'] ?? false)>
                    @error('limit')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <fieldset class="min-w-0 lg:col-span-2">
                    <legend class="block text-sm font-semibold mb-1">Sektor yang Diparse</legend>
                    <x-sector-selector :sectors="$sectors" :selected="old('sector_ids', $selectedSectorIds)" :disabled="$automaticParseSummary['active'] ?? false" empty-label="Semua sektor akan diparse jika tidak ada pilihan." />
                    @error('sector_ids')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                    @error('sector_ids.*')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </fieldset>
                <div class="lg:col-span-3 border-t border-[#e7eaf0] pt-4 flex justify-end">
                    <x-button type="submit" variant="primary" :disabled="$automaticParseSummary['active'] ?? false">Mulai Parse Sekarang</x-button>
                </div>
            </form>
            @if ($automaticParseSummary)
                <div class="mt-4 text-sm text-[#667085]" role="status">
                    <p class="font-semibold">{{ $automaticParseSummary['active'] ? 'Batch sedang berjalan' : 'Batch terakhir selesai' }} · {{ $automaticParseSummary['percent'] }}%</p>
                    <p class="mt-1">Jumlah batch: {{ $automaticParseSummary['batches'] }} (maksimal 500 dokumen per batch)</p>
                    <details class="mt-2"><summary class="cursor-pointer font-semibold">Lihat sektor dalam proses ini</summary><p class="mt-2 max-h-24 overflow-y-auto break-words">{{ $automaticParseSummary['sector'] }}</p></details>
                    <p class="mt-1">Total: {{ $automaticParseSummary['total'] }} · Menunggu/berjalan: {{ $automaticParseSummary['pending'] }} · Complete: {{ $automaticParseSummary['complete'] }} · Incomplete: {{ $automaticParseSummary['incomplete'] }} · Gagal: {{ $automaticParseSummary['failed'] }}</p>
                    @if ($automaticParseSummary['sectors'] !== [])
                        @php($coverage = $automaticParseSummary['coverage'])
                        @if ($coverage['sector_total'] > 0)
                            <div @class(['mt-3 rounded-xl border p-3', 'border-emerald-200 bg-emerald-50 text-emerald-800' => $coverage['covers_all'], 'border-amber-200 bg-amber-50 text-amber-800' => ! $coverage['covers_all']])>
                                <p class="font-semibold">Cakupan sektor terpilih: {{ $coverage['percent'] }}% ({{ $coverage['sector_parsed'] }} dari {{ $coverage['sector_total'] }} regulasi sudah diparse)</p>
                                <p class="mt-1">
                                    @if ($coverage['covers_all'])
                                        Proses ini sudah mencakup semua regulasi di sektor terpilih. Tidak ada regulasi yang tersisa belum diparse.
                                    @else
                                        Proses ini ({{ $automaticParseSummary['total'] }} dokumen) belum mencakup semua regulasi di sektor terpilih. Sisa {{ $coverage['remaining'] }} regulasi belum diparse.
                                    @endif
                                </p>
                            </div>
                        @endif
                        <div class="mt-3 overflow-x-auto">
                            <table class="min-w-full text-left text-xs">
                                <thead><tr class="border-b border-[#e7eaf0]"><th class="py-2 pr-4">Sektor</th><th class="py-2 pr-4">Total regulasi</th><th class="py-2 pr-4">Diproses</th><th class="py-2 pr-4">Berhasil diparse</th><th class="py-2 pr-4">Incomplete</th><th class="py-2 pr-4">Gagal</th><th class="py-2 pr-4">Sudah diparse (total)</th><th class="py-2">Sisa belum diparse</th></tr></thead>
                                <tbody>
                                    @foreach ($automaticParseSummary['sectors'] as $sectorResult)
                                        <tr class="border-b border-[#e7eaf0]">
                                            <td class="py-2 pr-4 font-semibold">{{ $sectorResult['name'] }}</td>
                                            <td class="py-2 pr-4">{{ $sectorResult['sector_total'] ?? '-' }}</td>
                                            <td class="py-2 pr-4">{{ $sectorResult['processed'] }}</td>
                                            <td class="py-2 pr-4">{{ $sectorResult['complete'] }}</td>
                                            <td class="py-2 pr-4">{{ $sectorResult['incomplete'] }}</td>
                                            <td class="py-2 pr-4">{{ $sectorResult['failed'] }}</td>
                                            <td class="py-2 pr-4">{{ $sectorResult['sector_parsed'] === null ? '-' : $sectorResult['sector_parsed'].' ('.$sectorResult['coverage_percent'].'%)' }}</td>
                                            <td class="py-2">{{ $sectorResult['remaining'] ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                    @if ($automaticParseSummary['failed_documents'] !== [])
                        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3">
                            <p class="font-semibold text-rose-700">{{ count($automaticParseSummary['failed_documents']) }} regulasi gagal diparse otomatis. Jalankan parse manual per regulasi:</p>
                            <ul class="mt-2 max-h-80 space-y-2 overflow-y-auto">
                                @foreach ($automaticParseSummary['failed_documents'] as $failedDocument)
                                    <li class="flex flex-wrap items-start justify-between gap-2 border-b border-rose-100 pb-2">
                                        <div class="min-w-0 flex-1">
                                            <a href="{{ route('regulations.show', $failedDocument['id']) }}" class="font-semibold text-[#071833] hover:underline">{{ $failedDocument['number'] }} — {{ $failedDocument['title'] }}</a>
                                            <p class="text-xs">Sektor: {{ $failedDocument['sector'] ?? '-' }} · Alasan: {{ $failedDocument['error'] ?? 'Tidak diketahui' }}</p>
                                        </div>
                                        <form method="POST" action="{{ route('regulations.parse', $failedDocument['id']) }}">
                                            @csrf
                                            <x-button type="submit" variant="outline" size="sm">Parse Manual</x-button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if ($automaticParseSummary['active'])<p class="mt-1">Muat ulang halaman untuk melihat progres terbaru.</p>@endif
                </div>
            @endif
        </x-card>
        <x-card class="mt-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="font-bold text-[#071833]">Ekstrak Peraturan Terkait Otomatis</h3>
                <x-button href="{{ route('regulations.automatic-extract.history') }}" variant="outline" size="sm">Riwayat Ekstrak</x-button>
            </div>
            <p class="mt-1 text-sm text-[#667085]">Jadwal pukul 22.00 WIB mengikuti sektor yang disimpan di atas, maksimal 1.000 regulasi yang sudah diparse lengkap dan belum pernah diekstrak. Peraturan yang cocok persis (nomor dan tahun) dengan regulasi di Lawco langsung dihubungkan.</p>
            <form method="POST" action="{{ route('regulations.automatic-extract') }}" class="mt-5 grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
                @csrf
                <div>
                    <label for="automatic-extract-limit" class="block text-sm font-semibold mb-1">Limit dokumen (1–1.000)</label>
                    <input id="automatic-extract-limit" name="limit" type="number" min="1" max="1000" required value="{{ old('limit', 1000) }}" class="input-premium" @disabled($automaticExtractSummary['active'] ?? false)>
                    @error('limit')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
                </div>
                <fieldset class="min-w-0 lg:col-span-2">
                    <legend class="block text-sm font-semibold mb-1">Sektor yang Diekstrak</legend>
                    <x-sector-selector :sectors="$sectors" :selected="old('sector_ids', $selectedSectorIds)" :disabled="$automaticExtractSummary['active'] ?? false" empty-label="Semua sektor akan diekstrak jika tidak ada pilihan." />
                </fieldset>
                <div class="lg:col-span-3 border-t border-[#e7eaf0] pt-4 flex justify-end">
                    <x-button type="submit" variant="primary" :disabled="$automaticExtractSummary['active'] ?? false">Mulai Ekstrak Sekarang</x-button>
                </div>
            </form>
            @if ($automaticExtractSummary)
                <div class="mt-4 text-sm text-[#667085]" role="status">
                    <p class="font-semibold">{{ $automaticExtractSummary['active'] ? 'Ekstrak sedang berjalan' : 'Ekstrak terakhir selesai' }} · {{ $automaticExtractSummary['percent'] }}%</p>
                    <p class="mt-1">Sektor: {{ $automaticExtractSummary['sector'] }}</p>
                    <p class="mt-1">Total: {{ $automaticExtractSummary['total'] }} · Menunggu/berjalan: {{ $automaticExtractSummary['pending'] }} · Berhasil: {{ $automaticExtractSummary['complete'] }} · Gagal: {{ $automaticExtractSummary['failed'] }} · Referensi: {{ $automaticExtractSummary['references'] }} · Terhubung otomatis: {{ $automaticExtractSummary['linked'] }}</p>
                    @if ($automaticExtractSummary['failed_documents'] !== [])
                        <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3">
                            <p class="font-semibold text-rose-700">{{ count($automaticExtractSummary['failed_documents']) }} regulasi gagal diekstrak otomatis. Ulangi dari halaman detail regulasi:</p>
                            <ul class="mt-2 max-h-80 space-y-2 overflow-y-auto">
                                @foreach ($automaticExtractSummary['failed_documents'] as $failedDocument)
                                    <li class="border-b border-rose-100 pb-2">
                                        <a href="{{ route('regulations.show', $failedDocument['id']) }}" class="font-semibold text-[#071833] hover:underline">{{ $failedDocument['number'] }} — {{ $failedDocument['title'] }}</a>
                                        <p class="text-xs">Alasan: {{ $failedDocument['error'] ?? 'Tidak diketahui' }}</p>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if ($automaticExtractSummary['active'])<p class="mt-1">Muat ulang halaman untuk melihat progres terbaru.</p>@endif
                </div>
            @endif
        </x-card>
@endsection
