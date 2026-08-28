<header class="sticky top-0 z-30 bg-white/85 backdrop-blur-xl border-b border-[#e7eaf0]">
    <div class="px-5 sm:px-8 lg:px-10">
        <div class="max-w-[1500px] mx-auto h-16 flex items-center justify-between gap-4">

            {{-- Left: Hamburger + Title --}}
            <div class="flex items-center gap-3 min-w-0">
                <button @click="sidebar = !sidebar" type="button"
                    class="lg:hidden inline-flex items-center justify-center w-10 h-10 rounded-xl border border-[#e7eaf0] text-[#071833] hover:bg-[#f6f8fb] transition"
                    aria-label="Toggle navigation">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <div class="min-w-0">
                    <div class="flex items-center gap-2 text-[11px] text-[#667085] font-medium">
                        <span class="hidden sm:inline">LawCo</span>
                        <svg class="hidden sm:inline w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" />
                        </svg>
                        <span class="text-[#c99a3e] font-semibold">@yield('header', 'Dashboard')</span>
                    </div>
                    <h1 class="text-lg sm:text-xl font-bold tracking-tight text-[#071833] truncate">@yield('header', 'Dashboard')
                    </h1>
                </div>
            </div>

            {{-- Center: Search --}}
            <div class="hidden md:flex flex-1 max-w-md">
                <label class="relative w-full">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-[#667085]">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m21 21-4.3-4.3M17 11a6 6 0 1 1-12 0 6 6 0 0 1 12 0Z" />
                        </svg>
                    </span>
                    <input type="search" placeholder="Search documents, regulations, reviews…"
                        class="w-full h-11 pl-11 pr-14 rounded-2xl bg-[#f6f8fb] border border-transparent text-sm placeholder:text-[#667085] text-[#071833] focus:outline-none focus:bg-white focus:border-[#c99a3e]/60 focus:ring-4 focus:ring-[#c99a3e]/15 transition">
                    <span class="absolute inset-y-0 right-0 hidden lg:flex items-center pr-3">
                        <kbd
                            class="px-1.5 py-0.5 text-[10px] font-semibold rounded-md border border-[#e7eaf0] bg-white text-[#667085]">⌘
                            K</kbd>
                    </span>
                </label>
            </div>

            {{-- Right: Actions --}}
            <div class="flex items-center gap-2.5">
                {{-- Mobile search --}}
                <button type="button"
                    class="md:hidden inline-flex items-center justify-center w-10 h-10 rounded-xl border border-[#e7eaf0] text-[#071833] hover:bg-[#f6f8fb] transition">
                    <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="m21 21-4.3-4.3M17 11a6 6 0 1 1-12 0 6 6 0 0 1 12 0Z" />
                    </svg>
                </button>

                {{-- Notification --}}
                <button type="button"
                    class="relative inline-flex items-center justify-center w-10 h-10 rounded-xl border border-[#e7eaf0] text-[#071833] hover:bg-[#f6f8fb] transition">
                    <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                    </svg>
                    <span
                        class="absolute top-2 right-2 inline-flex items-center justify-center w-2 h-2 rounded-full bg-[#c99a3e] ring-2 ring-white"></span>
                </button>

                <div class="hidden sm:block w-px h-7 bg-[#e7eaf0]"></div>
                @if (auth()->check())
                    <x-dropdown align="right" width="64">
                        <x-slot name="trigger">
                            <button type="button"
                                class="group flex items-center gap-2.5 pl-1.5 pr-3 py-1.5 rounded-2xl hover:bg-[#f6f8fb] transition">
                                <div class="relative">
                                    <div
                                        class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#071b3a] to-[#0b2a55] flex items-center justify-center text-white font-bold text-sm ring-1 ring-[#c99a3e]/40">
                                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                                    </div>
                                    <span
                                        class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-emerald-500 ring-2 ring-white"></span>
                                </div>
                                <div class="hidden sm:block text-left leading-tight">
                                    <p class="text-sm font-semibold text-[#071833] truncate max-w-[140px]">
                                        {{ auth()->user()->name }}</p>
                                    <p class="text-[11px] font-medium text-[#c99a3e] uppercase tracking-wider">
                                        {{ auth()->user()->role }}</p>
                                </div>
                                <svg class="hidden sm:block w-4 h-4 text-[#667085] group-hover:text-[#071833] transition"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                </svg>
                            </button>
                        </x-slot>

                        <div class="px-4 py-4 bg-gradient-to-br from-[#071b3a] to-[#0b2a55] text-white rounded-t-2xl">
                            <p class="text-sm font-semibold">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-white/70 truncate">{{ auth()->user()->email }}</p>
                            <div
                                class="mt-2 inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-[#c99a3e]/20 border border-[#c99a3e]/30 text-[10.5px] font-semibold text-[#e6c06a] uppercase tracking-wider">
                                <span class="w-1 h-1 rounded-full bg-[#e6c06a]"></span>
                                {{ ucfirst(auth()->user()->role) }}
                            </div>
                        </div>

                        <div class="py-2">
                            <a href="{{ route('dashboard') }}"
                                class="flex items-center gap-3 px-4 py-2.5 text-sm text-[#071833] hover:bg-[#f6f8fb] transition">
                                <svg class="w-4 h-4 text-[#667085]" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3 13.5 12 4l9 9.5M5 12v8a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-8" />
                                </svg>
                                Dashboard
                            </a>
                            <a href="{{ route('reviews.index') }}"
                                class="flex items-center gap-3 px-4 py-2.5 text-sm text-[#071833] hover:bg-[#f6f8fb] transition">
                                <svg class="w-4 h-4 text-[#667085]" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 12h6m-6 3h4M5 6h14v15l-3-2-2 2-2-2-2 2-2-2-3 2V6Z" />
                                </svg>
                                My Reviews
                            </a>
                            <a href="{{ route('profile.edit') }}"
                                class="flex items-center gap-3 px-4 py-2.5 text-sm text-[#071833] hover:bg-[#f6f8fb] transition">
                                <svg class="w-4 h-4 text-[#667085]" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                                Profil
                            </a>
                        </div>

                        <div class="border-t border-[#e7eaf0]">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"
                                    class="w-full flex items-center gap-3 px-4 py-3 text-sm font-medium text-red-600 hover:bg-red-50 transition">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3-3H9m12 0-3-3m3 3-3 3" />
                                    </svg>
                                    Sign Out
                                </button>
                            </form>
                        </div>
                    </x-dropdown>
                @else
                    <x-button href="{{ route('login') }}">Masuk</x-button>
                @endif

            </div>
        </div>
    </div>
</header>
