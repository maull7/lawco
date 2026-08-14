<aside class="fixed inset-y-0 left-0 z-40 w-72 transform transition-transform duration-300 ease-out lg:translate-x-0"
    :class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
    <div class="relative h-full overflow-hidden bg-navy-gradient text-white sidebar-scroll overflow-y-auto">


        {{-- Decorative glow --}}
        <div class="pointer-events-none absolute -top-24 -right-24 w-72 h-72 rounded-full bg-[#c99a3e]/20 blur-3xl">
        </div>
        <div class="pointer-events-none absolute bottom-0 -left-24 w-72 h-72 rounded-full bg-[#0b2a55]/40 blur-3xl">
        </div>

        <div class="relative flex flex-col h-full px-5 py-7">
            {{-- Brand --}}
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-2 group">
                <div class="relative">
                    <div
                        class="w-11 h-11 rounded-2xl bg-gradient-to-br from-[#c99a3e] to-[#e6c06a] flex items-center justify-center shadow-[0_10px_30px_rgba(201,154,62,.35)]">
                        <svg class="w-6 h-6 text-[#071b3a]" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 3v18M5 8l7-5 7 5M3 8h18M5 21h14M7 8v9M17 8v9" />
                        </svg>
                    </div>
                    <span
                        class="absolute -bottom-1 -right-1 w-3 h-3 rounded-full bg-emerald-400 ring-2 ring-[#071b3a]"></span>
                </div>
                <div>
                    <p class="text-base font-bold tracking-tight text-white">{{ config('app.name', 'InvestaLaw') }}</p>
                    <p class="text-[10.5px] font-medium tracking-[0.18em] uppercase text-[#c99a3e]">Legal · Strategic
                    </p>
                </div>
            </a>

            {{-- AUM-like card (executive summary) --}}
            <div class="mt-7 rounded-2xl border border-white/10 bg-white/5 backdrop-blur p-4">
                <div class="flex items-center justify-between">
                    <p class="text-[11px] uppercase tracking-[0.16em] text-white/60">Workspace</p>
                    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-emerald-300">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                        Live
                    </span>
                </div>
                <p class="mt-2 text-lg font-bold text-white">Compliance Suite</p>
                <p class="text-xs text-white/55 mt-0.5">Regulatory · Capital Markets</p>
            </div>

            @if (auth()->user()->role == 'user')
                <a href="{{ route('index') }}"
                    class="mt-7 block rounded-2xl border border-white/10 bg-gradient-to-br from-[#c99a3e] to-gray-600/70 px-4 py-2 shadow-lg transition duration-200 hover:-translate-y-0.5 hover:shadow-xl hover:border-white/20">

                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-white">
                            Profile
                        </p>
                    </div>

                    <p class="mt-2 text-lg font-bold text-white">
                        InvestalawCo Profile
                    </p>

                    <p class="mt-1 text-xs text-white/60">
                        View company profile and information
                    </p>

                </a>
            @endif



            {{-- Navigation --}}
            <nav class="mt-7 flex-1">
                @php
                    $user = auth()->user();
                    $canManageCategories = $user->hasPermission('manage_categories');
                    $canManageSubCategories = $user->hasPermission('manage_sub_categories');
                    $canManageTypes = $user->hasPermission('manage_types');
                    $canUploadRegulations = $user->hasPermission('upload_regulations');
                    $canManagePrompts = $user->hasPermission('manage_prompts');
                    $showMasterData =
                        $canManageCategories ||
                        $canManageSubCategories ||
                        $canManageTypes ||
                        $canUploadRegulations ||
                        $canManagePrompts;
                @endphp

                <p class="px-3 mb-2 text-[10.5px] font-semibold tracking-[0.18em] uppercase text-white/45">Overview</p>
                <ul class="space-y-1.5">
                    <li>
                        <a href="{{ route('dashboard') }}"
                            class="nav-item {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
                            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="1.6">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M3 13.5 12 4l9 9.5M5 12v8a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1v-8" />
                            </svg>
                            <span>{{ $user->isAdmin() ? 'Dashboard' : 'Main Page' }}</span>
                        </a>
                    </li>
                </ul>

                @if ($user->isAdmin())
                    <p class="px-3 mt-7 mb-2 text-[10.5px] font-semibold tracking-[0.18em] uppercase text-white/45">
                        Administration</p>
                    <ul class="space-y-1.5">
                        <li>
                            <a href="{{ route('users.index') }}"
                                class="nav-item {{ request()->routeIs('users.*') ? 'is-active' : '' }}">
                                <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                                <span>Manage Users</span>
                            </a>
                            <a href="{{ route('manage.users.from-register') }}"
                                class="nav-item {{ request()->routeIs('manage.users.from-register') ? 'is-active' : '' }}">
                                <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                </svg>
                                <span>Manage Registrant</span>
                            </a>
                            <a href="{{ route('legal-necessities.index') }}"
                                class="nav-item {{ request()->routeIs('legal-necessities.*') ? 'is-active' : '' }}">
                                <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                                </svg>
                                <span>Kebutuhan Hukum</span>
                            </a>
                            <a href="{{ route('packages.index') }}"
                                class="nav-item {{ request()->routeIs('packages.*') ? 'is-active' : '' }}">
                                <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z" />
                                </svg>
                                <span>Master Paket</span>
                            </a>
                            <a href="{{ route('confirm.packages.payment.confirmations') }}"
                                class="nav-item {{ request()->routeIs('confirm.packages.payment.confirmations') ? 'is-active' : '' }}">
                                <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" />
                                </svg>
                                <span>Konfirmasi Pembayaran</span>
                            </a>
                            <a href="{{ route('legal-cases.index') }}"
                                class="nav-item {{ request()->routeIs('legal-cases.*') ? 'is-active' : '' }}">
                                <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5m.75-9 3-3 2.148 2.148A12.061 12.061 0 0 1 16.5 7.605V10.5m-9-1.5h6" />
                                </svg>
                                <span>Analisa Kasus</span>
                            </a>
                        </li>
                    </ul>
                @endif

                @if ($showMasterData)
                    <p class="px-3 mt-7 mb-2 text-[10.5px] font-semibold tracking-[0.18em] uppercase text-white/45">
                        Master
                        Data</p>
                    <ul class="space-y-1.5">

                        @if ($canManageCategories)
                            <li>
                                <a href="{{ route('sectors.index') }}"
                                    class="nav-item {{ request()->routeIs('sectors.*') ? 'is-active' : '' }}">
                                    <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                    </svg>
                                    <span>Sektor</span>
                                </a>
                            </li>
                        @endif
                        @if ($canManageCategories)
                            <li>
                                <a href="{{ route('regulation-categories.index') }}"
                                    class="nav-item {{ request()->routeIs('regulation-categories.*') ? 'is-active' : '' }}">
                                    <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M6 6h.008v.008H6V6Z" />
                                    </svg>
                                    <span>Kategori</span>
                                </a>
                            </li>
                        @endif
                        @if ($canManageSubCategories)
                            <li>
                                <a href="{{ route('sub-categories.index') }}"
                                    class="nav-item {{ request()->routeIs('sub-categories.*') ? 'is-active' : '' }}">
                                    <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 0 1 0 2.828l-7 7a2 2 0 0 1-2.828 0l-7-7A2 2 0 0 1 3 12V7a4 4 0 0 1 4-4Z" />
                                    </svg>
                                    <span>Sub Category</span>
                                </a>
                            </li>
                        @endif
                        @if ($canManageTypes)
                            <li>
                                <a href="{{ route('regulation-types.index') }}"
                                    class="nav-item {{ request()->routeIs('regulation-types.*') ? 'is-active' : '' }}">
                                    <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L12 12.75 6.429 9.75m11.142 0 4.179 2.25-4.179 2.25m0 0L12 17.25 6.429 14.25m11.142 0 4.179 2.25L12 21.75l-9.75-5.25 4.179-2.25" />
                                    </svg>
                                    <span>Jenis Regulasi</span>
                                </a>
                            </li>
                        @endif
                        @if ($canUploadRegulations)
                            <li>
                                <a href="{{ route('regulations.index') }}"
                                    class="nav-item {{ request()->routeIs('regulations.*') ? 'is-active' : '' }}">
                                    <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                    </svg>
                                    <span>Regulasi</span>
                                </a>
                            </li>
                        @endif
                        @if ($canManagePrompts)
                            <li>
                                <a href="{{ route('ai-prompts.index') }}"
                                    class="nav-item {{ request()->routeIs('ai-prompts.*') ? 'is-active' : '' }}">
                                    <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456Z" />
                                    </svg>
                                    <span>AI Prompts</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('type-prompts.index') }}"
                                    class="nav-item {{ request()->routeIs('type-prompts.*') ? 'is-active' : '' }}">
                                    <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M6 6h.008v.008H6V6Z" />
                                    </svg>
                                    <span>Type Prompt</span>
                                </a>
                            </li>
                        @endif
                    </ul>
                @endif

                @if (!$user->isSubAdmin())
                    @if (!$user->isAdmin())
                        <p
                            class="px-3 mt-7 mb-2 text-[10.5px] font-semibold tracking-[0.18em] uppercase text-white/45">
                            Regulasi</p>
                    @endif
                    <ul class="space-y-1.5">
                        @if (!$user->hasPermission('upload_regulations'))
                            <li>
                                <a href="{{ route('user.regulation-categories.index') }}"
                                    class="nav-item {{ request()->routeIs('user.regulation-categories.*') ? 'is-active' : '' }}">
                                    <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M6 6h.008v.008H6V6Z" />
                                    </svg>
                                    <span>Kategori</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('user.sub-categories.index') }}"
                                    class="nav-item {{ request()->routeIs('user.sub-categories.*') ? 'is-active' : '' }}">
                                    <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 0 1 0 2.828l-7 7a2 2 0 0 1-2.828 0l-7-7A2 2 0 0 1 3 12V7a4 4 0 0 1 4-4Z" />
                                    </svg>
                                    <span>Sub Category</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('user.regulation-types.index') }}"
                                    class="nav-item {{ request()->routeIs('user.regulation-types.*') ? 'is-active' : '' }}">
                                    <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L12 12.75 6.429 9.75m11.142 0 4.179 2.25-4.179 2.25m0 0L12 17.25 6.429 14.25m11.142 0 4.179 2.25L12 21.75l-9.75-5.25 4.179-2.25" />
                                    </svg>
                                    <span>Jenis Regulasi</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ route('regulations.index') }}"
                                    class="nav-item {{ request()->routeIs('regulations.*') || request()->routeIs('partitions.*') ? 'is-active' : '' }}">
                                    <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1.6">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                    </svg>
                                    <span>Daftar Regulasi</span>
                                </a>
                            </li>
                        @endif
                        <p
                            class="px-3 mt-7 mb-2 text-[10.5px] font-semibold tracking-[0.18em] uppercase text-white/45">
                            Compliance</p>
                        <li>
                            <a href="{{ route('compliance.monitoring') }}"
                                class="nav-item {{ request()->routeIs('compliance.monitoring') ? 'is-active' : '' }}">
                                <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                                <span>Compliance Monitoring</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('consultations.index') }}"
                                class="nav-item {{ request()->routeIs('consultations.*') ? 'is-active' : '' }}">
                                <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 0 1-.825-.242m9.345-8.334a2.126 2.126 0 0 0-.476-.095 48.64 48.64 0 0 0-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0 0 11.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155" />
                                </svg>
                                <span>Konsultasi Kak Vesta</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('review-documents.index') }}"
                                class="nav-item {{ request()->routeIs('review-documents.*') || request()->routeIs('partitions.*') ? 'is-active' : '' }}">
                                <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                                <span>Documents</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('reviews.index') }}"
                                class="nav-item {{ request()->routeIs('reviews.*') || request()->routeIs('reports.*') ? 'is-active' : '' }}">
                                <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M9 12h6m-6 3h4m1.5 6H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.4 48.4 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15a2.25 2.25 0 0 1 2.15 1.586M8.25 8.25H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z" />
                                </svg>
                                <span>Reviews &amp; Reports</span>
                            </a>
                        </li>
                    </ul>
                @endif
            </nav>

            {{-- Footer --}}
            <div
                class="mt-6 rounded-2xl border border-[#c99a3e]/25 bg-gradient-to-br from-[#c99a3e]/15 to-transparent p-4">
                <div class="flex items-start gap-3">
                    <div class="w-9 h-9 rounded-xl bg-[#c99a3e] flex items-center justify-center shrink-0">
                        <svg class="w-4.5 h-4.5 text-[#071b3a]" viewBox="0 0 24 24" fill="none"
                            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2 4 5v6c0 5 3.5 9 8 11 4.5-2 8-6 8-11V5l-8-3Z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-white">Need Assistance?</p>
                        <p class="text-[11px] text-white/60 leading-relaxed mt-0.5">Reach our compliance desk for any
                            regulatory inquiry.</p>
                    </div>
                </div>
                <a href="{{ route('profile.edit') }}"
                    class="mt-3 inline-flex items-center justify-center gap-2 w-full text-xs font-semibold text-[#071b3a] bg-gradient-to-r from-[#c99a3e] to-[#e6c06a] rounded-xl py-2.5 hover:brightness-110 transition">
                    Contact Compliance
                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14M13 5l7 7-7 7" />
                    </svg>
                </a>
            </div>
        </div>
    </div>
</aside>
