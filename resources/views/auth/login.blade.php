@extends('layouts.guest')

@section('title', 'Sign In')

@section('content')
    <div class="min-h-screen grid lg:grid-cols-2">

        {{-- Left: Brand showcase --}}
        <div class="relative hidden lg:flex flex-col justify-between p-12 bg-navy-gradient text-white overflow-hidden">
            {{-- Decorative orbs --}}
            <div
                class="pointer-events-none absolute -top-32 -left-32 w-[28rem] h-[28rem] rounded-full bg-[#c99a3e]/15 blur-3xl">
            </div>
            <div
                class="pointer-events-none absolute -bottom-32 -right-32 w-[32rem] h-[32rem] rounded-full bg-[#0b2a55]/60 blur-3xl">
            </div>
            <div class="pointer-events-none absolute inset-0 opacity-[0.07]"
                style="background-image: linear-gradient(rgba(255,255,255,.5) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.5) 1px, transparent 1px); background-size: 60px 60px;">
            </div>

            <div class="relative">
                <a href="/" class="inline-flex items-center gap-3">
                    <div
                        class="w-11 h-11 rounded-2xl bg-gradient-to-br from-[#c99a3e] to-[#e6c06a] flex items-center justify-center shadow-[0_10px_30px_rgba(201,154,62,.35)]">
                        <svg class="w-6 h-6 text-[#071b3a]" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 3v18M5 8l7-5 7 5M3 8h18M5 21h14M7 8v9M17 8v9" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-lg font-bold tracking-tight">{{ config('app.name') }}</p>
                        <p class="text-[11px] font-medium tracking-[0.18em] uppercase text-[#c99a3e]">Legal · Strategic</p>
                    </div>
                </a>
            </div>

            <div class="relative max-w-md">
                <p class="text-[11px] font-semibold tracking-[0.2em] uppercase text-[#c99a3e]">Compliance Workspace</p>
                <h2 class="mt-4 text-4xl xl:text-5xl font-bold leading-[1.1] tracking-tight">
                    Regulatory clarity for <span class="text-gold-gradient">capital&nbsp;market</span> professionals.
                </h2>
                <p class="mt-5 text-base text-white/70 leading-relaxed">
                    A premium compliance review platform for legal counsels, investment managers, and corporate secretaries
                    — built for precision, trust, and institutional-grade workflow.
                </p>

                <div class="mt-10 grid grid-cols-3 gap-4">
                    <div class="rounded-2xl border border-white/10 bg-white/5 backdrop-blur p-4">
                        <p class="text-2xl font-bold text-white">24h</p>
                        <p class="mt-1 text-[11px] text-white/60 uppercase tracking-wider">Initial Review</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 backdrop-blur p-4">
                        <p class="text-2xl font-bold text-white">6+</p>
                        <p class="mt-1 text-[11px] text-white/60 uppercase tracking-wider">Practice Areas</p>
                    </div>
                    <div class="rounded-2xl border border-[#c99a3e]/30 bg-[#c99a3e]/10 backdrop-blur p-4">
                        <p class="text-2xl font-bold text-[#e6c06a]">100%</p>
                        <p class="mt-1 text-[11px] text-[#e6c06a]/80 uppercase tracking-wider">Confidential</p>
                    </div>
                </div>
            </div>

            <div class="relative flex items-center justify-between text-xs text-white/50">
                <p>&copy; {{ date('Y') }} {{ config('app.name', 'LawCo') }}. All rights reserved.</p>
                <div class="flex items-center gap-4">
                    <a href="#" class="hover:text-white transition">Privacy</a>
                    <a href="#" class="hover:text-white transition">Terms</a>
                </div>
            </div>
        </div>

        {{-- Right: Form --}}
        <div class="flex items-center justify-center px-6 sm:px-10 py-12 bg-white relative">
            {{-- Mobile brand --}}
            <div class="absolute top-6 left-6 lg:hidden">
                <a href="/" class="inline-flex items-center gap-2">
                    <div
                        class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#c99a3e] to-[#e6c06a] flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#071b3a]" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 3v18M5 8l7-5 7 5M3 8h18M5 21h14M7 8v9M17 8v9" />
                        </svg>
                    </div>
                    <span class="text-base font-bold text-[#071833]">{{ config('app.name', 'LawCo') }}</span>
                </a>
            </div>

            <div class="w-full max-w-md">
                <div class="text-center lg:text-left">
                    <span
                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#c99a3e]/10 ring-1 ring-[#c99a3e]/25 text-[11px] font-semibold tracking-wider uppercase text-[#8c6a25]">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#c99a3e]"></span>
                        Secure Access
                    </span>
                    <h1 class="mt-5 text-3xl sm:text-4xl font-bold tracking-tight text-[#071833]">Welcome back</h1>
                    <p class="mt-2 text-sm text-[#667085]">Sign in to your compliance workspace to continue.</p>
                </div>

                @if ($errors->any())
                    <div class="mt-6 flex items-start gap-3 p-4 rounded-2xl border border-rose-200/60 bg-rose-50/70">
                        <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                        <div class="text-sm text-rose-700">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if (session('status'))
                    <div
                        class="mt-6 flex items-center gap-3 p-4 rounded-2xl border border-emerald-200/60 bg-emerald-50/70 text-sm text-emerald-700">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-semibold text-[#071833] mb-2">Email Address</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-[#667085]">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.7">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                </svg>
                            </span>
                            <input type="email" name="email" id="email" value="{{ old('email') }}" required
                                autofocus autocomplete="username" class="input-premium" style="padding-left: 3rem"
                                placeholder="you@firm.com">
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label for="password" class="block text-sm font-semibold text-[#071833]">Password</label>
                            <a href="#"
                                class="text-xs font-semibold text-[#c99a3e] hover:text-[#8c6a25] transition">Forgot
                                password?</a>
                        </div>
                        <div x-data="{ show: false }" class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-4 text-[#667085]">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.7">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                                </svg>
                            </span>
                            <input :type="show ? 'text' : 'password'" name="password" id="password" required
                                autocomplete="current-password" class="input-premium pr-12" style="padding-left: 3rem"
                                placeholder="••••••••">
                            <button type="button" @click="show = !show"
                                class="absolute inset-y-0 right-0 flex items-center pr-4 text-[#667085] hover:text-[#071833] transition">
                                <svg x-show="!show" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="1.6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <svg x-show="show" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="1.6">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <label class="flex items-center gap-2.5 cursor-pointer select-none">
                        <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}
                            class="checkbox-premium">
                        <span class="text-sm text-[#071833]">Keep me signed in on this device</span>
                    </label>

                    <x-button type="submit" variant="primary" size="lg" class="w-full">
                        Sign In
                        <svg class="w-4 h-4 transition group-hover:translate-x-1" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </x-button>
                </form>

                @if (session('unverified'))
                    <div class="mt-4 p-4 rounded-2xl border border-[#e7eaf0] bg-[#fafbfd]">
                        <p class="text-xs font-semibold text-[#071833]">Belum menerima email aktivasi?</p>
                        <form method="POST" action="{{ route('verification.send') }}" class="flex gap-2 mt-2">
                            @csrf
                            <input type="email" name="email" value="{{ session('unverified_email', old('email')) }}"
                                placeholder="Alamat email Anda" required class="input-premium flex-1"
                                style="padding-left: 1rem">
                            <x-button type="submit" variant="primary" size="md" class="shrink-0">
                                Kirim Ulang
                            </x-button>
                        </form>
                    </div>
                @endif

                <p class="text-center text-xs text-[#667085]">
                    Belum punya akun?
                    <a href="{{ route('register') }}"
                        class="font-semibold text-[#c99a3e] hover:text-[#8c6a25] transition">Daftar</a>
                </p>
            </div>
        </div>
    </div>
@endsection
