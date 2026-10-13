@props(['sectors', 'selected' => ''])

@php
    $options = $sectors->map(fn ($sector) => ['id' => (string) $sector->id, 'name' => $sector->name])->values()->all();
@endphp

<div class="relative min-w-0" x-data="sectorFilter(@js($options), @js((string) $selected))" @click.outside="open = false" @keydown.escape.stop="open = false">
    <input type="hidden" name="sector_id" :value="selectedId" value="{{ $selected }}">
    <div class="relative">
        <input type="text" id="sector-filter-search" x-ref="searchInput" :value="query" @input="edit($event.target.value)" @input.debounce.300ms="search($event.target.value)" @focus="focus()"
            @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter="if (open) { $event.preventDefault(); chooseActive(); }" @keydown.tab="open = false"
            class="input-premium pr-10" placeholder="Ketik untuk cari sektor..." autocomplete="off" role="combobox" aria-label="Cari dan pilih sektor"
            aria-autocomplete="list" aria-controls="sector-filter-options" :aria-expanded="open" :aria-activedescendant="open && activeIndex >= 0 ? 'sector-filter-option-' + activeIndex : null">
        <button type="button" x-show="query !== ''" x-cloak @click="clear(); $refs.searchInput?.focus()" class="absolute right-3 top-1/2 -translate-y-1/2 text-[#667085] hover:text-[#071833]" aria-label="Hapus filter sektor">✕</button>
    </div>
    <div x-show="open" x-cloak class="absolute left-0 right-0 z-30 mt-1 rounded-xl border border-[#e7eaf0] bg-white shadow-xl overflow-hidden">
        <button type="button" @click="clear()" class="w-full px-3 py-2.5 text-left text-sm font-semibold text-[#071833] hover:bg-[#f6f8fb] border-b border-[#e7eaf0]">Semua Sektor</button>
        <div class="max-h-60 overflow-y-auto" id="sector-filter-options" role="listbox" aria-label="Hasil pencarian sektor" :aria-busy="searching">
            <template x-if="!searching">
                <div>
                    <template x-for="(sector, index) in matches" :key="sector.id">
                        <button type="button" :id="'sector-filter-option-' + index" role="option" :aria-selected="selectedId === sector.id" @click="choose(sector)"
                            @mouseenter="activeIndex = index" class="block w-full px-3 py-2.5 text-left text-sm break-words hover:bg-[#f6f8fb]" :class="activeIndex === index ? 'bg-[#f6f8fb] text-[#071833]' : 'text-[#667085]'" x-text="sector.name"></button>
                    </template>
                </div>
            </template>
            <p x-show="searching" class="px-3 py-3 text-xs text-[#667085]" role="status">Mencari sektor...</p>
            <p x-show="!searching && matches.length === 0" class="px-3 py-3 text-sm text-[#667085]" role="status">Tidak ada sektor yang cocok.</p>
        </div>
    </div>
    <p class="mt-1 text-xs text-[#667085]">Pilih nama sektor, lalu klik Cari.</p>
</div>
