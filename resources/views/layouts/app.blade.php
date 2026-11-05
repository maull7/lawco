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
    @can('access-legal-decisions')
        {{-- Peringatan suara dari admin (Putusan MA Download): polling tiap 15 detik --}}
        <div id="work-alert-toast" class="hidden fixed top-4 right-4 z-[100] max-w-sm p-4 rounded-xl bg-amber-500 text-white shadow-xl font-semibold"></div>
        <script>
            (() => {
                const url = @js(route('legal-decisions.work-alert.pending'));
                const toast = document.getElementById('work-alert-toast');
                const speak = (text) => {
                    if (!('speechSynthesis' in window)) return;
                    const utterance = new SpeechSynthesisUtterance(text);
                    utterance.lang = 'id-ID';
                    utterance.volume = 1;
                    window.speechSynthesis.cancel();
                    window.speechSynthesis.speak(utterance);
                };
                const poll = () => fetch(url, { headers: { 'Accept': 'application/json' } })
                    .then(r => r.ok ? r.json() : null)
                    .then(data => {
                        if (!data?.alert) return;
                        toast.textContent = data.alert.message + ' (dari ' + data.alert.sent_by + ')';
                        toast.classList.remove('hidden');
                        setTimeout(() => toast.classList.add('hidden'), 10000);
                        speak(data.alert.message);
                    })
                    .catch(() => {});
                setInterval(poll, 15000);
            })();
        </script>
    @endcan
    @stack('scripts')
</body>

</html>
