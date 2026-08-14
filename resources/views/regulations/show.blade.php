@extends('layouts.app')

@section('title', $regulation->regulation_number)
@section('header', $regulation->regulation_number)

@section('content')
    {{-- Hero --}}
    <section class="relative overflow-hidden rounded-[24px] bg-navy-gradient text-white p-7 sm:p-9">
        <div class="pointer-events-none absolute -top-24 -right-16 w-80 h-80 rounded-full bg-[#c99a3e]/18 blur-3xl"></div>

        <div class="relative grid lg:grid-cols-3 gap-6 items-start">
            <div class="lg:col-span-2">
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[10.5px] font-bold rounded-full bg-[#c99a3e]/20 ring-1 ring-[#c99a3e]/30 text-[#e6c06a] uppercase tracking-wider">
                        <span class="w-1 h-1 rounded-full bg-[#e6c06a]"></span>
                        Regulasi
                    </span>
                    @if ($regulation->type)
                        <x-badge :color="$regulation->type->levelBadgeColor()">{{ $regulation->type->name }} — Level
                            {{ $regulation->type->level }}</x-badge>
                    @endif
                </div>
                <h2 class="mt-4 text-2xl sm:text-3xl font-bold tracking-tight">{{ $regulation->title }}</h2>
                <p class="mt-2 text-white/70 text-sm">{{ $regulation->regulation_number }}</p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-2xl border border-white/10 bg-white/5 backdrop-blur p-4">
                    <p class="text-[11px] font-semibold tracking-[0.16em] uppercase text-white/55">Tahun</p>
                    <p class="mt-2 text-2xl font-bold text-white">{{ $regulation->year }}</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 backdrop-blur p-4">
                    <p class="text-[11px] font-semibold tracking-[0.16em] uppercase text-white/55">Dokumen</p>
                    <p class="mt-2 text-2xl font-bold text-white">{{ $regulation->documents->count() }}</p>
                </div>
            </div>
        </div>
    </section>

    @php
        $hasParsing =
            $regulation->parse_status === 'parsing' ||
            $regulation->documents->contains(fn($d) => $d->parse_status === 'parsing');
        $extractProcessing = $regulation->isAiProcessing('extract');
        $aiProcessing = $regulation->isAiProcessing('regulation-ai');
    @endphp

    @if ($aiProcessing)
        <div class="mb-4 flex items-center gap-3 rounded-2xl bg-blue-50 ring-1 ring-blue-200 px-5 py-3">
            <svg class="w-5 h-5 text-blue-600 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
            </svg>
            <p class="text-sm font-bold text-blue-800">Generate AI sedang diproses di background (queue). Halaman refresh
                otomatis saat selesai.</p>
        </div>
        <script>
            setTimeout(() => location.reload(), 4000);
        </script>
    @endif

    @if ($extractProcessing)
        <div class="mb-4 flex items-center gap-3 rounded-2xl bg-blue-50 ring-1 ring-blue-200 px-5 py-3">
            <svg class="w-5 h-5 text-blue-600 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
            </svg>
            <p class="text-sm font-bold text-blue-800">Ekstraksi peraturan terkait sedang diproses. Halaman refresh otomatis
                saat selesai.</p>
        </div>
        <script>
            setTimeout(() => location.reload(), 4000);
        </script>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6" x-data="regulationParseProgress('{{ route('regulations.parse-progress', $regulation) }}', {{ $hasParsing ? 'true' : 'false' }})" x-init="start()">
        <div class="lg:col-span-2">
            @php
                $activePrompts = collect($aiPrompt)->where('is_active', true);
                $tab = in_array(request('tab'), ['info', 'short-review', 'vesa']) ? request('tab') : 'info';
            @endphp
            <div x-data="{ tab: '{{ $tab }}' }">
                <div class="flex items-center gap-1 rounded-2xl bg-[#f6f8fb] p-1 w-fit mb-6">
                    <button type="button" @click="tab = 'info'"
                        :class="tab === 'info' ? 'bg-white shadow-sm text-[#071833]' : 'text-[#667085] hover:text-[#071833]'"
                        class="px-4 py-2 text-xs font-bold rounded-xl transition">Info</button>
                    <button type="button" @click="tab = 'short-review'"
                        :class="tab === 'short-review' ? 'bg-white shadow-sm text-[#071833]' :
                            'text-[#667085] hover:text-[#071833]'"
                        class="px-4 py-2 text-xs font-bold rounded-xl transition">Short Review</button>
                    <button type="button" @click="tab = 'vesa'"
                        :class="tab === 'vesa' ? 'bg-white shadow-sm text-[#071833]' : 'text-[#667085] hover:text-[#071833]'"
                        class="px-4 py-2 text-xs font-bold rounded-xl transition">Tanya Kak Vesta</button>
                </div>

                <div x-show="tab === 'info'" class="space-y-6">
                    {{-- Metadata --}}
                    <x-card id="metadata-card">
                        <x-slot name="header">
                            <h3 class="text-lg font-bold text-[#071833]">Informasi Regulasi</h3>
                        </x-slot>
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <dt class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Nomor Regulasi
                                </dt>
                                <dd class="mt-1.5 text-sm font-semibold text-[#071833]">{{ $regulation->regulation_number }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Tahun</dt>
                                <dd class="mt-1.5 text-sm font-semibold text-[#071833]">{{ $regulation->year }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Jenis Regulasi
                                </dt>
                                <dd class="mt-1.5">
                                    @if ($regulation->type)
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="text-sm font-semibold text-[#071833]">{{ $regulation->type->name }}</span>
                                            <x-badge :color="$regulation->type->levelBadgeColor()">Level {{ $regulation->type->level }}</x-badge>
                                        </div>
                                    @else
                                        <span class="text-sm text-[#667085]">-</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Category</dt>
                                <dd class="mt-1.5 text-sm font-semibold text-[#071833]">
                                    {{ $regulation->category?->name ?? '-' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Terakhir
                                    Diperbarui</dt>
                                <dd class="mt-1.5 text-sm font-semibold text-[#071833]">
                                    {{ $regulation->updated_at->diffForHumans() ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Dibuat</dt>
                                <dd class="mt-1.5 text-sm font-semibold text-[#071833]">
                                    {{ $regulation->created_at->format('d F Y') ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Tanggal DiTetapkan
                                </dt>
                                <dd class="mt-1.5 text-sm font-semibold text-[#071833]">
                                    {{ $regulation->tanggal_tetapkan?->format('d F Y') ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Tanggal
                                    DiUndangkan</dt>
                                <dd class="mt-1.5 text-sm font-semibold text-[#071833]">
                                    {{ $regulation->tanggal_diundangkan?->format('d F Y') ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Tanggal Berlaku
                                </dt>
                                <dd class="mt-1.5 text-sm font-semibold text-[#071833]">
                                    {{ $regulation->effective_date?->format('d F Y') ?? '-' }}</dd>
                            </div>
                        </dl>
                    </x-card>

                    {{-- Sub Categories --}}
                    <x-card>
                        <x-slot name="header">
                            <h3 class="text-lg font-bold text-[#071833]">Sub Category</h3>
                        </x-slot>
                        @if ($regulation->subCategories->isEmpty())
                            <p class="text-sm text-[#667085]">Belum ada sub category yang dipilih.</p>
                        @else
                            <div class="flex flex-wrap gap-2">
                                @foreach ($regulation->subCategories as $sub)
                                    <x-badge :color="$sub->is_active ? 'gold' : 'gray'">{{ $sub->name }}</x-badge>
                                @endforeach
                            </div>
                        @endif
                    </x-card>

                    {{-- Related Regulations --}}
                    <x-card :padding="false">
                        <x-slot name="header">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-[#071833]">Peraturan Terkait</h3>
                                    <p class="text-xs text-[#667085] mt-0.5">Regulasi yang saling berkaitan</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if (auth()->user()->hasPermission('upload_regulations'))
                                        @if ($extractProcessing)
                                            <span
                                                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl text-xs font-bold text-blue-700 bg-blue-50 ring-1 ring-blue-200">
                                                <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                                                </svg>
                                                Memproses...
                                            </span>
                                        @else
                                            <form method="POST"
                                                action="{{ route('regulations.extract-references', $regulation) }}">
                                                @csrf
                                                <x-button type="submit" variant="primary" size="sm">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                                        stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                                    </svg>
                                                    Ekstrak Peraturan Terkait
                                                </x-button>
                                            </form>
                                        @endif
                                    @endif
                                    <span
                                        class="px-3 py-1 rounded-full bg-[#f6f8fb] text-xs font-bold text-[#667085]">{{ $regulation->relatedRegulations->count() }}
                                        item</span>
                                </div>
                            </div>
                        </x-slot>
                        @if ($regulation->relatedRegulations->isEmpty())
                            @php $extractedTerkait = $regulation->relatedReferences->where('relationship', '!=', 'dicabut'); @endphp
                            @if ($extractedTerkait->isEmpty())
                                <div class="text-center py-10">
                                    <p class="text-sm text-[#667085]">Belum ada peraturan terkait.</p>
                                </div>
                            @endif
                        @else
                            <ul class="divide-y divide-[#f0f3f8]">
                                @foreach ($regulation->relatedRegulations as $related)
                                    <li class="flex items-center gap-4 px-6 py-4 hover:bg-[#f6f8fb]/60 transition">
                                        <div
                                            class="shrink-0 w-10 h-10 rounded-xl bg-[#f6f8fb] text-[#c99a3e] flex items-center justify-center">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor" stroke-width="1.6">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m9.86-2.04a4.5 4.5 0 0 0-1.242-7.244l-4.5-4.5a4.5 4.5 0 0 0-6.364 6.364L4.34 8.598" />
                                            </svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <a href="{{ route('regulations.show', $related) }}"
                                                class="text-sm font-semibold text-[#071833] hover:text-[#c99a3e] transition">{{ $related->regulation_number }}</a>
                                            <p class="text-xs text-[#667085] mt-0.5 line-clamp-1">{{ $related->title }}
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            @if ($related->type)
                                                <x-badge :color="$related->type->levelBadgeColor()">Lv{{ $related->type->level }}</x-badge>
                                            @endif
                                            <span class="text-xs font-semibold text-[#667085]">{{ $related->year }}</span>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @php
                            $extractedRefs = $regulation->relatedReferences;
                            $terkait = $extractedRefs->where('relationship', '!=', 'dicabut');
                        @endphp

                        @if ($regulation->isParsed() && $terkait->isNotEmpty())
                            <div class="border-t border-[#e7eaf0]">
                                <div class="px-6 pt-5 pb-2">
                                    <h4 class="text-sm font-bold text-[#071833]">Peraturan Terkait <span
                                            class="text-[#667085] font-semibold">(hasil ekstraksi)</span></h4>
                                </div>
                                <div class="overflow-x-auto">
                                    <table class="table-premium">
                                        <thead>
                                            <tr>
                                                <th class="text-left">Nama / Nomor</th>
                                                <th class="text-center">Tahun</th>
                                                <th class="text-center">Hubungan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($terkait as $ref)
                                                <tr>
                                                    <td>
                                                        <p class="text-sm font-semibold text-[#071833]">
                                                            {{ $ref->name }}</p>
                                                        @if ($ref->number)
                                                            <p class="text-xs text-[#667085]">Nomor: {{ $ref->number }}
                                                            </p>
                                                        @endif
                                                    </td>
                                                    <td class="text-center text-sm font-semibold text-[#071833]">
                                                        {{ $ref->year ?? '-' }}</td>
                                                    <td class="text-center">
                                                        <x-badge :color="match ($ref->relationship) {
                                                            'diubah' => 'amber',
                                                            'dicabut' => 'rose',
                                                            default => 'blue',
                                                        }">{{ $ref->relationship }}</x-badge>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </x-card>

                    {{-- Revoked Regulations --}}
                    @php $revoked = $regulation->relatedReferences->where('relationship', 'dicabut'); @endphp
                    <x-card :padding="false">
                        <x-slot name="header">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-[#071833]">Peraturan dicabut dan dinyatakan tidak
                                        berlaku
                                    </h3>
                                    <p class="text-xs text-[#667085] mt-0.5">Regulasi yang dicabut oleh regulasi ini</p>
                                </div>
                                <span
                                    class="px-3 py-1 rounded-full bg-[#f6f8fb] text-xs font-bold text-[#667085]">{{ $revoked->count() }}
                                    item</span>
                            </div>
                        </x-slot>
                        @if ($revoked->isEmpty())
                            <div class="text-center py-10">
                                <p class="text-sm text-[#667085]">Belum ada peraturan dicabut.</p>
                            </div>
                        @else
                            <div class="overflow-x-auto">
                                <table class="table-premium">
                                    <thead>
                                        <tr>
                                            <th class="text-left">Nama / Nomor</th>
                                            <th class="text-center">Tahun</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($revoked as $ref)
                                            <tr>
                                                <td>
                                                    <p class="text-sm font-semibold text-[#071b33]">{{ $ref->name }}
                                                    </p>
                                                    @if ($ref->number)
                                                        <p class="text-xs text-[#667085]">Nomor: {{ $ref->number }}</p>
                                                    @endif
                                                </td>
                                                <td class="text-center text-sm font-semibold text-[#071833]">
                                                    {{ $ref->year ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </x-card>



                    {{-- Dokumen Tambahan --}}
                    <x-card :padding="false">
                        <x-slot name="header">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-[#071833]">Dokumen Tambahan</h3>
                                    <p class="text-xs text-[#667085] mt-0.5">Dokumen pendukung untuk regulasi ini</p>
                                </div>
                                <span
                                    class="px-3 py-1 rounded-full bg-[#f6f8fb] text-xs font-bold text-[#667085]">{{ $regulation->documents->count() }}
                                    file</span>
                            </div>
                        </x-slot>
                        @if ($regulation->documents->isEmpty())
                            <div class="text-center py-10">
                                <div
                                    class="mx-auto w-14 h-14 rounded-2xl bg-[#f6f8fb] flex items-center justify-center text-[#c99a3e]">
                                    <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="1.4">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12-3-3m0 0-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                    </svg>
                                </div>
                                <p class="mt-3 text-sm font-bold text-[#071833]">Belum ada dokumen tambahan</p>
                                <p class="text-xs text-[#667085] mt-1">Upload dokumen pendukung melalui halaman edit.</p>
                            </div>
                        @else
                            @php $progress = $regulation->documentsParseProgress(); @endphp

                            {{-- Banner: muncul jika main regulation sudah diparse & ada dokumen yang belum --}}
                            @if ($regulation->isParsed() && $progress['pending'] > 0 && auth()->user()->hasPermission('upload_regulations'))
                                <div
                                    class="mx-6 mt-4 flex items-center justify-between gap-4 rounded-xl bg-blue-50 border border-blue-200 px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="flex items-center justify-center w-9 h-9 rounded-full bg-blue-100 text-blue-600 shrink-0">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor" stroke-width="1.6">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                                            </svg>
                                        </span>
                                        <div>
                                            <p class="text-sm font-semibold text-blue-800">{{ $progress['pending'] }} dari
                                                {{ $progress['total'] }} dokumen belum diparse</p>
                                            <p class="text-xs text-blue-600 mt-0.5">Parse teks dokumen tambahan untuk
                                                analisis
                                                lanjutan.</p>
                                        </div>
                                    </div>
                                    <form method="POST"
                                        action="{{ route('regulations.documents.parse-all', $regulation) }}">
                                        @csrf
                                        <x-button type="submit" variant="primary" size="sm">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                                            </svg>
                                            Parse Semua
                                        </x-button>
                                    </form>
                                </div>
                            @endif

                            {{-- Progress bar --}}
                            @if ($progress['parsed'] > 0)
                                <div class="mx-6 mt-3">
                                    <div class="flex items-center justify-between text-xs text-[#667085] mb-1">
                                        <span>Progress parse dokumen</span>
                                        <span
                                            class="font-semibold">{{ $progress['parsed'] }}/{{ $progress['total'] }}</span>
                                    </div>
                                    <div class="w-full h-1.5 rounded-full bg-[#f0f3f8] overflow-hidden">
                                        <div class="h-full rounded-full bg-emerald-500 transition-all"
                                            style="width: {{ $progress['percentage'] }}%"></div>
                                    </div>
                                </div>
                            @endif

                            <ul class="divide-y divide-[#f0f3f8]">
                                @foreach ($regulation->documents as $doc)
                                    @php
                                        $ext = pathinfo($doc->file_path, PATHINFO_EXTENSION);
                                        $iconColor = match ($ext) {
                                            'pdf' => 'bg-rose-50 text-rose-500',
                                            'docx', 'doc' => 'bg-blue-50 text-blue-500',
                                            'xlsx', 'xls' => 'bg-emerald-50 text-emerald-500',
                                            'pptx', 'ppt' => 'bg-orange-50 text-orange-500',
                                            default => 'bg-[#f6f8fb] text-[#667085]',
                                        };
                                        $statusBadge = match ($doc->parse_status) {
                                            'complete' => 'bg-emerald-100 text-emerald-700',
                                            'incomplete' => 'bg-amber-100 text-amber-700',
                                            'parsing' => 'bg-blue-100 text-blue-700',
                                            'failed' => 'bg-rose-100 text-rose-500',
                                            default => 'bg-gray-100 text-gray-500',
                                        };
                                    @endphp
                                    <li class="flex items-center gap-4 px-6 py-4 hover:bg-[#f6f8fb]/60 transition">
                                        <div
                                            class="shrink-0 w-11 h-11 rounded-xl {{ $iconColor }} flex items-center justify-center">
                                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                                <path
                                                    d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zM14 3.5L18.5 8H14V3.5zM6 20V4h7v5h5v11H6z" />
                                            </svg>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <a href="{{ route('regulations.documents.view', $doc) }}" target="_blank"
                                                class="text-sm font-semibold text-[#071833] hover:text-[#c99a3e] transition truncate block"
                                                title="{{ $doc->name }}">{{ $doc->name }}</a>
                                            <p class="text-xs text-[#667085] mt-0.5">{{ $doc->document_type ?: '-' }}
                                                @if ($ext)
                                                    <span class="mx-1">&middot;</span>{{ strtoupper($ext) }}
                                                @endif
                                                <span
                                                    class="ml-2 inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold {{ $statusBadge }}">{{ $doc->parseStatusLabel() }}</span>
                                            </p>
                                            @if ($doc->parse_status === 'parsing')
                                                <div class="mt-2 flex items-center gap-2">
                                                    <div
                                                        class="flex-1 h-1.5 rounded-full bg-[#f6f8fb] ring-1 ring-[#e7eaf0] overflow-hidden">
                                                        <div class="h-full bg-[#c99a3e] rounded-full transition-all duration-500"
                                                            :style="`width: ${docProgress({{ $doc->id }}) ?? {{ $doc->parse_progress ?? 0 }}}%`">
                                                        </div>
                                                    </div>
                                                    <span class="text-[10px] font-bold text-[#667085]"
                                                        x-text="(docProgress({{ $doc->id }}) ?? {{ $doc->parse_progress ?? 0 }}) + '%'"></span>
                                                    @if (auth()->user()->hasPermission('upload_regulations'))
                                                        <form method="POST"
                                                            action="{{ route('regulations.documents.parse-cancel', [$regulation, $doc]) }}"
                                                            class="inline"
                                                            onsubmit="return confirm('Batalkan parse dokumen ini?')">
                                                            @csrf
                                                            <button type="submit"
                                                                class="text-[10px] font-bold text-rose-500 hover:text-rose-700 transition">Batalkan</button>
                                                        </form>
                                                    @endif
                                                </div>
                                            @endif
                                            @if ($doc->parse_status === 'failed' && $doc->parse_error)
                                                <p class="mt-1.5 text-[10px] font-medium text-rose-500 break-words">
                                                    {{ $doc->parse_error }}</p>
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            @if ($doc->isParsed())
                                                @if (auth()->user()->hasPermission('upload_regulations'))
                                                    <form method="POST"
                                                        action="{{ route('regulations.documents.parse', [$regulation, $doc]) }}"
                                                        class="inline">
                                                        @csrf
                                                        <x-button type="submit" variant="ghost" size="sm">
                                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                                                                stroke="currentColor" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                                                            </svg>
                                                            Re-Parse
                                                        </x-button>
                                                    </form>
                                                @endif
                                            @else
                                                @if (auth()->user()->hasPermission('upload_regulations'))
                                                    <form method="POST"
                                                        action="{{ route('regulations.documents.parse', [$regulation, $doc]) }}"
                                                        class="inline">
                                                        @csrf
                                                        <x-button type="submit" variant="outline" size="sm">
                                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                                                                stroke="currentColor" stroke-width="2">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                                                            </svg>
                                                            Parse
                                                        </x-button>
                                                    </form>
                                                @endif
                                            @endif
                                            <a href="{{ route('regulations.documents.view', $doc) }}" target="_blank"
                                                class="inline-flex items-center gap-1.5 px-3 h-9 rounded-xl text-xs font-semibold text-[#071833] bg-[#f6f8fb] ring-1 ring-[#e7eaf0] hover:bg-white hover:ring-[#c99a3e]/40 transition">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                </svg>
                                                Preview
                                            </a>
                                            <a href="{{ route('regulations.documents.view', $doc) }}" download
                                                class="inline-flex items-center gap-1.5 px-3 h-9 rounded-xl text-xs font-semibold text-[#071833] bg-[#f6f8fb] ring-1 ring-[#e7eaf0] hover:bg-white hover:ring-[#c99a3e]/40 transition">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                                </svg>
                                                Download
                                            </a>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-card>

                    @if ($regulation->isParsed())
                        @if (auth()->user()->role != 'user')
                            <x-card id="hasil-parse" x-data="{ parseTab: 'result' }">
                                <x-slot name="header">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h3 class="text-lg font-bold text-[#071833]">Hasil Parse PDF</h3>
                                            <p class="text-xs text-[#667085] mt-0.5">Hasil ekstraksi teks dari file
                                                regulasi</p>
                                        </div>
                                        <div class="flex items-center gap-1 rounded-xl bg-[#f6f8fb] p-1">
                                            <button type="button" @click="parseTab = 'result'"
                                                :class="parseTab === 'result' ? 'bg-white shadow-sm text-[#071833]' :
                                                    'text-[#667085] hover:text-[#071833]'"
                                                class="px-3 py-1.5 text-xs font-bold rounded-lg transition">Hasil
                                                Parse</button>
                                            <button type="button" @click="parseTab = 'analysis'"
                                                :class="parseTab === 'analysis' ? 'bg-white shadow-sm text-[#071833]' :
                                                    'text-[#667085] hover:text-[#071833]'"
                                                class="px-3 py-1.5 text-xs font-bold rounded-lg transition">Analisa
                                                %</button>
                                        </div>
                                    </div>
                                </x-slot>

                                <div x-show="parseTab === 'result'">
                                    <div
                                        class="text-xs text-[#071833] leading-relaxed bg-[#f6f8fb] rounded-xl p-4 max-h-96 overflow-y-auto">
                                        @formatText($regulation->parsed_text)</div>
                                </div>

                                <div x-show="parseTab === 'analysis'">
                                    @php $stats = $regulation->parse_stats; @endphp
                                    <div class="space-y-4">
                                        <div class="grid grid-cols-2 gap-3">
                                            <div class="rounded-xl bg-[#f6f8fb] p-4">
                                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">
                                                    Total
                                                    Halaman
                                                </p>
                                                <p class="mt-1.5 text-2xl font-bold text-[#071833]">
                                                    {{ $stats['total_pages'] ?? '-' }}
                                                </p>
                                            </div>
                                            <div class="rounded-xl bg-[#f6f8fb] p-4">
                                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">
                                                    Terdeteksi
                                                </p>
                                                <p class="mt-1.5 text-2xl font-bold text-emerald-600">
                                                    {{ $stats['parsed_pages'] ?? '-' }}</p>
                                            </div>
                                            <div class="rounded-xl bg-[#f6f8fb] p-4">
                                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">
                                                    Kosong</p>
                                                <p class="mt-1.5 text-2xl font-bold text-amber-600">
                                                    {{ $stats['empty_pages'] ?? '-' }}
                                                </p>
                                            </div>
                                            <div class="rounded-xl bg-[#f6f8fb] p-4">
                                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">
                                                    Persentase
                                                </p>
                                                <p class="mt-1.5 text-2xl font-bold text-[#071833]">
                                                    {{ ($stats['percent_parsed'] ?? 0) . '%' }}</p>
                                            </div>
                                            <div class="rounded-xl bg-[#f6f8fb] p-4">
                                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">
                                                    Normal
                                                    Pages
                                                </p>
                                                <p class="mt-1.5 text-2xl font-bold text-blue-600">
                                                    {{ $stats['normal_pages'] ?? 0 }}
                                                </p>
                                            </div>
                                            <div class="rounded-xl bg-[#f6f8fb] p-4">
                                                <p class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">
                                                    OCR Pages
                                                </p>
                                                <p class="mt-1.5 text-2xl font-bold text-purple-600">
                                                    {{ $stats['ocr_pages'] ?? 0 }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="rounded-xl bg-[#f6f8fb] p-4">
                                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Total
                                                Karakter
                                            </p>
                                            <p class="mt-1.5 text-2xl font-bold text-[#071833">
                                                {{ number_format($stats['char_total'] ?? 0) }}</p>
                                        </div>
                                        <div class="rounded-xl bg-[#f6f8fb] p-4">
                                            <p class="text-[11px] font-bold uppercase tracking-wider text-[#667085]">Tipe
                                                PDF</p>
                                            <p class="mt-1.5">
                                                @if (!empty($stats['used_ocr']))
                                                    <span
                                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-700">OCR</span>
                                                @else
                                                    <span
                                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-700">Normal</span>
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </x-card>

                        @endif
                    @endif

                    @if (auth()->user()->hasPermission('upload_regulations'))
                        <x-card id="generate-ai">
                            <x-slot name="header">
                                <div>
                                    <h3 class="text-lg font-bold text-[#071833]">Generate AI</h3>
                                    <p class="text-xs text-[#667085] mt-0.5">Pilih prompt lalu generate analisa AI untuk
                                        regulasi
                                        ini. Hasil prompt "Short Review" tampil di tab Short Review.</p>
                                </div>
                            </x-slot>
                            <div class="p-6">
                                <form method="POST" action="{{ route('regulations.generate-ai', $regulation) }}"
                                    class="flex flex-col sm:flex-row gap-3">
                                    @csrf
                                    <select name="ai_prompt_id" required class="select-premium flex-1">
                                        @foreach ($activePrompts as $prompt)
                                            <option value="{{ $prompt->id }}">{{ $prompt->title }}</option>
                                        @endforeach
                                    </select>
                                    <x-button type="submit" variant="primary">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                                        </svg>
                                        Generate AI
                                    </x-button>
                                </form>
                            </div>
                        </x-card>
                    @endif
                </div>

                <div x-show="tab === 'short-review'" x-cloak class="space-y-6">
                    <x-card :padding="false">
                        <x-slot name="header">
                            <div>
                                <h3 class="text-lg font-bold text-[#071833]">Short Review</h3>
                                <p class="text-xs text-[#667085] mt-0.5">Hasil generate AI dengan prompt "Short Review"
                                    untuk regulasi ini.</p>
                            </div>
                        </x-slot>
                        <div class="p-6">
                            @php
                                $shortReviewResults = $regulation->aiResults
                                    ->filter(fn($r) => $r->prompt_title === 'Short Review')
                                    ->sortByDesc('created_at');
                            @endphp

                            @if ($shortReviewResults->isNotEmpty())
                                <div class="space-y-3">
                                    @foreach ($shortReviewResults as $aiResult)
                                        <div class="rounded-2xl border border-[#e7eaf0] bg-white overflow-hidden">
                                            <div
                                                class="flex items-center justify-between gap-3 px-4 py-3 bg-[#f6f8fb]/60 border-b border-[#e7eaf0]">
                                                <div class="min-w-0">
                                                    <p class="text-sm font-semibold text-[#071833] truncate">
                                                        {{ $aiResult->prompt_title }}</p>
                                                    <p class="text-[10px] text-[#667085] mt-0.5">
                                                        {{ $aiResult->created_at->format('d M Y H:i') }}</p>
                                                </div>
                                            </div>
                                            <div
                                                class="px-4 py-4 text-xs text-[#071833] leading-relaxed whitespace-pre-line">
                                                {{ $aiResult->result }}
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="text-center py-10">
                                    <p class="text-sm text-[#667085]">Belum ada hasil Short Review untuk regulasi ini.</p>
                                    @if (auth()->user()->hasPermission('upload_regulations'))
                                        <p class="text-xs text-[#667085] mt-1">Generate melalui tab Info.</p>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </x-card>
                </div>

                <div x-show="tab === 'vesa'" x-cloak class="space-y-6">
                    <x-card id="tanya-kak-vesa" x-data="vesaChat('{{ route('regulations.chat.ask', $regulation) }}', '{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}')">
                        <x-slot name="header">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-[#071833]">Tanya Kak Vesta</h3>
                                    <p class="text-xs text-[#667085] mt-0.5">Asisten AI LawCo — bisa membaca teks
                                        regulasi &amp; dokumen tambahan.</p>
                                </div>
                            </div>
                        </x-slot>

                        <div x-ref="messages" class="max-h-[26rem] overflow-y-auto space-y-3">
                            @forelse ($chatMessages as $msg)
                                @if ($msg->role === 'user')
                                    {{-- User Message --}} <div class="flex items-start justify-end gap-2.5">
                                        <div
                                            class="max-w-[80%] rounded-2xl rounded-tr-md bg-navy-gradient px-4 py-3 text-sm leading-6 text-white shadow-sm">
                                            <div class="whitespace-pre-wrap break-words"> {{ $msg->content }} </div>
                                        </div>
                                        <div
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#071b3a] to-[#0b2a55] text-xs font-bold text-white ring-1 ring-[#c99a3e]/40">
                                            {{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }} </div>
                                    </div>
                                @else
                                    {{-- AI Message --}} <div class="flex items-start gap-2.5">
                                        <div
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-[#c99a3e] to-[#e6c06a] text-xs font-bold text-[#071b3a]">
                                            V </div>
                                        <div
                                            class="max-w-[85%] min-w-0 rounded-2xl rounded-tl-md bg-[#f6f8fb] px-4 py-3 text-sm leading-6 text-[#071833] shadow-sm ring-1 ring-[#e7eaf0]">
                                            <div class="chat-message whitespace-pre-wrap break-words"> {{ $msg->content }}
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @empty
                                <div x-ref="empty" class="text-center py-10">
                                    <div
                                        class="mx-auto w-12 h-12 rounded-2xl bg-[#f6f8fb] flex items-center justify-center text-[#c99a3e]">
                                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                                        </svg>
                                    </div>
                                    <p class="mt-3 text-sm font-semibold text-[#071833]">Belum ada percakapan.</p>
                                    <p class="text-xs text-[#667085] mt-1">Tanyakan hal-hal seputar regulasi atau isi
                                        dokumen ini.</p>
                                </div>
                            @endforelse
                        </div>

                        <div class="mt-4 border-t border-[#e7eaf0] pt-4">
                            <form @submit.prevent="send()" class="flex items-center gap-2">
                                <label class="relative flex-1">
                                    <textarea x-model="question" :disabled="sending" rows="2" maxlength="4000"
                                        class="input-premium resize-none" placeholder="Tanya Kak Vesta tentang regulasi ini…"></textarea>
                                </label>
                                <button type="submit" :disabled="sending"
                                    class="shrink-0 inline-flex items-center justify-center gap-2 h-11 px-4 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-[#c99a3e] to-[#b17c24] hover:brightness-110 transition disabled:opacity-60 disabled:cursor-not-allowed">
                                    <svg x-show="!sending" class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                                    </svg>
                                    <svg x-show="sending" class="w-4 h-4 animate-spin" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                                    </svg>
                                    <span x-text="sending ? 'Memproses…' : 'Kirim'"></span>
                                </button>
                            </form>
                            <div class="mt-1.5 flex items-center justify-between gap-3 text-[10px] text-[#667085]">
                                <span>Contoh: Ringkas pasal yang mengatur keterbukaan informasi.</span>
                                <span x-text="question.length + ' / 4000'"></span>
                            </div>
                            <p x-show="error" x-cloak class="mt-2 text-xs font-medium text-rose-600" x-text="error"></p>
                        </div>
                    </x-card>
                </div>
            </div>
        </div>

        <aside class="space-y-6">
            @if (auth()->user()->hasPermission('upload_regulations'))
                {{-- File Regulasi --}}
                <x-card>
                    <x-slot name="header">
                        <h3 class="text-base font-bold text-[#071833]">File Regulasi</h3>
                    </x-slot>
                    <a href="{{ route('regulations.file', $regulation) }}" target="_blank"
                        class="block p-3 rounded-xl bg-[#f6f8fb] hover:bg-[#f0f3f8] transition">
                        <div class="flex items-center gap-3">
                            <div
                                class="shrink-0 w-10 h-10 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zM14 3.5L18.5 8H14V3.5zM6 20V4h7v5h5v11H6z" />
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-[#071833] truncate">File PDF</p>
                                <p class="text-xs text-[#667085]">Regulasi utama</p>
                            </div>
                        </div>
                    </a>
                    <a href="{{ route('regulations.file-raw', $regulation) }}" download
                        class="mt-3 flex items-center justify-center gap-2 w-full h-11 rounded-xl bg-[#f6f8fb] text-sm font-semibold text-[#071833] ring-1 ring-[#e7eaf0] hover:bg-white hover:ring-[#c99a3e]/40 transition">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Download PDF
                    </a>
                </x-card>

                {{-- Actions --}}
                <x-card>
                    <x-slot name="header">
                        <h3 class="text-base font-bold text-[#071833]">Aksi</h3>
                    </x-slot>
                    <div class="space-y-2.5">
                        @if (!auth()->user()->hasPermission('upload_regulations'))
                            <div
                                class="flex items-center gap-2 px-3 py-2 rounded-lg bg-[#f6f8fb] ring-1 ring-[#e7eaf0] text-xs font-semibold text-[#667085]">
                                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                </svg>
                                Dokumen hanya untuk dibaca
                            </div>
                            @if ($regulation->isParsed())
                                <a href="#hasil-parse"
                                    class="inline-flex items-center justify-center gap-2 w-full h-11 rounded-xl bg-[#c99a3e] text-sm font-bold text-white hover:bg-[#b88a2e] transition">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                    </svg>
                                    Lihat Hasil Parse
                                </a>
                            @endif
                        @else
                            @if ($regulation->parse_status === 'complete')
                                <div
                                    class="flex items-center gap-2 px-3 py-2 rounded-lg bg-emerald-50 text-emerald-700 text-xs font-semibold">
                                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    Teks berhasil diekstrak
                                </div>
                                <a href="#hasil-parse"
                                    class="inline-flex items-center justify-center gap-2 w-full h-11 rounded-xl bg-[#c99a3e] text-sm font-bold text-white hover:bg-[#b88a2e] transition">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                                    </svg>
                                    Lihat Hasil Parse
                                </a>
                            @elseif ($regulation->parse_status === 'incomplete')
                                <div
                                    class="flex items-center gap-2 px-3 py-2 rounded-lg bg-amber-50 text-amber-700 text-xs font-semibold">
                                    <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                                    </svg>
                                    Parse tidak lengkap (incomplete) — klik Parse PDF untuk mengulang
                                </div>
                                <form method="POST" action="{{ route('regulations.parse', $regulation) }}">
                                    @csrf
                                    <x-button type="submit" variant="primary" class="w-full justify-start">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                                        </svg>
                                        Parse PDF
                                    </x-button>
                                </form>
                            @elseif($regulation->parse_status === 'failed')
                                <div
                                    class="flex items-start gap-2 px-3 py-2 rounded-lg bg-rose-50 text-rose-700 text-xs font-semibold">
                                    <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                    </svg>
                                    <span class="text-rose-700">
                                        Parse gagal

                                    </span>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <form method="POST" action="{{ route('regulations.parse', $regulation) }}">
                                        @csrf
                                        <x-button type="submit" variant="primary" class="w-full justify-center">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                                            </svg>
                                            Parse Ulang
                                        </x-button>
                                    </form>
                                    <form method="POST" action="{{ route('regulations.parse-cancel', $regulation) }}"
                                        onsubmit="return confirm('Batalkan parse regulasi ini?')">
                                        @csrf
                                        <x-button type="submit" variant="outline"
                                            class="w-full justify-center text-rose-600">
                                            Batalkan
                                        </x-button>
                                    </form>
                                </div>
                            @elseif($regulation->parse_status === 'parsing')
                                <div class="space-y-2">
                                    <div
                                        class="flex items-center justify-between px-1 text-xs font-semibold text-[#667085]">
                                        <span
                                            x-text="display < 5 ? 'Menyiapkan dokumen...' : 'Memproses halaman...'"></span>
                                        <span class="text-[#071833]" x-text="display + '%'"></span>
                                    </div>
                                    <div class="h-2.5 rounded-full bg-[#f6f8fb] ring-1 ring-[#e7eaf0] overflow-hidden">
                                        <div class="h-full bg-gradient-to-r from-[#c99a3e] to-[#e6c06a] rounded-full transition-all duration-500"
                                            :style="`width: ${display}%`"></div>
                                    </div>
                                    <p class="text-[10px] text-[#b0b8c5] px-1">Berjalan di background. Halaman otomatis
                                        refresh
                                        saat selesai.</p>
                                    <form method="POST" action="{{ route('regulations.parse-cancel', $regulation) }}"
                                        onsubmit="return confirm('Batalkan parse yang sedang berjalan?')">
                                        @csrf
                                        <button type="submit"
                                            class="w-full inline-flex items-center justify-center gap-2 h-9 rounded-lg bg-rose-50 text-rose-600 text-xs font-bold ring-1 ring-rose-200 hover:bg-rose-100 transition">
                                            Batalkan Parse
                                        </button>
                                    </form>
                                </div>
                            @else
                                <form method="POST" action="{{ route('regulations.parse', $regulation) }}">
                                    @csrf
                                    <x-button type="submit" variant="primary" class="w-full justify-start">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                            stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                                        </svg>
                                        Parse PDF
                                    </x-button>
                                </form>
                            @endif
                            <a href="{{ route('regulations.analyze', $regulation) }}"
                                class="inline-flex items-center justify-center gap-2 w-full h-11 rounded-xl bg-emerald-600 text-sm font-bold text-white hover:bg-emerald-700 transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0 5.25 5.25M13.5 3.75h4.5m-4.5 0v4.5m0-4.5 5.25 5.25M3.75 13.5h4.5m-4.5 0v4.5m0-4.5 5.25-5.25M13.5 20.25h4.5m-4.5 0v-4.5m0 4.5 5.25-5.25" />
                                </svg>
                                Checking Hasil Parse
                            </a>
                            <x-button href="{{ route('regulations.edit', $regulation) }}" variant="outline"
                                class="w-full justify-start">
                                <svg class="w-4 h-4 text-[#c99a3e]" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M16.862 4.487 18.55 2.8a2.121 2.121 0 1 1 3 3L19.863 7.487m-3-3L8.25 13.1l-1.5 4.5 4.5-1.5 8.613-8.613m-3-3 3 3" />
                                </svg>
                                Edit Regulasi
                            </x-button>
                            <form method="POST" action="{{ route('regulations.destroy', $regulation) }}"
                                id="delete-regulation-form">
                                @csrf
                                @method('DELETE')
                                <x-button type="button" variant="danger" class="w-full justify-start"
                                    @click="$dispatch('open-modal-confirm-delete-regulation')">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                        stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                    Hapus Regulasi
                                </x-button>
                            </form>
                        @endif
                        <x-button href="{{ route('regulations.index') }}" variant="ghost" class="w-full justify-start">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                            </svg>
                            Kembali ke Daftar
                        </x-button>
                    </div>
                </x-card>
            @endif

            {{-- Hierarchy Info --}}
            <x-card>
                <x-slot name="header">
                    <h3 class="text-base font-bold text-[#071833]">Hierarki Regulasi</h3>
                </x-slot>
                @if ($regulation->type)
                    <div class="space-y-2">
                        @for ($i = 1; $i <= 5; $i++)
                            @php
                                $colors = [1 => 'red', 2 => 'orange', 3 => 'yellow', 4 => 'blue', 5 => 'green'];
                                $isActive = $regulation->type->level === $i;
                            @endphp
                            <div
                                class="flex items-center gap-3 p-2 rounded-lg {{ $isActive ? 'bg-[#f6f8fb] ring-1 ring-[#c99a3e]/30' : '' }}">
                                <x-badge :color="$colors[$i]">Lv {{ $i }}</x-badge>
                                <span class="text-xs {{ $isActive ? 'font-bold text-[#071833]' : 'text-[#667085]' }}">
                                    {{ $i === 1 ? 'Tertinggi' : ($i === 5 ? 'Terendah' : '') }}
                                    @if ($isActive)
                                        ← Regulasi ini
                                    @endif
                                </span>
                            </div>
                        @endfor
                    </div>
                @endif
            </x-card>
        </aside>
    </div>

    @if (auth()->user()->hasPermission('upload_regulations'))
        <x-modal name="confirm-delete-regulation" title="Hapus Regulasi" maxWidth="md">
            <div class="flex items-start gap-4">
                <span class="shrink-0 flex items-center justify-center w-10 h-10 rounded-full bg-rose-50 text-rose-500">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                    </svg>
                </span>
                <div>
                    <p class="text-sm font-semibold text-[#071833]">Hapus Regulasi</p>
                    <p class="mt-1 text-sm text-[#667085] leading-relaxed">Apakah Anda yakin ingin menghapus regulasi ini
                        beserta seluruh dokumen terkait? Aksi ini tidak dapat dibatalkan.</p>
                </div>
            </div>
            <x-slot name="footer">
                <x-button type="button" variant="outline"
                    @click="$dispatch('close-modal-confirm-delete-regulation')">Batal</x-button>
                <x-button type="button" variant="danger"
                    onclick="document.getElementById('delete-regulation-form').submit()">Hapus</x-button>
            </x-slot>
        </x-modal>
    @endif
@endsection

@push('scripts')
    <script>
        function vesaChat(url, userInitial) {
            return {
                question: '',
                sending: false,
                error: '',
                async send() {
                    const q = this.question.trim();
                    if (!q || this.sending) return;

                    this.sending = true;
                    this.error = '';
                    this.question = '';
                    this.appendBubble('user', q);
                    this.appendBubble('assistant', 'Kak Vesta sedang mengetik…', true);

                    try {
                        const res = await fetch(url, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({
                                question: q
                            }),
                        });

                        const data = await res.json();
                        this.removeTyping();

                        if (!res.ok) {
                            const message = data.errors ?
                                Object.values(data.errors).flat().join(' ') :
                                (data.message || 'Terjadi kesalahan.');
                            this.appendBubble('assistant', message);
                            return;
                        }

                        this.appendBubble('assistant', data.reply);
                    } catch (e) {
                        this.removeTyping();
                        this.appendBubble('assistant', 'Koneksi gagal. Coba lagi.');
                    } finally {
                        this.sending = false;
                    }
                },
                appendBubble(role, text, typing = false) {
                    this.$refs.empty?.remove();

                    const wrap = document.createElement('div');
                    wrap.className = role === 'user' ?
                        'flex justify-end items-start gap-2.5' :
                        'flex justify-start items-start gap-2.5';

                    if (typing) wrap.dataset.typing = '1';

                    const avatar = document.createElement('div');
                    avatar.className = role === 'user' ?
                        'shrink-0 w-8 h-8 rounded-full bg-gradient-to-br from-[#071b3a] to-[#0b2a55] ring-1 ring-[#c99a3e]/40 flex items-center justify-center text-white font-bold text-xs' :
                        'shrink-0 w-8 h-8 rounded-full bg-gradient-to-br from-[#c99a3e] to-[#e6c06a] flex items-center justify-center text-[#071b3a] font-bold text-xs';
                    avatar.textContent = role === 'user' ? userInitial : 'V';

                    const bubble = document.createElement('div');
                    bubble.className = 'min-w-0 max-w-[80%] rounded-2xl ' + (role === 'user' ?
                            'rounded-tr-md bg-navy-gradient text-white' :
                            'rounded-tl-md bg-[#f6f8fb] ring-1 ring-[#e7eaf0] text-[#071833]') +
                        ' px-4 py-3 text-sm leading-relaxed whitespace-pre-line break-words';
                    bubble.textContent = text;

                    if (role === 'user') {
                        wrap.appendChild(bubble);
                        wrap.appendChild(avatar);
                    } else {
                        wrap.appendChild(avatar);
                        wrap.appendChild(bubble);
                    }

                    this.$refs.messages.appendChild(wrap);
                    this.$nextTick(() => this.scrollBottom());
                },
                removeTyping() {
                    this.$refs.messages.querySelector('[data-typing]')?.remove();
                },
                scrollBottom() {
                    if (this.$refs.messages) {
                        this.$refs.messages.scrollTop = this.$refs.messages.scrollHeight;
                    }
                },
            };
        }

        function regulationParseProgress(url, shouldStart) {
            return {
                display: {{ $regulation->parse_progress ?? 0 }},
                main: {
                    progress: null,
                    status: '',
                    parsedAt: null
                },
                docs: [],
                timer: null,
                started: false,
                start() {
                    if (!shouldStart || this.started) return;
                    this.started = true;
                    this.poll();
                    this.timer = setInterval(() => this.poll(), 2000);
                },
                docProgress(id) {
                    const found = this.docs.find((d) => d.id === id);
                    return found ? found.progress : null;
                },
                async poll() {
                    try {
                        const res = await fetch(url);
                        const data = await res.json();
                        this.main = data.regulation;
                        this.docs = data.documents || [];
                        const stillParsing = this.main.status === 'parsing' ||
                            (data.documents || []).some((d) => d.status === 'parsing');
                        if (!stillParsing) {
                            this.display = 100;
                            clearInterval(this.timer);
                            location.reload();
                            return;
                        }
                        const real = this.main.progress ?? 0;
                        if (real > this.display) {
                            this.display = real;
                        } else if (real === 0) {
                            this.display = Math.min(85, this.display + 4);
                        }
                    } catch (e) {}
                },
            };
        }
    </script>
@endpush
