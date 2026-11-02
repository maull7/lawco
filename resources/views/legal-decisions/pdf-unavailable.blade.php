<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PDF Putusan Tidak Tersedia</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #fff; color: #071833; font-family: ui-sans-serif, system-ui, sans-serif; }
        .box { max-width: 420px; padding: 24px; text-align: center; }
        h1 { font-size: 16px; margin: 0 0 8px; }
        p { font-size: 13px; color: #64748b; line-height: 1.6; margin: 0 0 16px; }
        .actions { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        a { display: inline-block; padding: 8px 14px; border-radius: 8px; font-size: 12px; font-weight: 700; text-decoration: none; }
        .primary { background: #071833; color: #fff; }
        .secondary { background: #946d25; color: #fff; }
    </style>
</head>
<body>
    <div class="box">
        <h1>PDF putusan belum bisa ditampilkan</h1>
        <p>{{ $reason ?? 'Koneksi ke layanan pengambil PDF Mahkamah Agung sedang terputus atau lambat.' }} Coba muat ulang beberapa saat lagi, atau buka langsung di portal MA.</p>
        <div class="actions">
            <a class="primary" href="{{ request()->fullUrl() }}">Muat ulang</a>
            @if(in_array(parse_url($sourceUrl, PHP_URL_SCHEME), ['http', 'https'], true))
                <a class="secondary" href="{{ $sourceUrl }}" target="_blank" rel="noopener">Buka di Portal MA</a>
            @endif
        </div>
    </div>
</body>
</html>
