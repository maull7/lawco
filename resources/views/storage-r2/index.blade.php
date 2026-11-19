@extends('layouts.app')

@section('title', 'Storage R2')
@section('header', 'Storage R2')

@section('content')
    <div x-data="storageR2()" x-init="poll()">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#c99a3e]">Storage</p>
            <h2 class="mt-2 text-3xl font-bold tracking-tight text-[#071833]">Storage R2 (Cloudflare)</h2>
            <p class="mt-1.5 text-sm text-[#667085]">
                Pindahkan dokumen (regulasi, dokumen review, kasus, file kategori, bukti bayar) dari server ke Cloudflare R2.
                Disk aktif sekarang: <code class="rounded bg-[#f6f8fb] px-1">{{ $pdfDisk }}</code>
            </p>
        </div>

        <x-card class="mt-6">
            <div class="flex flex-wrap gap-3">
                <x-button type="button" variant="outline" size="md" @click="run('stats')" x-bind:disabled="running">1. Cek Ukuran &amp; Hasil</x-button>
                <x-button type="button" variant="outline" size="md" @click="run('check')" x-bind:disabled="running">2. Tes Koneksi R2</x-button>
                <x-button type="button" variant="primary" size="md" @click="confirm('Salin semua dokumen ke R2? File di server tidak dihapus.') && run('migrate')" x-bind:disabled="running">3. Migrasi ke R2</x-button>
                <x-button type="button" variant="outline" size="md" @click="run('prune-preview')" x-bind:disabled="running">4. Preview Hapus File Server</x-button>
                @if ($pdfDisk === $r2Disk)
                    <x-button type="button" variant="danger" size="md" @click="confirm('Hapus PERMANEN file di server yang sudah ada di R2 (ukuran sama)? Tidak bisa dibatalkan.') && run('prune')" x-bind:disabled="running">5. Hapus File Server</x-button>
                @else
                    <x-button type="button" variant="danger" size="md" disabled title="Aktif setelah PDF_DISK={{ $r2Disk }}" class="opacity-40 cursor-not-allowed">5. Hapus File Server</x-button>
                @endif
            </div>
            <ul class="mt-4 list-disc space-y-0.5 pl-5 text-xs text-[#667085]">
                <li><b>Cek Ukuran &amp; Hasil</b>: jumlah/ukuran file di server vs R2, dan berapa file di database yang sudah ada di R2.</li>
                <li><b>Migrasi</b> aman diulang: file yang sudah ada di R2 dilewati. Maksimal <b>{{ $dailyLimitGb }} GB per hari</b> (<code>R2_DAILY_COPY_LIMIT_GB</code>); sisa file dilanjutkan dengan klik Migrasi lagi besok. Setelah semua OK, set <code>PDF_DISK={{ $r2Disk }}</code> di .env lalu <code>php artisan config:clear</code>.</li>
                <li><b>Hapus File Server</b>: hanya menghapus file yang sudah ada di R2 dengan ukuran sama. Aktif setelah <code>PDF_DISK={{ $r2Disk }}</code>. Jalankan Preview dulu, dan sebaiknya tunggu 1–2 minggu pemakaian R2 lancar (file server = cadangan untuk rollback).</li>
            </ul>
            <p class="mt-3 text-sm" :class="error ? 'text-red-600' : 'text-[#071833]'" x-text="message"></p>
        </x-card>

        <x-card :padding="false" class="mt-6">
            <div class="flex items-center justify-between border-b border-[#e7eaf0] px-6 py-3">
                <h3 class="text-sm font-bold text-[#071833]">Log</h3>
                <span class="text-xs" :class="running ? 'text-[#c99a3e]' : 'text-[#667085]'" x-text="running ? 'Sedang berjalan…' : 'Idle'"></span>
            </div>
            <pre x-ref="log" class="h-[28rem] overflow-auto whitespace-pre-wrap bg-[#071833] p-4 font-mono text-xs text-gray-100" x-text="log || 'Belum ada log.'"></pre>
        </x-card>
    </div>

    <script>
        function storageR2() {
            return {
                running: false, log: '', message: '', error: false, timer: null,
                async run(action) {
                    this.error = false;
                    const res = await fetch(@json(route('storage-r2.run')), {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
                        body: JSON.stringify({ action }),
                    });
                    const data = await res.json().catch(() => ({}));
                    this.error = !res.ok;
                    this.message = data.message || (res.ok ? 'Dimulai.' : 'Gagal (' + res.status + ').');
                    if (res.ok) { this.running = true; setTimeout(() => this.poll(), 800); }
                },
                async poll() {
                    clearTimeout(this.timer);
                    try {
                        const data = await (await fetch(@json(route('storage-r2.log')), { headers: { 'Accept': 'application/json' } })).json();
                        const el = this.$refs.log;
                        const stick = el.scrollTop + el.clientHeight >= el.scrollHeight - 20;
                        this.log = data.log;
                        this.running = data.running;
                        if (stick) this.$nextTick(() => el.scrollTop = el.scrollHeight);
                    } catch (e) {}
                    if (this.running) this.timer = setTimeout(() => this.poll(), 2000);
                },
            };
        }
    </script>
@endsection
