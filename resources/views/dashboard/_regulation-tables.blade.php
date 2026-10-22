{{-- Peraturan Terkini + Peraturan Terkait, shared by dashboard and landing page. Expects $filterRoute; $aiSearchRoute is optional. --}}
@php
    $showAiResults = isset($aiSearchRoute, $aiQuery);
    $rows = $showAiResults
        ? $aiResults
        : $latestRegulations->map(fn ($reg) => ['regulation' => $reg, 'reason' => null]);
@endphp

{{-- Peraturan Terkini --}}
<x-card :padding="false" class="mt-7" id="peraturan-terkini">
    <x-slot name="header">
        <div class="flex flex-wrap gap-3 justify-between items-center">
            <div>
                <h3 class="text-lg font-bold text-[#071833]">Peraturan Terkini</h3>
                <p class="text-xs text-[#667085] mt-0.5">
                    @if ($showAiResults)
                        Hasil pencarian AI untuk "<span class="font-semibold text-[#071833]">{{ $aiQuery }}</span>"
                        · <a href="{{ route($filterRoute) }}#peraturan-terkini" class="text-[#c99a3e] hover:underline">Reset</a>
                    @elseif (array_filter($regulationFilters))
                        Hasil filter regulasi
                        · <a href="{{ route($filterRoute) }}#peraturan-terkini" class="text-[#c99a3e] hover:underline">Reset</a>
                    @else
                        5 regulasi terbaru yang diundangkan
                    @endif
                </p>
            </div>
            <x-button href="{{ route('regulations.index') }}" variant="outline" size="sm">
                Semua Regulasi
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
            </x-button>
        </div>
    </x-slot>

    <form method="GET" action="{{ route($filterRoute) }}#peraturan-terkini" class="p-5 space-y-3 border-b border-[#e7eaf0]">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            <input type="text" name="search" value="{{ $regulationFilters['search'] ?? '' }}"
                class="input-premium lg:col-span-2" placeholder="Cari nomor atau judul regulasi...">
            <select name="category_keyword" class="select-premium" aria-label="Kategori">
                <option value="">Semua Kategori</option>
                @foreach ($filterOptions['categories']->pluck('name')->unique() as $categoryName)
                    <option value="{{ $categoryName }}" @selected(($regulationFilters['category_keyword'] ?? '') === $categoryName)>{{ $categoryName }}</option>
                @endforeach
            </select>
            <select name="year" class="select-premium" aria-label="Tahun">
                <option value="">Semua Tahun</option>
                @foreach ($filterOptions['years'] as $year)
                    <option value="{{ $year }}" @selected(($regulationFilters['year'] ?? '') == $year)>{{ $year }}</option>
                @endforeach
            </select>
            <select name="type_id" class="select-premium" aria-label="Jenis">
                <option value="">Semua Jenis</option>
                @foreach ($filterOptions['types'] as $type)
                    <option value="{{ $type->id }}" @selected(($regulationFilters['type_id'] ?? '') == $type->id)>{{ $type->name }}</option>
                @endforeach
            </select>
            <x-button type="submit" variant="primary" size="md" class="h-12">Cari</x-button>
        </div>
        <div>
            <label for="dashboard_search_content" class="block text-xs font-semibold text-[#071833] mb-1.5">Cari dalam Dokumen</label>
            <input type="text" name="search_content" id="dashboard_search_content"
                value="{{ $regulationFilters['search_content'] ?? '' }}" class="input-premium"
                placeholder="Cari kata dalam isi dokumen regulasi... (gunakan &quot;kata&quot; untuk kata utuh)">
        </div>
    </form>

    @isset($aiSearchRoute)
        <form method="GET" action="{{ route($aiSearchRoute) }}#peraturan-terkini" class="px-5 py-4 flex gap-2 border-b border-[#e7eaf0]">
            <input type="search" name="q" value="{{ $aiQuery ?? '' }}" required minlength="2" maxlength="500"
                placeholder="Tanya AI: regulasi apa yang mengatur…"
                class="flex-1 h-10 px-3 rounded-xl bg-white border border-[#e7eaf0] text-sm text-[#071833] placeholder:text-[#667085] focus:outline-none focus:border-[#c99a3e]/60 focus:ring-4 focus:ring-[#c99a3e]/15 transition">
            <button type="submit"
                class="inline-flex items-center px-4 h-10 rounded-xl text-xs font-bold text-[#071b3a] bg-gradient-to-r from-[#c99a3e] to-[#e6c06a] hover:brightness-110 transition">
                Cari AI
            </button>
        </form>
    @endisset

    <div class="overflow-x-auto">
        <table class="table-premium">
            <thead>
                <tr>
                    <th class="text-left">No. Regulasi</th>
                    <th class="text-left">Judul</th>
                    <th class="text-center">Jenis</th>
                    <th class="text-center">Kategori</th>
                    <th class="text-center">Tahun</th>
                    @if ($showAiResults)
                        <th class="text-left">Alasan Relevansi</th>
                    @endif
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @if ($showAiResults && !empty($aiError))
                    <tr>
                        <td colspan="7" class="text-sm text-rose-600">{{ $aiError }}</td>
                    </tr>
                @elseif ($rows->isEmpty())
                    <tr>
                        <td colspan="7" class="text-sm text-[#667085]">Tidak ada regulasi yang ditemukan.</td>
                    </tr>
                @endif
                @foreach ($rows as ['regulation' => $reg, 'reason' => $reason])
                    <tr>
                        <td>
                            <a href="{{ route('regulations.show', $reg) }}"
                                class="font-semibold text-[#071833] hover:text-[#c99a3e] transition">{{ $reg->regulation_number }}</a>
                        </td>
                        <td>
                            <a href="{{ route('regulations.show', $reg) }}"
                                class="text-sm font-medium text-[#071833] hover:text-[#c99a3e] transition line-clamp-2">{{ $reg->title }}</a>
                        </td>
                        <td class="text-center">
                            @if ($reg->type)
                                <x-badge :color="$reg->type->levelBadgeColor()">{{ $reg->type->name }}</x-badge>
                            @else
                                <span class="text-xs text-[#667085]">-</span>
                            @endif
                        </td>
                        <td class="text-center text-sm text-[#667085]">{{ $reg->category?->name ?? '-' }}</td>
                        <td class="text-center">
                            <span class="font-semibold text-[#071833]">{{ $reg->year }}</span>
                        </td>
                        @if ($showAiResults)
                            <td class="text-sm text-[#667085]">{{ $reason ?: '-' }}</td>
                        @endif
                        <td class="text-right whitespace-nowrap">
                            <a href="{{ route($filterRoute, [...$regulationFilters, 'related' => $reg->id]) }}#peraturan-terkait"
                                class="inline-flex items-center gap-1.5 px-3 h-9 rounded-xl text-xs font-semibold text-[#071833] bg-[#f6f8fb] ring-1 ring-[#e7eaf0] hover:bg-white hover:ring-[#c99a3e]/40 transition">
                                Terkait
                            </a>
                            <a href="{{ route('regulations.show', $reg) }}"
                                class="inline-flex items-center gap-1.5 px-3 h-9 rounded-xl text-xs font-semibold text-[#071833] bg-[#f6f8fb] ring-1 ring-[#e7eaf0] hover:bg-white hover:ring-[#c99a3e]/40 transition">
                                Detail
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-card>

{{-- Peraturan Terkait: no search form here on purpose; rows are picked with the "Terkait" button above. --}}
@if ($regulationRelated->isNotEmpty() || $relatedRegulation)
    <x-card :padding="false" class="mt-6" id="peraturan-terkait">
        <x-slot name="header">
            <div>
                <h3 class="text-lg font-bold text-[#071833]">Peraturan Terkait</h3>
                <p class="text-xs text-[#667085] mt-0.5">
                    @if ($relatedRegulation)
                        Peraturan terkait {{ $relatedRegulation->regulation_number }}
                        · <a href="{{ route($filterRoute, $regulationFilters) }}#peraturan-terkait" class="text-[#c99a3e] hover:underline">Tampilkan semua</a>
                    @else
                        5 peraturan terkait terbaru berdasarkan data linkage
                    @endif
                </p>
            </div>
        </x-slot>

        <div class="overflow-x-auto">
            <table class="table-premium">
                <thead>
                    <tr>
                        <th class="text-left">Sumber Regulasi</th>
                        <th class="text-left">Nama Terkait</th>
                        <th class="text-center">Nomor</th>
                        <th class="text-center">Tahun</th>
                        <th class="text-center">Hubungan</th>
                    </tr>
                </thead>
                <tbody>
                    @if ($regulationRelated->isEmpty())
                        <tr>
                            <td colspan="5" class="text-sm text-[#667085]">Belum ada data peraturan terkait.</td>
                        </tr>
                    @endif
                    @foreach ($regulationRelated as $ref)
                        <tr>
                            <td>
                                <a href="{{ route('regulations.show', $ref->regulation) }}"
                                    class="font-semibold text-[#071833] hover:text-[#c99a3e] transition">{{ $ref->regulation->regulation_number }}</a>
                            </td>
                            <td class="text-sm font-medium text-[#071833]">{{ $ref->name }}</td>
                            <td class="text-center text-sm text-[#667085]">{{ $ref->number ?? '-' }}</td>
                            <td class="text-center">
                                <span class="font-semibold text-[#071833]">{{ $ref->year ?? '-' }}</span>
                            </td>
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
    </x-card>
@endif
