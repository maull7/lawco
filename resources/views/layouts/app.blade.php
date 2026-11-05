<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    @include('partials.google-analytics')
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'LawCo') }} — @yield('title', 'Compliance Workspace')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen font-sans antialiased text-[#071833]">
    <div x-data="{ sidebar: false }" class="flex min-h-screen">
        @if (auth()->check())
            {{-- Sudah login --}}
            <x-sidebar />
        @else
            {{-- Belum login --}}
            <x-landing-sidebar />
        @endif

        {{-- Mobile backdrop --}}
        <div x-show="sidebar" x-transition:enter="transition-opacity ease-out duration-300"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-200" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" @click="sidebar = false"
            class="fixed inset-0 z-30 bg-[#071b3a]/55 backdrop-blur-sm lg:hidden" style="display: none;"></div>

        <div class="flex-1 min-w-0 lg:pl-72">
            <x-navbar />

            <main class="px-5 sm:px-8 lg:px-10 py-8">
                <div class="max-w-[1500px] mx-auto space-y-6">
                    @if (session('success'))
                        <x-alert type="success" :message="session('success')" />
                    @endif
                    @if (session('error'))
                        <x-alert type="error" :message="session('error')" />
                    @endif
                    @if (session('info'))
                        <x-alert type="info" :message="session('info')" />
                    @endif

                    <div class="animate-fade-up">
                        @yield('content')
                    </div>
                </div>

                <footer
                    class="max-w-[1500px] mx-auto mt-16 pt-6 border-t border-[#e7eaf0] flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-[#667085]">
                    <p>&copy; {{ date('Y') }} {{ config('app.name', 'LawCo') }}. Legal · Strategic · Trusted.
                    </p>
                    <div class="flex items-center gap-4">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            All systems operational
                        </span>
                        <span>v1.0</span>
                    </div>
                </footer>
            </main>
        </div>
    </div>
    {{-- Peringatan dari admin (Putusan MA Download): sirene + suara + popup + notifikasi desktop. Route::has() mencegah error jika route cache belum diperbarui. --}}
    @if(Route::has('legal-decisions.work-alert.pending'))
        @can('access-legal-decisions')
            {{-- Banner izin: browser hanya mengizinkan suara/notifikasi setelah user menekan tombol --}}
            <div id="work-alert-permission" class="hidden fixed bottom-4 right-4 z-[100] max-w-xs p-4 rounded-xl bg-white border border-amber-300 shadow-xl text-sm">
                <p class="font-semibold text-[#071833]">Aktifkan suara peringatan</p>
                <p class="mt-1 text-xs text-slate-500">Izinkan suara dan notifikasi agar peringatan dari admin terdengar dengan volume penuh.</p>
                <button type="button" id="work-alert-enable" class="mt-3 w-full px-3 py-2 rounded-lg bg-amber-500 text-white text-xs font-bold hover:bg-amber-600">Izinkan Suara &amp; Notifikasi</button>
            </div>

            {{-- Popup peringatan: wajib ditutup user --}}
            <div id="work-alert-modal" class="hidden fixed inset-0 z-[110] flex items-center justify-center bg-red-900/70 p-4" role="alertdialog" aria-modal="true" aria-labelledby="work-alert-title">
                <div class="w-full max-w-md rounded-2xl bg-white p-6 text-center shadow-2xl ring-4 ring-red-500 animate-pulse">
                    <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-red-100 text-red-600">
                        <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" /></svg>
                    </div>
                    <p class="text-xs font-bold uppercase tracking-widest text-red-600">Peringatan dari Admin</p>
                    <h2 id="work-alert-title" class="mt-1 text-2xl font-extrabold text-[#071833]"></h2>
                    <p id="work-alert-sender" class="mt-1 text-xs text-slate-500"></p>
                    <button type="button" id="work-alert-ack" class="mt-5 w-full px-4 py-2.5 rounded-xl bg-red-600 text-white font-bold hover:bg-red-700">Siap, lanjut kerja</button>
                </div>
            </div>

            <script>
                (() => {
                    const url = @js(route('legal-decisions.work-alert.pending'));
                    const $ = (id) => document.getElementById(id);
                    const AudioCtx = window.AudioContext || window.webkitAudioContext;
                    let audioCtx = null;
                    let sirenTimer = null;

                    const unlockAudio = () => {
                        if (!AudioCtx) return;
                        audioCtx = audioCtx || new AudioCtx();
                        if (audioCtx.state === 'suspended') audioCtx.resume();
                    };

                    // Sirene naik-turun dengan gain maksimal (1.0) sampai popup ditutup.
                    const startSiren = () => {
                        unlockAudio();
                        if (!audioCtx) return;
                        const osc = audioCtx.createOscillator();
                        const gain = audioCtx.createGain();
                        osc.type = 'square';
                        gain.gain.value = 1;
                        osc.connect(gain).connect(audioCtx.destination);
                        osc.start();
                        let high = true;
                        sirenTimer = setInterval(() => { osc.frequency.value = high ? 960 : 640; high = !high; }, 400);
                        startSiren.stop = () => { clearInterval(sirenTimer); try { osc.stop(); } catch (e) {} };
                    };
                    const stopSiren = () => startSiren.stop?.();

                    const speak = (text) => {
                        if (!('speechSynthesis' in window)) return;
                        const utterance = new SpeechSynthesisUtterance(text);
                        utterance.lang = 'id-ID';
                        utterance.volume = 1;
                        utterance.rate = 0.95;
                        window.speechSynthesis.cancel();
                        window.speechSynthesis.speak(utterance);
                    };

                    const showAlert = (alert) => {
                        clearInterval(showAlert.repeat);
                        stopSiren();
                        $('work-alert-title').textContent = alert.message;
                        $('work-alert-sender').textContent = 'Dikirim oleh ' + alert.sent_by;
                        $('work-alert-modal').classList.remove('hidden');
                        startSiren();
                        // Sirene 3 detik dulu, lalu diucapkan; diulang tiap 8 detik sampai user menekan tombol.
                        setTimeout(() => { stopSiren(); speak(alert.message); }, 3000);
                        showAlert.repeat = setInterval(() => speak(alert.message), 8000);

                        if ('Notification' in window && Notification.permission === 'granted') {
                            const notification = new Notification('Peringatan dari Admin', { body: alert.message, requireInteraction: true, tag: 'work-alert' });
                            notification.onclick = () => { window.focus(); notification.close(); };
                        }
                    };

                    $('work-alert-ack').addEventListener('click', () => {
                        stopSiren();
                        clearInterval(showAlert.repeat);
                        window.speechSynthesis?.cancel();
                        $('work-alert-modal').classList.add('hidden');
                    });

                    // Banner izin muncul sampai user mengizinkan; klik apa pun di halaman juga membuka kunci audio.
                    const needsPermission = !localStorage.getItem('work-alert-enabled') || ('Notification' in window && Notification.permission === 'default');
                    if (needsPermission) $('work-alert-permission').classList.remove('hidden');
                    $('work-alert-enable').addEventListener('click', async () => {
                        unlockAudio();
                        speak('Suara peringatan aktif');
                        if ('Notification' in window && Notification.permission === 'default') {
                            try { await Notification.requestPermission(); } catch (e) {}
                        }
                        localStorage.setItem('work-alert-enabled', '1');
                        $('work-alert-permission').classList.add('hidden');
                    });
                    ['click', 'keydown'].forEach((type) => document.addEventListener(type, unlockAudio, { once: true }));

                    const poll = () => fetch(url, { headers: { 'Accept': 'application/json' } })
                        .then(r => r.ok ? r.json() : null)
                        .then(data => { if (data?.alert) showAlert(data.alert); })
                        .catch(() => {});
                    setInterval(poll, 15000);
                })();
            </script>
        @endcan
    @endif
    @stack('scripts')
</body>

</html>
