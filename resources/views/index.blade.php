<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>LawCo | Jasa Kebutuhan berbagai sektor</title>
    <meta name="description"
        content="Layanan konsultan hukum investasi dan pasar modal untuk reksa dana, emisi efek, aksi korporasi, keterbukaan informasi, legal due diligence, dan perlindungan investor." />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body x-data="app()" x-cloak>

    {{-- Header --}}
    <header class="fixed top-0 left-0 right-0 z-50 bg-white/92 backdrop-blur-md border-b border-line/80">
        <div class="mx-auto max-w-[1180px] px-5 flex items-center justify-between min-h-[78px]">
            <a class="flex items-center gap-3 text-navy no-underline" href="#top">
                <span
                    class="w-[42px] h-[42px] border-2 border-gold rounded-xl grid place-items-center text-gold bg-[#fff9ed]">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 3v18M5 8l7-5 7 5M3 8h18M5 21h14M7 8v9M17 8v9" />
                    </svg>
                </span>
                <span class="leading-none">
                    <strong
                        class="block text-[28px] leading-none font-bold text-navy tracking-tight">{{ config('app.name') }}</strong>
                    <small class="block text-[11px] text-muted font-medium tracking-[0.03em]">Legal. Strategic.
                        Trusted.</small>
                </span>
            </a>

            <button @click="navOpen = !navOpen" :aria-expanded="navOpen"
                class="hidden max-lg:block bg-transparent border-0 w-[42px] h-[42px] cursor-pointer"
                aria-label="Buka menu">
                <span class="block h-[2px] bg-navy my-[7px] rounded transition-transform duration-200"
                    :class="{ 'translate-y-[9px] rotate-45': navOpen }"></span>
                <span class="block h-[2px] bg-navy my-[7px] rounded transition-opacity duration-200"
                    :class="{ 'opacity-0': navOpen }"></span>
                <span class="block h-[2px] bg-navy my-[7px] rounded transition-transform duration-200"
                    :class="{ '-translate-y-[9px] -rotate-45': navOpen }"></span>
            </button>

            <nav class="flex items-center gap-7 text-sm font-bold max-lg:absolute max-lg:left-5 max-lg:right-5 max-lg:top-[78px] max-lg:bg-white max-lg:border max-lg:border-line max-lg:shadow-luxury max-lg:rounded-[18px] max-lg:p-[18px] max-lg:flex-col max-lg:items-stretch max-lg:transition-all max-lg:duration-200"
                :class="navOpen ? 'max-lg:flex max-lg:opacity-100 max-lg:translate-y-0' :
                    'max-lg:hidden max-lg:opacity-0 max-lg:-translate-y-2'"
                @click.outside="navOpen = false" x-cloak>
                <a href="#layanan" @click="navOpen = false" class="hover:text-gold transition max-lg:py-2">Layanan</a>
                <a href="#paket" @click="navOpen = false" class="hover:text-gold transition max-lg:py-2">Paket</a>
                <a href="#checker" @click="navOpen = false" class="hover:text-gold transition max-lg:py-2">Legal
                    Check</a>
                <a href="#alur" @click="navOpen = false" class="hover:text-gold transition max-lg:py-2">Alur</a>
                <a href="#dokumen" @click="navOpen = false" class="hover:text-gold transition max-lg:py-2">Dokumen</a>
                <a href="#faq" @click="navOpen = false" class="hover:text-gold transition max-lg:py-2">FAQ</a>
                <a href="{{ route('index-dash') }}" @click="navOpen = false"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl font-extrabold text-sm bg-gradient-to-r from-gold to-[#b17c24] text-white shadow-gold hover:shadow-lg hover:from-[#b17c24] hover:to-gold transition-all duration-300">
                    Masuk
                </a>
            </nav>
        </div>
    </header>

    <main>
        {{-- Hero --}}
        <section
            class="relative bg-navy text-white overflow-hidden pt-[116px] pb-[70px] max-sm:pt-[100px] max-sm:pb-[64px]"
            style="background: radial-gradient(circle at 75% 25%,rgba(201,154,62,.30),transparent 26%),linear-gradient(135deg,#061932 0%,#0b2a55 55%,#071b3a 100%);">
            <div class="absolute inset-0 opacity-45 pointer-events-none"
                style="background-image:linear-gradient(rgba(255,255,255,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.05) 1px,transparent 1px);background-size:52px 52px;">
            </div>
            <div class="relative z-1 mx-auto max-w-[1180px] px-5 grid lg:grid-cols-[1.1fr_0.9fr] gap-12 items-center">
                <div>
                    <p class="text-xs font-black tracking-[0.12em] uppercase text-gold mb-3">Layanan InvestaLaw</p>
                    <h1
                        class="text-[clamp(44px,7vw,76px)] font-bold leading-[1.05] tracking-[-0.03em] max-w-[680px] mb-4">
                        Investasi & Pasar Modal</h1>
                    <p class="text-lg leading-relaxed max-w-[650px] text-white/86 mb-7">
                        Nasihat hukum untuk investasi efek, reksa dana, aksi korporasi, keterbukaan informasi,
                        perizinan, dan perlindungan investor.
                    </p>
                    <div class="flex gap-3.5 flex-wrap max-sm:flex-col">
                        <a href="#konsultasi"
                            class="inline-flex items-center justify-center gap-2 px-6 py-4 rounded-xl font-extrabold text-sm bg-gradient-to-r from-gold to-[#b17c24] text-white shadow-gold hover:shadow-lg hover:from-[#b17c24] hover:to-gold transition-all duration-300 max-sm:w-full">
                            Ajukan Konsultasi
                        </a>
                        <a href="{{ route('login') }}"
                            class="inline-flex items-center justify-center gap-2 px-6 py-4 rounded-xl font-extrabold text-sm border-2 border-white/25 text-white hover:bg-white hover:text-navy hover:border-white transition-all duration-300 max-sm:w-full">
                            Masuk Dashboard
                        </a>
                        <button @click="downloadChecklist"
                            class="inline-flex items-center justify-center gap-2 px-6 py-4 rounded-xl font-extrabold text-sm border-2 border-white/25 text-white hover:bg-white hover:text-navy hover:border-white transition-all duration-300 max-sm:w-full">
                            Unduh Checklist
                        </button>
                    </div>
                    <div class="flex gap-[18px] flex-wrap mt-7 font-bold text-white/84">
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-[#22c55e]" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            Rahasia
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-[#22c55e]" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            Strategis
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-[#22c55e]" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            Berbasis Regulasi
                        </span>
                    </div>
                </div>

                <div class="grid gap-[18px] p-[26px] border border-white/16 rounded-[24px] shadow-[0_30px_70px_rgba(0,0,0,.24)]"
                    style="background:linear-gradient(180deg,rgba(255,255,255,.14),rgba(255,255,255,.05));">
                    <div class="bg-white/93 text-navy rounded-[18px] p-6 border border-white/28">
                        <span class="block text-[34px] font-bold text-gold leading-none mb-1">24–72 Jam</span>
                        <span class="text-muted font-bold text-sm">Estimasi initial legal review</span>
                    </div>
                    <div class="bg-white/93 text-navy rounded-[18px] p-6 border border-white/28">
                        <span class="block text-[34px] font-bold text-gold leading-none mb-1">6 Area</span>
                        <span class="text-muted font-bold text-sm">Ruang lingkup pasar modal</span>
                    </div>
                    <div class="bg-white/93 text-navy rounded-[18px] p-6 border border-white/28">
                        <span class="block text-[34px] font-bold text-gold leading-none mb-1">4 Output</span>
                        <span class="text-muted font-bold text-sm">Legal memo, review, checklist, opini</span>
                    </div>
                </div>
            </div>
        </section>

        {{-- Quick Benefit --}}
        <section class="relative z-3 -mt-[42px]">
            <div
                class="mx-auto max-w-[1180px] px-5 grid grid-cols-4 max-md:grid-cols-2 max-sm:grid-cols-1 gap-[18px] bg-white border border-line shadow-luxury p-[18px] rounded-[22px]">
                <article class="p-5 rounded-[18px]" style="background:linear-gradient(180deg,#fff,#fbfcfe);">
                    <span
                        class="w-[54px] h-[54px] rounded-[18px] bg-[#fff8eb] text-gold grid place-items-center mb-3.5">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path
                                d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                    </span>
                    <h3 class="text-lg font-bold mb-2">Kepatuhan Regulasi</h3>
                    <p class="text-muted text-sm">Memastikan rencana investasi, aksi korporasi, dan dokumen pasar modal
                        mengikuti ketentuan yang relevan.</p>
                </article>
                <article class="p-5 rounded-[18px]" style="background:linear-gradient(180deg,#fff,#fbfcfe);">
                    <span
                        class="w-[54px] h-[54px] rounded-[18px] bg-[#fff8eb] text-gold grid place-items-center mb-3.5">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path
                                d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                    </span>
                    <h3 class="text-lg font-bold mb-2">Perlindungan Investor</h3>
                    <p class="text-muted text-sm">Mengutamakan transparansi, mitigasi risiko hukum, dan perlindungan
                        hak investor.</p>
                </article>
                <article class="p-5 rounded-[18px]" style="background:linear-gradient(180deg,#fff,#fbfcfe);">
                    <span
                        class="w-[54px] h-[54px] rounded-[18px] bg-[#fff8eb] text-gold grid place-items-center mb-3.5">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path
                                d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                    </span>
                    <h3 class="text-lg font-bold mb-2">Dokumentasi Transaksi</h3>
                    <p class="text-muted text-sm">Review dan penyusunan dokumen investasi agar lebih jelas, kuat, dan
                        dapat dipertanggungjawabkan.</p>
                </article>
                <article class="p-5 rounded-[18px]" style="background:linear-gradient(180deg,#fff,#fbfcfe);">
                    <span
                        class="w-[54px] h-[54px] rounded-[18px] bg-[#fff8eb] text-gold grid place-items-center mb-3.5">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path
                                d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                        </svg>
                    </span>
                    <h3 class="text-lg font-bold mb-2">Aksi Korporasi</h3>
                    <p class="text-muted text-sm">Pendampingan dari perencanaan, persetujuan internal, keterbukaan
                        informasi, hingga pelaksanaan.</p>
                </article>
            </div>
        </section>

        {{-- Layanan --}}
        <section id="layanan" class="py-[82px]">
            <div class="mx-auto max-w-[1180px] px-5">
                <div class="text-center max-w-[760px] mx-auto mb-[38px]">
                    <p class="text-xs font-black tracking-[0.12em] uppercase text-gold mb-3">Ruang Lingkup</p>
                    <h2 class="text-[clamp(30px,4vw,46px)] font-bold leading-[1.05] tracking-[-0.03em] mb-4">Layanan
                        Hukum Investasi dan Pasar Modal</h2>
                    <p class="text-muted">Pilih area layanan untuk melihat detail manfaat, output, dan dokumen yang
                        biasanya dibutuhkan.</p>
                </div>

                <div class="flex gap-2.5 justify-center flex-wrap mb-[30px]">
                    <template x-for="tab in ['Semua','Reksa Dana','Emisi Efek','Aksi Korporasi','Sengketa']"
                        :key="tab">
                        <button @click="filter = tab === 'Semua' ? 'all' : tab.toLowerCase().replace(/\s+/g,'')"
                            :class="filter === (tab === 'Semua' ? 'all' : tab.toLowerCase().replace(/\s+/g, '')) ?
                                'bg-navy text-white border-navy' :
                                'bg-white text-muted border-line hover:bg-navy hover:text-white hover:border-navy'"
                            class="border rounded-full px-4 py-2.5 font-extrabold text-sm cursor-pointer transition-all duration-200"
                            x-text="tab"></button>
                    </template>
                </div>

                <div class="grid grid-cols-3 max-md:grid-cols-2 max-sm:grid-cols-1 gap-[18px]">
                    <template x-for="(svc, idx) in services" :key="idx">
                        <article x-show="filter === 'all' || svc.category === filter"
                            class="border border-line rounded-[22px] p-[26px] bg-white shadow-[0_10px_30px_rgba(7,27,58,.05)] hover:-translate-y-1 hover:shadow-luxury hover:border-gold/45 transition-all duration-200">
                            <span
                                class="w-[54px] h-[54px] rounded-[18px] bg-[#fff8eb] text-gold grid place-items-center mb-3.5"
                                x-html="svc.icon"></span>
                            <h3 class="text-lg font-bold mb-2" x-text="svc.name"></h3>
                            <p class="text-muted text-sm" x-text="svc.short"></p>
                            <button @click="openService(svc)"
                                class="border-0 bg-transparent text-navy font-black pt-4 pb-0 cursor-pointer hover:text-gold transition text-sm">
                                Selengkapnya →
                            </button>
                        </article>
                    </template>
                </div>
            </div>
        </section>

        {{-- Legal Checker --}}
        <section id="checker" class="py-[82px] bg-soft">
            <div class="mx-auto max-w-[1180px] px-5">
                <div
                    class="grid lg:grid-cols-[0.78fr_1.22fr] gap-7 bg-white border border-line rounded-[24px] shadow-luxury overflow-hidden">

                    <div class="bg-navy text-white p-[42px] max-sm:p-6"
                        style="background:linear-gradient(135deg,#071b3a,#0b2a55);">
                        <p class="text-xs font-black tracking-[0.12em] uppercase text-gold mb-3">Legal Risk Quick Check
                        </p>
                        <h2 class="text-[clamp(30px,4vw,46px)] font-bold leading-[1.05] tracking-[-0.03em] mb-4">Cek
                            Kebutuhan Hukum Anda</h2>
                        <p class="text-white/78 text-sm">Jawab beberapa pertanyaan singkat. Sistem akan memberikan
                            rekomendasi awal layanan dan dokumen yang perlu disiapkan.</p>
                        <ul class="flex gap-3 flex-wrap mt-6 list-none p-0">
                            <li
                                class="bg-white/10 border border-white/15 rounded-full px-3 py-1.5 text-xs font-extrabold inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                Aman & rahasia
                            </li>
                            <li
                                class="bg-white/10 border border-white/15 rounded-full px-3 py-1.5 text-xs font-extrabold inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                Gratis
                            </li>
                            <li
                                class="bg-white/10 border border-white/15 rounded-full px-3 py-1.5 text-xs font-extrabold inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                    stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                2 menit
                            </li>
                        </ul>
                    </div>

                    <div>
                        <form @submit.prevent="runChecker"
                            class="p-[36px] max-sm:p-6 grid grid-cols-2 max-sm:grid-cols-1 gap-[18px]">

                            <div class="grid gap-2">
                                <label class="text-sm font-extrabold text-navy">
                                    Nama Lengkap
                                </label>

                                <input x-model="checker.name" type="text" required placeholder="Nama Anda"
                                    class="w-full rounded-[14px] border border-line bg-white px-[15px] py-3.5 text-ink outline-none focus:border-gold focus:shadow-[0_0_0_4px_rgba(201,154,62,.12)]">
                            </div>

                            <div class="grid gap-2">
                                <label class="text-sm font-extrabold text-navy">
                                    Email
                                </label>

                                <input x-model="checker.email" type="email" required placeholder="nama@email.com"
                                    class="w-full rounded-[14px] border border-line bg-white px-[15px] py-3.5 text-ink outline-none focus:border-gold focus:shadow-[0_0_0_4px_rgba(201,154,62,.12)]">
                            </div>

                            <div class="col-span-2 max-sm:col-span-1 grid gap-2">
                                <label class="text-sm font-extrabold text-navy">
                                    Nomor Telpon
                                </label>

                                <input x-model="checker.phone" type="text" required placeholder="+628392202"
                                    class="w-full rounded-[14px] border border-line bg-white px-[15px] py-3.5 text-ink outline-none focus:border-gold focus:shadow-[0_0_0_4px_rgba(201,154,62,.12)]">
                            </div>

                            <div class="col-span-2 max-sm:col-span-1 grid gap-2">
                                <label class="font-extrabold text-navy text-sm">Jenis Kegiatan</label>
                                <select x-model="checker.activity" required
                                    class="w-full border border-line rounded-[14px] px-[15px] py-3.5 bg-white text-ink outline-none focus:border-gold focus:shadow-[0_0_0_4px_rgba(201,154,62,.12)] appearance-none"
                                    style="background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%23667085' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E\");background-repeat:no-repeat;background-position:right 16px center;">
                                    <option value="">Pilih jenis kegiatan</option>
                                    <option value="reksa_dana">Reksa Dana</option>
                                    <option value="penawaran_umum">Emisi / Penawaran Umum</option>
                                    <option value="aksi_korporasi">Aksi Korporasi</option>
                                    <option value="investasi_akuisisi">Investasi / Akuisisi</option>
                                    <option value="sengketa">Sengketa / Pengaduan</option>
                                </select>
                            </div>
                            <div class="grid gap-2">
                                <label class="font-extrabold text-navy text-sm">Status Perusahaan</label>
                                <select x-model="checker.companyStatus" required
                                    class="w-full border border-line rounded-[14px] px-[15px] py-3.5 bg-white text-ink outline-none focus:border-gold focus:shadow-[0_0_0_4px_rgba(201,154,62,.12)] appearance-none"
                                    style="background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%23667085' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E\");background-repeat:no-repeat;background-position:right 16px center;">
                                    <option value="">Pilih status</option>
                                    <option value="terbuka">Perusahaan Terbuka</option>
                                    <option value="tertutup">Perusahaan Tertutup</option>
                                    <option value="startup">Startup / Venture Capital</option>
                                    <option value="mi">Manajer Investasi</option>
                                    <option value="investor">Investor Individu / Institusi</option>
                                </select>
                            </div>
                            <div class="grid gap-2">
                                <label class="font-extrabold text-navy text-sm">Nilai Transaksi</label>
                                <select x-model="checker.transactionValue" required
                                    class="w-full border border-line rounded-[14px] px-[15px] py-3.5 bg-white text-ink outline-none focus:border-gold focus:shadow-[0_0_0_4px_rgba(201,154,62,.12)] appearance-none"
                                    style="background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%23667085' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E\");background-repeat:no-repeat;background-position:right 16px center;">
                                    <option value="">Pilih rentang nilai</option>
                                    <option value="low">&lt; Rp10 Miliar</option>
                                    <option value="medium">Rp10–100 Miliar</option>
                                    <option value="high">Rp100 Miliar–1 Triliun</option>
                                    <option value="very_high">&gt; Rp1 Triliun</option>
                                    <option value="na">Belum ditentukan</option>
                                </select>
                            </div>
                            <div class="col-span-2 max-sm:col-span-1 grid gap-2">
                                <label class="font-extrabold text-navy text-sm">Target Output</label>
                                <select x-model="checker.targetOutput" required
                                    class="w-full border border-line rounded-[14px] px-[15px] py-3.5 bg-white text-ink outline-none focus:border-gold focus:shadow-[0_0_0_4px_rgba(201,154,62,.12)] appearance-none"
                                    style="background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%23667085' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E\");background-repeat:no-repeat;background-position:right 16px center;">
                                    <option value="">Pilih target</option>
                                    <option value="review">Review Dokumen</option>
                                    <option value="legal_opinion">Legal Opinion</option>
                                    <option value="compliance">Checklist Kepatuhan</option>
                                    <option value="pendampingan">Pendampingan Transaksi</option>
                                </select>
                            </div>
                            <button type="submit"
                                class="col-span-2 max-sm:col-span-1 inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-xl font-extrabold text-sm bg-gradient-to-r from-gold to-[#b17c24] text-white shadow-gold hover:shadow-lg hover:from-[#b17c24] hover:to-gold transition-all duration-300">
                                Kirim Kebutuhan
                            </button>
                        </form>

                        <div x-show="checker.result" x-cloak
                            class="mx-[36px] mb-[36px] max-sm:mx-6 p-[22px] rounded-[18px] bg-[#fff8eb] border border-gold/30">
                            <h3 class="font-bold text-navy mb-2 text-lg">Rekomendasi Awal</h3>
                            <p class="text-muted" x-text="checker.result.text"></p>
                            <div class="flex gap-2 flex-wrap my-3.5">
                                <template x-for="tag in checker.result.tags" :key="tag">
                                    <span
                                        class="bg-white border border-gold/35 rounded-full px-2.5 py-1.5 text-xs font-extrabold text-navy"
                                        x-text="tag"></span>
                                </template>
                            </div>
                            <button @click="copyResult"
                                class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-extrabold text-sm border-2 border-navy/15 text-navy hover:bg-navy hover:text-white hover:border-navy transition-all duration-300">
                                Salin Rekomendasi
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        {{-- Alur --}}
        <section id="alur" class="py-[82px]">
            <div class="mx-auto max-w-[1180px] px-5">
                <div class="text-center max-w-[760px] mx-auto mb-[38px]">
                    <p class="text-xs font-black tracking-[0.12em] uppercase text-gold mb-3">Metode Kerja</p>
                    <h2 class="text-[clamp(30px,4vw,46px)] font-bold leading-[1.05] tracking-[-0.03em]">Alur
                        Pendampingan</h2>
                </div>

                <div class="grid grid-cols-5 max-md:grid-cols-2 max-sm:grid-cols-1 gap-4">
                    <div
                        class="text-center border border-line rounded-[22px] bg-white p-6 shadow-[0_8px_26px_rgba(7,27,58,.05)]">
                        <span
                            class="w-12 h-12 grid place-items-center rounded-full bg-navy text-white font-black mx-auto mb-3.5">1</span>
                        <h3 class="font-bold mb-2">Konsultasi Awal</h3>
                        <p class="text-muted text-sm">Memahami kebutuhan, tujuan transaksi, profil risiko, dan konteks
                            hukum.</p>
                    </div>
                    <div
                        class="text-center border border-line rounded-[22px] bg-white p-6 shadow-[0_8px_26px_rgba(7,27,58,.05)]">
                        <span
                            class="w-12 h-12 grid place-items-center rounded-full bg-navy text-white font-black mx-auto mb-3.5">2</span>
                        <h3 class="font-bold mb-2">Analisis Regulasi</h3>
                        <p class="text-muted text-sm">Memetakan kewajiban, pembatasan, risiko, dan konsekuensi hukum.
                        </p>
                    </div>
                    <div
                        class="text-center border border-line rounded-[22px] bg-white p-6 shadow-[0_8px_26px_rgba(7,27,58,.05)]">
                        <span
                            class="w-12 h-12 grid place-items-center rounded-full bg-navy text-white font-black mx-auto mb-3.5">3</span>
                        <h3 class="font-bold mb-2">Review Dokumen</h3>
                        <p class="text-muted text-sm">Menelaah dokumen, kontrak, prospektus, disclosure, dan bukti
                            pendukung.</p>
                    </div>
                    <div
                        class="text-center border border-line rounded-[22px] bg-white p-6 shadow-[0_8px_26px_rgba(7,27,58,.05)]">
                        <span
                            class="w-12 h-12 grid place-items-center rounded-full bg-navy text-white font-black mx-auto mb-3.5">4</span>
                        <h3 class="font-bold mb-2">Strategi & Opini</h3>
                        <p class="text-muted text-sm">Menyusun legal memo, legal opinion, checklist, atau strategi
                            transaksi.</p>
                    </div>
                    <div
                        class="text-center border border-line rounded-[22px] bg-white p-6 shadow-[0_8px_26px_rgba(7,27,58,.05)]">
                        <span
                            class="w-12 h-12 grid place-items-center rounded-full bg-navy text-white font-black mx-auto mb-3.5">5</span>
                        <h3 class="font-bold mb-2">Implementasi</h3>
                        <p class="text-muted text-sm">Pendampingan pelaksanaan, komunikasi pihak terkait, dan
                            monitoring.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- Dokumen --}}
        <section id="dokumen" class="py-[82px] bg-white">
            <div
                class="mx-auto max-w-[1180px] px-5 grid lg:grid-cols-[0.45fr_0.55fr] gap-7 items-start border border-line rounded-[24px] shadow-luxury p-[30px] max-sm:p-6">
                <div>
                    <p class="text-xs font-black tracking-[0.12em] uppercase text-gold mb-3">Dokumen</p>
                    <h2 class="text-[clamp(30px,4vw,46px)] font-bold leading-[1.05] tracking-[-0.03em] mb-4">Dokumen
                        yang Dapat Kami Review</h2>
                    <p class="text-muted mb-4">Daftar ini dapat disesuaikan dengan jenis transaksi dan kebutuhan klien.
                    </p>
                    <button @click="selectAllDocs"
                        class="inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-xl font-extrabold text-sm border-2 border-navy/15 text-navy hover:bg-navy hover:text-white hover:border-navy transition-all duration-300">
                        Tampilkan Semua
                    </button>
                </div>

                <div class="grid grid-cols-2 max-sm:grid-cols-1 gap-3">
                    <template x-for="doc in docList" :key="doc">
                        <button @click="toggleDoc(doc)"
                            :class="selectedDocs.includes(doc) ? 'border-gold bg-[#fff8eb]' :
                                'border-line bg-white hover:border-gold hover:bg-[#fffaf0]'"
                            class="border rounded-[16px] p-[18px] text-left font-extrabold text-navy cursor-pointer transition-all duration-200"
                            x-text="doc">
                        </button>
                    </template>
                </div>
            </div>
        </section>

        {{-- Stats --}}
        <section class="py-[42px]" style="background:linear-gradient(135deg,#071b3a,#0b2a55);">
            <div class="mx-auto max-w-[1180px] px-5 grid grid-cols-4 max-sm:grid-cols-2 gap-[18px] text-center">
                <div><strong class="block text-[42px] font-bold text-gold leading-none mb-1">100+</strong><span
                        class="font-extrabold text-white/80">Klien</span></div>
                <div><strong class="block text-[42px] font-bold text-gold leading-none mb-1">200+</strong><span
                        class="font-extrabold text-white/80">Proyek</span></div>
                <div><strong class="block text-[42px] font-bold text-gold leading-none mb-1">15+</strong><span
                        class="font-extrabold text-white/80">Sektor Industri</span></div>
                <div><strong class="block text-[42px] font-bold text-gold leading-none mb-1">10+</strong><span
                        class="font-extrabold text-white/80">Tahun Pengalaman</span></div>
            </div>
        </section>

        {{-- Paket & Harga --}}
        {{-- <section id="paket" class="py-[82px] bg-soft">
            <div class="mx-auto max-w-[1180px] px-5">
                <div class="text-center max-w-[760px] mx-auto mb-[38px]">
                    <p class="text-xs font-black tracking-[0.12em] uppercase text-gold mb-3">Paket & Harga</p>
                    <h2 class="text-[clamp(30px,4vw,46px)] font-bold leading-[1.05] tracking-[-0.03em] mb-4">Pilih
                        Paket yang Sesuai</h2>

                    <p class="text-muted">Skema pendampingan tunggal untuk kebutuhan kepatuhan dan transaksi pasar
                        modal.</p>
                </div>

                <div class="grid grid-cols-3 max-lg:grid-cols-1 gap-5">
                    @forelse($packages as $package)
                        <article
                            class="relative {{ $package->is_popular ? 'border-2 border-gold bg-navy text-white shadow-[0_24px_60px_rgba(201,154,62,.22)] overflow-hidden' : 'border border-line bg-white shadow-[0_10px_30px_rgba(7,27,58,.05)]' }} rounded-[24px] p-[30px] flex flex-col">
                            @if ($package->is_popular)
                                <span
                                    class="absolute top-0 right-0 bg-gradient-to-r from-gold to-[#b17c24] text-white text-[11px] font-black tracking-wider uppercase px-4 py-1.5 rounded-bl-[18px]">Paling
                                    Populer</span>
                            @endif
                            <h3 class="text-lg font-bold {{ $package->is_popular ? '' : 'text-navy' }}">{{ $package->name }}</h3>
                            <p class="{{ $package->is_popular ? 'text-white/70' : 'text-muted' }} text-sm mt-1">{{ $package->tagline }}</p>
                            <p class="mt-6 flex items-baseline gap-1.5">
                                <span class="text-[40px] font-bold leading-none {{ $package->is_popular ? '' : 'text-navy' }}">
                                    @if (strtolower($package->price) === 'custom')
                                        {{ $package->price }}
                                    @else
                                        Rp<span class="text-gold">{{ $package->price }}</span>
                                    @endif
                                </span>
                                @if ($package->price_period)
                                    <span class="{{ $package->is_popular ? 'text-white/60' : 'text-muted' }} text-sm font-bold">{{ $package->price_period }}</span>
                                @endif
                            </p>
                            <ul class="mt-6 space-y-2.5 text-sm {{ $package->is_popular ? 'text-white/80' : 'text-muted' }} flex-1">
                                @foreach ($package->benefits ?? [] as $benefit)
                                    <li class="flex items-start gap-2.5">
                                        <svg class="w-4.5 h-4.5 text-gold shrink-0 mt-0.5" viewBox="0 0 24 24"
                                            fill="none" stroke="currentColor" stroke-width="2.5"
                                            stroke-linecap="round" stroke-linejoin="round">
                                            <path d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                        {{ $benefit }}
                                    </li>
                                @endforeach
                            </ul>
                            <a href="{{ strtolower($package->price) === 'custom' ? '#konsultasi' : route('register') }}"
                                class="mt-7 inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-xl font-extrabold text-sm {{ $package->is_popular ? 'bg-gradient-to-r from-gold to-[#b17c24] text-white shadow-gold hover:shadow-lg hover:from-[#b17c24] hover:to-gold transition-all duration-300' : 'border-2 border-navy/15 text-navy hover:bg-navy hover:text-white hover:border-navy transition-all duration-300' }}">{{ strtolower($package->price) === 'custom' ? 'Hubungi Kami' : 'Pilih Paket' }}</a>
                        </article>
                    @empty
                        <article
                            class="relative border border-line rounded-[24px] bg-white p-[30px] shadow-[0_10px_30px_rgba(7,27,58,.05)] flex flex-col">
                            <h3 class="text-lg font-bold text-navy">Dasar</h3>
                            <p class="text-muted text-sm mt-1">Untuk individu & pemantauan kepatuhan ringan.</p>
                            <p class="mt-6 flex items-baseline gap-1.5">
                                <span class="text-[40px] font-bold text-navy leading-none">Rp<span
                                        class="text-gold">5</span>jt</span>
                                <span class="text-muted text-sm font-bold">/bulan</span>
                            </p>
                            <ul class="mt-6 space-y-2.5 text-sm text-muted flex-1">
                                <li class="flex items-start gap-2.5"><svg class="w-4.5 h-4.5 text-gold shrink-0 mt-0.5"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>Legal check regulasi dasar</li>
                                <li class="flex items-start gap-2.5"><svg class="w-4.5 h-4.5 text-gold shrink-0 mt-0.5"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>Review 1 dokumen per bulan</li>
                                <li class="flex items-start gap-2.5"><svg class="w-4.5 h-4.5 text-gold shrink-0 mt-0.5"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>Konsultasi email</li>
                            </ul>
                            <a href="{{ route('register') }}"
                                class="mt-7 inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-xl font-extrabold text-sm border-2 border-navy/15 text-navy hover:bg-navy hover:text-white hover:border-navy transition-all duration-300">Pilih
                                Paket</a>
                        </article>
                        <article
                            class="relative border-2 border-gold rounded-[24px] bg-navy text-white p-[30px] shadow-[0_24px_60px_rgba(201,154,62,.22)] flex flex-col overflow-hidden">
                            <span
                                class="absolute top-0 right-0 bg-gradient-to-r from-gold to-[#b17c24] text-white text-[11px] font-black tracking-wider uppercase px-4 py-1.5 rounded-bl-[18px]">Paling
                                Populer</span>
                            <h3 class="text-lg font-bold">Bisnis</h3>
                            <p class="text-white/70 text-sm mt-1">Untuk perusahaan & manajer investasi aktif.</p>
                            <p class="mt-6 flex items-baseline gap-1.5">
                                <span class="text-[40px] font-bold leading-none">Rp<span
                                        class="text-gold">12,5</span>jt</span>
                                <span class="text-white/60 text-sm font-bold">/bulan</span>
                            </p>
                            <ul class="mt-6 space-y-2.5 text-sm text-white/80 flex-1">
                                <li class="flex items-start gap-2.5"><svg class="w-4.5 h-4.5 text-gold shrink-0 mt-0.5"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>Review hingga 5 dokumen/bulan</li>
                                <li class="flex items-start gap-2.5"><svg class="w-4.5 h-4.5 text-gold shrink-0 mt-0.5"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>Konsultasi telepon & email</li>
                                <li class="flex items-start gap-2.5"><svg class="w-4.5 h-4.5 text-gold shrink-0 mt-0.5"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>Pendampingan aksi korporasi</li>
                            </ul>
                            <a href="{{ route('register') }}"
                                class="mt-7 inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-xl font-extrabold text-sm bg-gradient-to-r from-gold to-[#b17c24] text-white shadow-gold hover:shadow-lg hover:from-[#b17c24] hover:to-gold transition-all duration-300">Pilih
                                Paket</a>
                        </article>
                        <article
                            class="relative border border-line rounded-[24px] bg-white p-[30px] shadow-[0_10px_30px_rgba(7,27,58,.05)] flex flex-col">
                            <h3 class="text-lg font-bold text-navy">Enterprise</h3>
                            <p class="text-muted text-sm mt-1">Untuk grup usaha & transaksi kompleks.</p>
                            <p class="mt-6 flex items-baseline gap-1.5">
                                <span class="text-[40px] font-bold text-navy leading-none">Custom</span>
                            </p>
                            <ul class="mt-6 space-y-2.5 text-sm text-muted flex-1">
                                <li class="flex items-start gap-2.5"><svg class="w-4.5 h-4.5 text-gold shrink-0 mt-0.5"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>Review tanpa batas</li>
                                <li class="flex items-start gap-2.5"><svg class="w-4.5 h-4.5 text-gold shrink-0 mt-0.5"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>Dedicated legal counsel</li>
                                <li class="flex items-start gap-2.5"><svg class="w-4.5 h-4.5 text-gold shrink-0 mt-0.5"
                                        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                                        stroke-linecap="round" stroke-linejoin="round">
                                        <path d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>Due diligence & transaksi besar</li>
                            </ul>
                            <a href="#konsultasi"
                                class="mt-7 inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-xl font-extrabold text-sm border-2 border-navy/15 text-navy hover:bg-navy hover:text-white hover:border-navy transition-all duration-300">Hubungi
                                Kami</a>
                        </article>
                    @endforelse
                </div>
            </div>
        </section> --}}

        {{-- FAQ --}}
        <section id="faq" class="py-[82px] bg-soft">
            <div class="mx-auto max-w-[1180px] px-5">
                <div class="text-center max-w-[760px] mx-auto mb-[38px]">
                    <p class="text-xs font-black tracking-[0.12em] uppercase text-gold mb-3">FAQ</p>
                    <h2 class="text-[clamp(30px,4vw,46px)] font-bold leading-[1.05] tracking-[-0.03em]">Pertanyaan Umum
                    </h2>
                </div>

                <div class="max-w-[900px] mx-auto grid gap-2.5">
                    <template x-for="(faq, idx) in faqs" :key="idx">
                        <div class="border border-line rounded-[14px] bg-white overflow-hidden">
                            <button @click="faq.open = !faq.open"
                                class="w-full flex items-center justify-between p-[18px] text-left font-extrabold text-navy cursor-pointer border-0 bg-transparent">
                                <span x-text="faq.q"></span>
                                <svg class="w-5 h-5 text-navy shrink-0 ml-4 transition-transform duration-200"
                                    :class="{ 'rotate-45': faq.open }" viewBox="0 0 24 24" fill="none"
                                    stroke="currentColor" stroke-width="2.2" stroke-linecap="round"
                                    stroke-linejoin="round">
                                    <path d="M12 5v14M5 12h14" />
                                </svg>
                            </button>
                            <div x-show="faq.open" x-cloak x-collapse.duration.200ms>
                                <div class="px-[18px] pb-[18px] text-muted text-sm" x-text="faq.a"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </section>

        {{-- Konsultasi --}}
        <section id="konsultasi" class="py-[82px] bg-white">
            <div class="mx-auto max-w-[1180px] px-5 grid lg:grid-cols-[0.78fr_1.22fr] gap-[34px] items-start">
                <div>
                    <p class="text-xs font-black tracking-[0.12em] uppercase text-gold mb-3">Ajukan Konsultasi</p>
                    <h2 class="text-[clamp(30px,4vw,46px)] font-bold leading-[1.05] tracking-[-0.03em] mb-4">Siap
                        Mengamankan Investasi Anda?</h2>
                    <p class="text-muted">Isi formulir singkat berikut. Tim LawCo akan menghubungi Anda untuk
                        mengatur sesi konsultasi.</p>
                    <div class="border border-line rounded-[18px] p-5 bg-soft mt-6 space-y-1.5 text-sm">
                        <p class="font-bold text-navy">Email: <span
                                class="font-normal text-muted">info@investalaw.id</span></p>
                        <p class="font-bold text-navy">Telepon: <span
                                class="font-normal text-muted">0852-1786-6428</span></p>
                        <p class="font-bold text-navy">WhatsApp: <span
                                class="font-normal text-muted">0898-7498-579</span></p>
                    </div>
                </div>

                <form @submit.prevent="submitContact"
                    class="border border-line rounded-[24px] shadow-luxury p-[30px] max-sm:p-6 space-y-4">
                    <div class="grid grid-cols-2 max-sm:grid-cols-1 gap-4">
                        <div class="grid gap-2">
                            <label class="font-extrabold text-navy text-sm">Nama Lengkap</label>
                            <input x-model="contact.name" type="text" required placeholder="Nama Anda"
                                class="w-full border border-line rounded-[14px] px-[15px] py-3.5 bg-white text-ink outline-none focus:border-gold focus:shadow-[0_0_0_4px_rgba(201,154,62,.12)]">
                        </div>
                        <div class="grid gap-2">
                            <label class="font-extrabold text-navy text-sm">Email</label>
                            <input x-model="contact.email" type="email" required placeholder="nama@email.com"
                                class="w-full border border-line rounded-[14px] px-[15px] py-3.5 bg-white text-ink outline-none focus:border-gold focus:shadow-[0_0_0_4px_rgba(201,154,62,.12)]">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 max-sm:grid-cols-1 gap-4">
                        <div class="grid gap-2">
                            <label class="font-extrabold text-navy text-sm">Nomor HP / WhatsApp</label>
                            <input x-model="contact.phone" type="tel" required placeholder="+62..."
                                class="w-full border border-line rounded-[14px] px-[15px] py-3.5 bg-white text-ink outline-none focus:border-gold focus:shadow-[0_0_0_4px_rgba(201,154,62,.12)]">
                        </div>
                        <div class="grid gap-2">
                            <label class="font-extrabold text-navy text-sm">Topik Konsultasi</label>
                            <select x-model="contact.topic" required
                                class="w-full border border-line rounded-[14px] px-[15px] py-3.5 bg-white text-ink outline-none focus:border-gold focus:shadow-[0_0_0_4px_rgba(201,154,62,.12)] appearance-none"
                                style="background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%23667085' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E\");background-repeat:no-repeat;background-position:right 16px center;">
                                <option value="">Pilih topik</option>
                                <option>Reksa Dana</option>
                                <option>Emisi / Penawaran Umum</option>
                                <option>Aksi Korporasi</option>
                                <option>Due Diligence Investasi</option>
                                <option>Sengketa Pasar Modal</option>
                                <option>Lainnya</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <label class="font-extrabold text-navy text-sm">Ringkasan Kebutuhan</label>
                        <textarea x-model="contact.message" rows="5" required placeholder="Ceritakan kebutuhan hukum Anda..."
                            class="w-full border border-line rounded-[14px] px-[15px] py-3.5 bg-white text-ink outline-none focus:border-gold focus:shadow-[0_0_0_4px_rgba(201,154,62,.12)] resize-y"></textarea>
                    </div>
                    <label class="flex gap-2.5 items-start text-sm text-muted cursor-pointer">
                        <input x-model="contact.agree" type="checkbox" required class="mt-0.5 w-4 h-4 accent-gold">
                        <span>Saya menyetujui pemrosesan data untuk keperluan tindak lanjut konsultasi.</span>
                    </label>
                    <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-xl font-extrabold text-sm bg-gradient-to-r from-gold to-[#b17c24] text-white shadow-gold hover:shadow-lg hover:from-[#b17c24] hover:to-gold transition-all duration-300">
                        Kirim Permintaan Konsultasi
                    </button>
                    <p x-show="contact.note" x-cloak class="text-gold font-extrabold text-sm mt-3"
                        x-text="contact.note"></p>
                </form>
            </div>
        </section>
    </main>

    {{-- Floating Actions --}}
    <aside class="fixed right-[18px] top-[35%] z-30 grid gap-2 p-2 rounded-[18px] bg-navy shadow-luxury max-lg:hidden">
        <a href="#konsultasi" title="Konsultasi"
            class="w-11 h-11 grid place-items-center text-white rounded-xl hover:bg-white/10 transition">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round">
                <path
                    d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
            </svg>
        </a>
        <a href="tel:+622150101934" title="Telepon"
            class="w-11 h-11 grid place-items-center text-white rounded-xl hover:bg-white/10 transition">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round">
                <path
                    d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
            </svg>
        </a>
        <a href="mailto:info@investalaw.id" title="Email"
            class="w-11 h-11 grid place-items-center text-white rounded-xl hover:bg-white/10 transition">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                stroke-linecap="round" stroke-linejoin="round">
                <path
                    d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
            </svg>
        </a>
    </aside>

    {{-- WhatsApp Float --}}
    <a class="fixed right-[22px] bottom-[22px] z-30 w-[58px] h-[58px] rounded-full grid place-items-center bg-[#22c55e] text-white shadow-[0_18px_34px_rgba(34,197,94,.35)] hover:scale-105 transition-transform"
        href="https://wa.me/628987498579?text=Halo%20InvestaLawCo,%20saya%20ingin%20konsultasi%20Investasi%20dan%20Pasar%20Modal"
        target="_blank" rel="noopener" aria-label="WhatsApp InvestaLaw">
        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="currentColor">
            <path
                d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
        </svg>
    </a>

    {{-- Footer --}}
    <footer class="pt-[52px] pb-[22px] text-white" style="background:#061932;">
        <div
            class="mx-auto max-w-[1180px] px-5 grid grid-cols-[1.4fr_1fr_1fr_1fr] max-md:grid-cols-2 max-sm:grid-cols-1 gap-[30px]">
            <div>
                <a class="flex items-center gap-3 text-white no-underline mb-3.5" href="#top">
                    <span
                        class="w-[42px] h-[42px] border-2 border-gold rounded-xl grid place-items-center text-gold bg-[#fff9ed]">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 3v18M5 8l7-5 7 5M3 8h18M5 21h14M7 8v9M17 8v9" />
                        </svg>
                    </span>
                    <span class="leading-none">
                        <strong class="block text-[28px] leading-none font-bold tracking-tight">InvestaLaw</strong>
                        <small class="block text-[11px] text-white/60 font-medium tracking-[0.03em]">Legal. Strategic.
                            Trusted.</small>
                    </span>
                </a>
                <p class="text-white/72 text-sm">Konsultan hukum investasi dan pasar modal untuk bisnis, investor, dan
                    institusi.</p>
            </div>
            <div>
                <h4 class="text-white font-bold mb-3">Layanan</h4>
                <a href="#layanan" class="block text-white/72 text-sm hover:text-white transition my-1.5">Investasi &
                    Pasar Modal</a>
                <a href="#layanan" class="block text-white/72 text-sm hover:text-white transition my-1.5">Korporasi &
                    Komersial</a>
                <a href="#layanan" class="block text-white/72 text-sm hover:text-white transition my-1.5">M&A dan Due
                    Diligence</a>
                <a href="#layanan" class="block text-white/72 text-sm hover:text-white transition my-1.5">Litigasi &
                    Sengketa</a>
            </div>
            <div>
                <h4 class="text-white font-bold mb-3">Tautan</h4>
                <a href="#checker" class="block text-white/72 text-sm hover:text-white transition my-1.5">Legal
                    Check</a>
                <a href="#alur" class="block text-white/72 text-sm hover:text-white transition my-1.5">Alur</a>
                <a href="#dokumen" class="block text-white/72 text-sm hover:text-white transition my-1.5">Dokumen</a>
                <a href="#faq" class="block text-white/72 text-sm hover:text-white transition my-1.5">FAQ</a>
            </div>
            <div>
                <h4 class="text-white font-bold mb-3">Hubungi Kami</h4>
                <p class="text-white/72 text-sm my-1.5">Cibubur Country, komplek RFP I No.8, Cikeas Udik, Kec. Gn.
                    Putri, Kabupaten Bogor, Jawa Barat 16966, Indonesia</p>
                <p class="text-white/72 text-sm my-1.5">info@investalaw.id</p>
                <p class="text-white/72 text-sm my-1.5">0898-7498-579</p>
            </div>
        </div>
        <div class="mx-auto max-w-[1180px] px-5 mt-[30px] pt-[18px] border-t border-white/12 text-white/60 text-sm">
            &copy; {{ date('Y') }} InvestaLawCo. All rights reserved.
        </div>
    </footer>

    {{-- Modal --}}
    <div x-show="modalOpen" x-cloak @keydown.escape.window="modalOpen = false" class="fixed inset-0 z-[100]"
        role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-[rgba(2,12,27,.66)]" @click="modalOpen = false"></div>
        <div
            class="relative bg-white rounded-[24px] shadow-[0_30px_80px_rgba(0,0,0,.30)] w-[min(620px,calc(100%-32px))] my-[8vh] mx-auto p-[34px]">
            <button @click="modalOpen = false"
                class="absolute right-[18px] top-[18px] border-0 bg-[#f2f4f7] rounded-full w-[38px] h-[38px] grid place-items-center cursor-pointer text-navy hover:bg-[#e7eaf0] transition">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
            <p class="text-xs font-black tracking-[0.12em] uppercase text-gold mb-3">Detail Layanan</p>
            <h2 class="text-[clamp(24px,3vw,36px)] font-bold leading-[1.05] tracking-[-0.03em] mb-2.5"
                x-text="modalService?.name"></h2>
            <p class="text-muted" x-text="modalService?.desc"></p>
            <h3 class="font-extrabold text-navy mt-5 mb-2">Output yang Dapat Diberikan</h3>
            <ul class="text-muted space-y-1.5 list-disc pl-5">
                <template x-for="o in modalService?.outputs" :key="o">
                    <li x-text="o"></li>
                </template>
            </ul>
            <a href="#konsultasi" @click="modalOpen = false"
                class="mt-6 inline-flex items-center justify-center gap-2 px-5 py-3.5 rounded-xl font-extrabold text-sm bg-gradient-to-r from-gold to-[#b17c24] text-white shadow-gold hover:shadow-lg hover:from-[#b17c24] hover:to-gold transition-all duration-300">
                Ajukan Konsultasi
            </a>
        </div>
    </div>

    {{-- Toast --}}
    <div x-show="toast.show" x-cloak x-transition.duration.200
        class="fixed left-1/2 bottom-[26px] z-[120] -translate-x-1/2 bg-navy text-white rounded-[14px] px-[18px] py-[13px] shadow-luxury text-sm font-semibold whitespace-nowrap"
        x-text="toast.msg">
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('app', () => ({
                navOpen: false,
                filter: 'all',
                modalOpen: false,
                modalService: null,

                services: [{
                        category: 'reksadana',
                        name: 'Nasihat Hukum Reksa Dana',
                        short: 'Pendampingan pendirian, perubahan, pembubaran, kontrak investasi kolektif, dan koordinasi dengan pihak terkait.',
                        desc: 'Pendampingan hukum untuk struktur produk reksa dana, kontrak investasi kolektif, peran Manajer Investasi dan Bank Kustodian, perubahan produk, serta aspek perlindungan pemegang unit.',
                        outputs: ['Checklist kepatuhan reksa dana', 'Review KIK dan prospektus',
                            'Legal memo risiko produk',
                            'Daftar dokumen untuk koordinasi pihak terkait'
                        ],
                        icon: '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"/></svg>'
                    },
                    {
                        category: 'emisi',
                        name: 'Pendampingan Emisi & Penawaran Umum',
                        short: 'Struktur transaksi, persiapan dokumen, legal due diligence, dan dukungan proses penawaran umum.',
                        desc: 'Pendampingan hukum dalam proses emisi efek, termasuk struktur transaksi, penyiapan dokumen, koordinasi legal due diligence, dan penyelarasan keterbukaan informasi.',
                        outputs: ['Legal due diligence report', 'Review prospektus',
                            'Action plan kepatuhan', 'Daftar isu hukum material'
                        ],
                        icon: '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Z"/></svg>'
                    },
                    {
                        category: 'emisi',
                        name: 'Review Prospektus & Keterbukaan Informasi',
                        short: 'Review prospektus, ringkasan informasi, laporan berkala, dan dokumen disclosure lain.',
                        desc: 'Review narasi, fakta material, risiko usaha, penggunaan dana, struktur transaksi, dan kewajiban keterbukaan informasi agar lebih jelas dan konsisten.',
                        outputs: ['Catatan review prospektus', 'Matriks disclosure',
                            'Daftar gap dokumen', 'Rekomendasi revisi klausul'
                        ],
                        icon: '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>'
                    },
                    {
                        category: 'korporasi',
                        name: 'Aksi Korporasi & RUPS',
                        short: 'Pendampingan rights issue, private placement, merger, akuisisi, RUPS, dan pelaporan korporasi.',
                        desc: 'Pendampingan hukum untuk RUPS, perubahan anggaran dasar, rights issue, private placement, merger, akuisisi, divestasi, dan aksi korporasi lain.',
                        outputs: ['Timeline aksi korporasi', 'Checklist dokumen RUPS',
                            'Review pengumuman/keterbukaan informasi',
                            'Legal memo struktur aksi korporasi'
                        ],
                        icon: '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.087 4.113"/></svg>'
                    },
                    {
                        category: 'korporasi',
                        name: 'Due Diligence Investasi',
                        short: 'Legal due diligence untuk investasi, akuisisi, pembiayaan, joint venture, dan transaksi pasar modal.',
                        desc: 'Legal due diligence untuk investor, perusahaan target, manajer investasi, startup, venture capital, joint venture, dan transaksi strategis.',
                        outputs: ['Legal due diligence report', 'Risk register', 'Red flag summary',
                            'Rekomendasi mitigasi dan kondisi pendahuluan'
                        ],
                        icon: '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>'
                    },
                    {
                        category: 'sengketa',
                        name: 'Sengketa Pasar Modal',
                        short: 'Analisis, strategi, dan pendampingan sengketa investor, emiten, manajer investasi, atau pihak terkait.',
                        desc: 'Analisis awal sengketa pasar modal, strategi penyelesaian, pengumpulan bukti, dan pendampingan komunikasi dengan pihak terkait.',
                        outputs: ['Legal position paper', 'Kronologi dan matriks bukti',
                            'Strategi penyelesaian', 'Draft surat atau tanggapan awal'
                        ],
                        icon: '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18M5 8l7-5 7 5M3 8h18M5 21h14M7 8v9M17 8v9"/></svg>'
                    },
                ],

                faqs: [{
                        q: 'Apa saja yang termasuk layanan Investasi & Pasar Modal?',
                        a: 'Layanan meliputi review dokumen investasi, nasihat hukum reksa dana, pendampingan aksi korporasi, penawaran umum, due diligence, keterbukaan informasi, dan sengketa pasar modal.',
                        open: false
                    },
                    {
                        q: 'Berapa lama proses review dokumen?',
                        a: 'Estimasi awal 24–72 jam kerja untuk review awal, bergantung pada kompleksitas dokumen, jumlah pihak, dan urgensi transaksi.',
                        open: false
                    },
                    {
                        q: 'Apakah konsultasi dapat dilakukan online?',
                        a: 'Ya. Konsultasi dapat dilakukan melalui video meeting, telepon, atau komunikasi tertulis sesuai kebutuhan klien.',
                        open: false
                    },
                    {
                        q: 'Apakah data dan dokumen klien dijaga kerahasiaannya?',
                        a: 'Ya. Platform dirancang dengan prinsip kerahasiaan, pembatasan akses, dan pengelolaan dokumen secara aman.',
                        open: false
                    },
                ],

                docList: ['Prospektus', 'KIK Reksa Dana', 'Perjanjian Investasi', 'Term Sheet',
                    'Perjanjian Pemegang Saham', 'Keterbukaan Informasi', 'Legal Opinion',
                    'Risalah RUPS'
                ],

                checker: {
                    name: '',
                    email: '',
                    phone: '',
                    activity: '',
                    companyStatus: '',
                    transactionValue: '',
                    targetOutput: '',
                    result: null,
                },

                contact: {
                    name: '',
                    email: '',
                    phone: '',
                    topic: '',
                    message: '',
                    agree: false,
                    note: '',
                },

                selectedDocs: [],

                toast: {
                    show: false,
                    msg: ''
                },

                openService(svc) {
                    this.modalService = svc;
                    this.modalOpen = true;
                },

                async runChecker() {
                    const {
                        name,
                        email,
                        phone,
                        activity,
                        companyStatus,
                        transactionValue,
                        targetOutput
                    } = this.checker;

                    try {
                        const res = await fetch('{{ route('legal-necessities.store') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector(
                                    'meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({
                                name,
                                email,
                                phone,
                                legal_activities: activity,
                                status_company: companyStatus,
                                value_trx: transactionValue,
                                target_output: targetOutput,
                            }),
                        });

                        if (!res.ok) throw new Error('Gagal menyimpan data');
                    } catch (e) {
                        this.showToast('Gagal mengirim kebutuhan. Coba lagi.');
                        return;
                    }

                    let score = 0;
                    if (['penawaran_umum', 'aksi_korporasi', 'investasi_akuisisi'].includes(
                            activity))
                        score += 2;
                    if (['terbuka', 'mi'].includes(companyStatus)) score += 2;
                    if (['high', 'very_high'].includes(transactionValue)) score += 2;
                    if (['legal_opinion', 'pendampingan'].includes(targetOutput)) score += 1;

                    let level = 'Standar';
                    let recommendation =
                        'Anda dapat memulai dengan review dokumen dan checklist kepatuhan awal.';
                    if (score >= 5) {
                        level = 'Prioritas Tinggi';
                        recommendation =
                            'Disarankan melakukan legal due diligence, legal opinion, dan pendampingan transaksi secara penuh karena terdapat indikasi kompleksitas dan risiko hukum tinggi.';
                    } else if (score >= 3) {
                        level = 'Menengah';
                        recommendation =
                            'Disarankan melakukan legal review terstruktur, pemetaan kewajiban regulasi, dan penyusunan legal memo sebelum transaksi dilanjutkan.';
                    }

                    this.checker.result = {
                        text: recommendation,
                        tags: [
                            `Risiko: ${level}`,
                            activity.replace(/_/g, ' '),
                            companyStatus.replace(/_/g, ' '),
                            targetOutput.replace(/_/g, ' '),
                        ]
                    };
                    this.showToast('Rekomendasi awal berhasil dibuat.');
                },

                copyResult() {
                    if (!this.checker.result) return;
                    const content =
                        `${this.checker.result.text}\n${this.checker.result.tags.join(' | ')}`;
                    navigator.clipboard.writeText(content).then(() => {
                        this.showToast('Rekomendasi disalin.');
                    }).catch(() => {
                        this.showToast('Browser belum mengizinkan copy otomatis.');
                    });
                },

                toggleDoc(doc) {
                    const idx = this.selectedDocs.indexOf(doc);
                    if (idx >= 0) {
                        this.selectedDocs.splice(idx, 1);
                    } else {
                        this.selectedDocs.push(doc);
                    }
                    this.showToast(this.selectedDocs.length ?
                        `${this.selectedDocs.length} dokumen dipilih.` :
                        'Tidak ada dokumen dipilih.');
                },

                selectAllDocs() {
                    this.selectedDocs = [...this.docList];
                    this.showToast('Semua dokumen ditampilkan sebagai pilihan review.');
                },

                downloadChecklist() {
                    const checklist = [
                        'CHECKLIST AWAL INVESTASI & PASAR MODAL - INVESTALAW',
                        '',
                        '1. Identitas pihak dan kewenangan penandatangan',
                        '2. Tujuan transaksi dan struktur investasi',
                        '3. Status perusahaan, pemegang saham, dan organ perseroan',
                        '4. Dokumen perizinan dan kepatuhan sektor terkait',
                        '5. Dokumen keterbukaan informasi / prospektus bila relevan',
                        '6. Kontrak utama, term sheet, KIK, atau perjanjian investasi',
                        '7. Riwayat sengketa, jaminan, pembatasan, atau kewajiban material',
                        '8. Rencana aksi korporasi, RUPS, atau persetujuan internal',
                        '9. Risiko perlindungan investor dan potensi benturan kepentingan',
                        '10. Target output: legal memo, legal opinion, checklist, atau pendampingan',
                        '',
                        'Catatan: Checklist ini bersifat awal dan perlu disesuaikan dengan transaksi.'
                    ].join('\n');

                    const blob = new Blob([checklist], {
                        type: 'text/plain;charset=utf-8'
                    });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = 'checklist-awal-investasi-pasar-modal-investalaw.txt';
                    a.click();
                    URL.revokeObjectURL(url);
                    this.showToast('Checklist diunduh.');
                },

                async submitContact() {
                    try {
                        const res = await fetch('{{ route('legal-necessities.store') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector(
                                    'meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({
                                name: this.contact.name,
                                email: this.contact.email,
                                phone: this.contact.phone,
                                legal_activities: this.contact.topic,
                                message: this.contact.message,
                            }),
                        });

                        if (!res.ok) throw new Error('Gagal menyimpan data');
                    } catch (e) {
                        this.showToast('Gagal mengirim konsultasi. Coba lagi.');
                        return;
                    }

                    this.contact.note = '';
                    this.contact = {
                        name: '',
                        email: '',
                        phone: '',
                        topic: '',
                        message: '',
                        agree: false,
                        note: ''
                    };
                    this.showToast('Permintaan konsultasi berhasil disimpan.');
                },

                showToast(msg) {
                    this.toast.msg = msg;
                    this.toast.show = true;
                    clearTimeout(this._toastTimer);
                    this._toastTimer = setTimeout(() => {
                        this.toast.show = false;
                    }, 2600);
                },
            }));
        });
    </script>
</body>

</html>
