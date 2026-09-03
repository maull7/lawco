@extends('layouts.app')

@section('title', 'Daftar Regulasi')
@section('header', 'Daftar Regulasi')

@section('content')
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
        <div>
            <p class="text-xs font-semibold tracking-[0.16em] uppercase text-[#c99a3e]">Manajemen Regulasi</p>
            <h2 class="mt-2 text-3xl font-bold text-[#071833] tracking-tight">Daftar Regulasi</h2>
            <p class="mt-1.5 text-sm text-[#667085]">Kelola seluruh regulasi dengan metadata lengkap untuk analisis
                kepatuhan.</p>
        </div>
        @if (auth()->user()->hasPermission('upload_regulations'))
            <x-button href="{{ route('regulations.create') }}" variant="primary">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Tambah Regulasi
            </x-button>
        @endif
    </div>

    {{-- Filters --}}
    <x-card class="mt-6">
        <form method="GET" action="{{ route('regulations.index') }}" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="lg:col-span-2">
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="input-premium"
                        placeholder="Cari nomor atau judul regulasi...">
                </div>
                <select name="sector_id" class="select-premium">
                    <option value="">Semua Sektor</option>
                    @foreach ($filterOptions['sectors'] as $type)
                        <option value="{{ $type->id }}"
                            {{ ($filters['sector_id'] ?? '') == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                    @endforeach
                </select>
                <select name="category_id" class="select-premium">
                    <option value="">Semua Kategori</option>
                    @foreach ($filterOptions['categories'] as $category)
                        <option value="{{ $category->id }}"
                            {{ ($filters['category_id'] ?? '') == $category->id ? 'selected' : '' }}>{{ $category->name }}
                        </option>
                    @endforeach
                </select>
                <select name="year" class="select-premium">
                    <option value="">Semua Tahun</option>
                    @foreach ($filterOptions['years'] as $year)
                        <option value="{{ $year }}" {{ ($filters['year'] ?? '') == $year ? 'selected' : '' }}>
                            {{ $year }}</option>
                    @endforeach
                </select>

                <select name="type_id" class="select-premium">
                    <option value="">Semua Jenis</option>
                    @foreach ($filterOptions['types'] as $type)
                        <option value="{{ $type->id }}"
                            {{ ($filters['type_id'] ?? '') == $type->id ? 'selected' : '' }}>{{ $type->name }}</option>
                    @endforeach
                </select>

                <div class="flex gap-2">
                    <x-button type="submit" variant="primary" size="md">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        Cari
                    </x-button>
                </div>
            </div>
            <div class="border-t border-[#e7eaf0] pt-4">
                <label for="search_content" class="block text-sm font-semibold text-[#071833] mb-2">Cari dalam Isi
                    Dokumen</label>
                <input type="text" name="search_content" id="search_content"
                    value="{{ $filters['search_content'] ?? '' }}" class="input-premium"
                    placeholder="Cari kata dalam isi dokumen regulasi... (gunakan &quot;kata&quot; untuk kata utuh)">
            </div>
        </form>
    </x-card>

    {{-- Table --}}
    <x-card :padding="false" class="mt-6" x-data="{ docModal: null, docs: [], parseModal: null, parseData: null }">
        @if ($regulations->isEmpty())
            <div class="text-center py-14">
                <div class="mx-auto w-16 h-16 rounded-2xl bg-[#f6f8fb] flex items-center justify-center text-[#c99a3e]">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                </div>
                <p class="mt-4 text-base font-bold text-[#071833]">Belum ada regulasi</p>
                <p class="mt-1 text-sm text-[#667085]">Tambahkan regulasi pertama Anda untuk memulai pengelolaan.</p>
                @if (auth()->user()->hasPermission('upload_regulations'))
                    <x-button href="{{ route('regulations.create') }}" variant="primary" size="sm"
                        class="mt-5">Tambah
                        Regulasi</x-button>
                @endif
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="table-premium">
                    <thead>
                        <tr>
                            <th>
                                <x-sortable-link :filters="$filters" field="regulation_number" label="No. Regulasi" />
                            </th>
                            <th>
                                <x-sortable-link :filters="$filters" field="title" label="Judul" />
                            </th>
                            @if (!empty($filters['search_content']))
                                <th class="text-center">Jumlah Temuan</th>
                            @endif
                            <th>
                                <x-sortable-link :filters="$filters" field="regulation_type_id" label="Jenis" />
                            </th>
                            <th>
                                <x-sortable-link :filters="$filters" field="category_id" label="Kategori" />
                            </th>
                            <th>
                                <x-sortable-link :filters="$filters" field="year" label="Tahun" />
                            </th>
                            <th class="text-center">Dok Tambahan</th>
                            <th class="text-center">Status Parser</th>

                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($regulations as $reg)
                            <tr>
                                <td>
                                    <a href="{{ route('regulations.show', $reg) }}"
                                        class="font-semibold text-[#071833] hover:text-[#c99a3e] transition">{{ $reg->regulation_number }}</a>
                                </td>
                                <td>
                                    <div>
                                        <a href="{{ route('regulations.file', $reg) }}" target="_blank"
                                            title="{{ $reg->title }}"
                                            class="text-sm font-medium text-[#071833] hover:text-[#c99a3e] transition">
                                            {{ Str::limit($reg->title, 60) }}
                                        </a>
                                        @if (!empty($filters['search_content']))
                                            @php
                                                $repo = app(\App\Repositories\RegulationRepository::class);
                                                $hlRaw = $filters['search_content'];
                                                $hlExact = str_starts_with($hlRaw, '"') && str_ends_with($hlRaw, '"');
                                                $hlTerm = $hlExact
                                                    ? preg_replace('/\s+/u', ' ', trim(mb_substr($hlRaw, 1, -1)))
                                                    : $hlRaw;
                                                $hlPattern = $hlExact
                                                    ? '/\b(' . preg_quote($hlTerm, '/') . ')\b/iu'
                                                    : '/((' . preg_quote($hlRaw, '/') . '))/iu';
                                                $matches = function (?string $text) use ($hlExact, $hlTerm, $hlRaw) {
                                                    if (!$text) {
                                                        return false;
                                                    }
                                                    return $hlExact
                                                        ? preg_match(
                                                                '/\b' . preg_quote($hlTerm, '/') . '\b/iu',
                                                                $text,
                                                            ) === 1
                                                        : mb_stripos($text, $hlRaw) !== false;
                                                };
                                                $matchDoc = $reg->documents->first(fn($d) => $matches($d->parsed_text));
                                                $snippet = null;
                                                $snippetLabel = null;
                                                if ($matches($reg->parsed_text)) {
                                                    $snippet = $repo->buildSnippet($reg->parsed_text, $hlRaw, 250);
                                                    $snippetLabel = $reg->title;
                                                } elseif ($matchDoc) {
                                                    $snippet = $repo->buildSnippet($matchDoc->parsed_text, $hlRaw, 250);
                                                    $snippetLabel = $matchDoc->name;
                                                }
                                                $visibleDocs = $reg->documents->filter(
                                                    fn($d) => $matches($d->parsed_text),
                                                );
                                            @endphp
                                            @if ($snippet)
                                                <div class="mt-2 p-3 bg-yellow-50 border-l-4 border-yellow-400 rounded">
                                                    <div class="flex items-start gap-2">
                                                        <svg class="w-4 h-4 text-yellow-600 mt-0.5 shrink-0"
                                                            fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                                            stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                                        </svg>
                                                        <div class="min-w-0 text-xs text-[#854d0e] leading-relaxed">
                                                            <p
                                                                class="text-[10px] font-bold uppercase tracking-wider text-yellow-700 mb-0.5">
                                                                {{ $snippetLabel }}</p>
                                                            {!! preg_replace(
                                                                $hlPattern,
                                                                '<mark class="bg-yellow-300 text-[#071833] font-semibold px-1 py-0.5 rounded">$1</mark>',
                                                                e($snippet),
                                                            ) !!}
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                </td>

                                @if (!empty($filters['search_content']))
                                    <td class="text-center">
                                        <span
                                            class="font-semibold text-[#071833]">{{ $reg->searchOccurrenceCount($filters['search_content']) }}</span>
                                    </td>
                                @endif
                                <td>
                                    @if ($reg->type)
                                        <x-badge :color="$reg->type->levelBadgeColor()">{{ $reg->type->name }}</x-badge>
                                    @endif
                                </td>
                                <td>
                                    <span class="text-sm text-[#667085]">{{ $reg->category?->name }}</span>
                                </td>
                                <td>
                                    <span class="font-semibold text-[#071833]">{{ $reg->year }}</span>
                                </td>
                                <td class="text-center">
                                    @php
                                        $docCount = !empty($filters['search_content'])
                                            ? $visibleDocs->count()
                                            : $reg->documents->count();
                                        $modalDocs = !empty($filters['search_content'])
                                            ? $visibleDocs->map(
                                                fn($d) => [
                                                    'id' => $d->id,
                                                    'name' => $d->name,
                                                    'type' => $d->document_type,
                                                ],
                                            )
                                            : $reg->documents->map(
                                                fn($d) => [
                                                    'id' => $d->id,
                                                    'name' => $d->name,
                                                    'type' => $d->document_type,
                                                ],
                                            );
                                    @endphp
                                    @if ($docCount > 0)
                                        <button type="button"
                                            @click="docModal = {{ $reg->id }}; docs = {{ Js::from($modalDocs) }}"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-sky-100 text-sky-700 hover:bg-sky-200 transition cursor-pointer">
                                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                            </svg>
                                            {{ $docCount }}
                                        </button>
                                    @else
                                        <span class="text-xs text-[#b0b8c5]">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @php
                                        $status = $reg->parseStatusLabel();
                                        $color = $reg->parseStatusBadgeColor();
                                    @endphp
                                    <x-badge :color="$color">{{ $status }}</x-badge>
                                </td>

                                <td>
                                    <div class="flex items-center justify-end gap-2">
                                        <x-button href="{{ route('regulations.show', $reg) }}" variant="outline"
                                            size="sm">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            </svg>
                                            Detail
                                        </x-button>
                                        @if (auth()->user()->hasPermission('upload_regulations'))
                                            <x-button href="{{ route('regulations.edit', $reg) }}" variant="outline"
                                                size="sm">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M16.862 4.487 18.55 2.8a2.121 2.121 0 1 1 3 3L19.863 7.487m-3-3L8.25 13.1l-1.5 4.5 4.5-1.5 8.613-8.613m-3-3 3 3" />
                                                </svg>
                                                Edit
                                            </x-button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="px-6 py-4 border-t border-[#e7eaf0]">
                {{ $regulations->links() }}
            </div>
        @endif

        {{-- Dok Tambahan Modal --}}
        <div x-show="docModal" x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center bg-[#071b3a]/60 backdrop-blur-sm overflow-hidden"
            @click.self="docModal = null">
            <div class="bg-white rounded-2xl shadow-2xl p-6 max-w-lg w-full mx-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-bold text-[#071833]">Dokumen Tambahan</h3>
                    <button type="button" @click="docModal = null"
                        class="p-1.5 rounded-lg text-[#667085] hover:bg-[#f6f8fb] transition">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <template x-if="docs.length === 0">
                    <p class="text-sm text-[#667085] py-4 text-center">Belum ada dokumen tambahan.</p>
                </template>
                <template x-if="docs.length > 0">
                    <ul class="divide-y divide-[#e7eaf0]">
                        <template x-for="doc in docs" :key="doc.id">
                            <li class="flex items-center gap-3 py-3">
                                <div
                                    class="shrink-0 w-9 h-9 rounded-lg bg-sky-50 text-sky-500 flex items-center justify-center">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                        <path
                                            d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zM14 3.5L18.5 8H14V3.5zM6 20V4h7v5h5v11H6z" />
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-[#071833]" x-text="doc.name"></p>
                                    <p class="text-xs text-[#667085]" x-text="doc.type"></p>
                                </div>
                            </li>
                        </template>
                    </ul>
                </template>
                <div class="mt-4 pt-3 border-t border-[#e7eaf0] flex justify-end">
                    <x-button type="button" variant="outline" size="sm" @click="docModal = null">Tutup</x-button>
                </div>
            </div>
        </div>
    </x-card>
@endsection
