@php
    $pickerOpen = $categories
        ->filter(fn ($category) => $category->regulations->isNotEmpty())
        ->mapWithKeys(fn ($category) => [$category->id => true])
        ->all();
    $showFilters = $showFilters ?? false;
    $filterCategories = $showFilters ? $categories->map(fn ($category) => [
        'id' => (string) $category->id,
        'name' => $category->name,
        'sectorId' => (string) $category->sector_id,
        'subCategories' => $category->subCategories->map(fn ($subCategory) => [
            'id' => (string) $subCategory->id,
            'name' => $subCategory->name,
        ])->values(),
        'regulations' => $category->regulations->map(fn ($regulation) => [
            'id' => (string) $regulation->id,
            'search' => mb_strtolower($regulation->regulation_number.' '.$regulation->title),
            'subCategoryIds' => $regulation->subCategories->pluck('id')->map(fn ($id) => (string) $id)->values(),
        ])->values(),
    ])->values() : [];
    $initialFilters = $showFilters ? [
        'sectorId' => old('filter_sector_id', ''),
        'categoryId' => old('filter_category_id', ''),
        'subCategoryId' => old('filter_sub_category_id', ''),
    ] : [];
@endphp
<div
    x-data="regulationPicker({{ Js::from($pickerOpen) }}, {{ Js::from($filterCategories) }}, {{ Js::from($initialFilters) }})"
>
    @if($showFilters)
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div>
                <label for="filter_sector_id" class="block text-sm font-semibold text-[#071833] mb-2">Sektor</label>
                <select id="filter_sector_id" name="filter_sector_id" class="select-premium" x-model="sectorId" @change="changeSector()">
                    <option value="">Semua Sektor</option>
                    @foreach($categories->pluck('sector')->filter()->unique('id')->sortBy('name') as $sector)
                        <option value="{{ $sector->id }}">{{ $sector->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filter_category_id" class="block text-sm font-semibold text-[#071833] mb-2">Kategori</label>
                <select id="filter_category_id" name="filter_category_id" class="select-premium" x-model="categoryId" @change="changeCategory()">
                    <option value="">Semua Kategori</option>
                    <template x-for="category in availableCategories" :key="category.id">
                        <option :value="category.id" x-text="category.name"></option>
                    </template>
                </select>
            </div>
            <div>
                <label for="filter_sub_category_id" class="block text-sm font-semibold text-[#071833] mb-2">Sub Kategori</label>
                <select id="filter_sub_category_id" name="filter_sub_category_id" class="select-premium" x-model="subCategoryId" @change="expandAll()">
                    <option value="">Semua Sub Kategori</option>
                    <template x-for="subCategory in availableSubCategories" :key="subCategory.id">
                        <option :value="subCategory.id" x-text="subCategory.name"></option>
                    </template>
                </select>
            </div>
        </div>
        <div class="flex items-center justify-between mb-2">
            <label class="block text-sm font-semibold text-[#071833]">Pilih Regulasi yang Berlaku <span class="text-[#c99a3e]">*</span></label>
            <span class="text-[11px] font-semibold text-[#667085]" aria-live="polite"><span x-text="visibleRegulations.length"></span> regulasi tersedia</span>
        </div>
        <p class="text-xs text-[#667085] mb-3">Filter hanya mengubah daftar yang ditampilkan. Regulasi yang sudah dicentang tetap dipilih.</p>
    @endif
    <input
        type="search"
        placeholder="Cari nomor atau judul regulasi…"
        @input.debounce.300ms="query = $event.target.value.toLowerCase()"
        class="input-premium mb-4"
    >

    <div class="rounded-2xl border border-[#e7eaf0] bg-[#f6f8fb]/40 p-4 max-h-96 overflow-y-auto space-y-3">
        @if($showFilters)
            <p x-show="visibleRegulations.length === 0" x-cloak class="text-center py-8 text-sm text-[#667085]" role="status">Tidak ada regulasi yang sesuai dengan filter atau pencarian.</p>
        @endif
        @forelse($categories as $category)
            @if($category->regulations->isNotEmpty())
                <div @if($showFilters) x-show="hasCategory('{{ $category->id }}')" @endif class="rounded-xl bg-white ring-1 ring-[#e7eaf0] overflow-hidden">
                    <button
                        type="button"
                        @click="open['{{ $category->id }}'] = !open['{{ $category->id }}']"
                        class="w-full flex items-center justify-between gap-3 p-3 cursor-pointer hover:bg-[#f6f8fb] transition text-left"
                    >
                        <span class="text-[11px] font-bold uppercase tracking-wider text-[#c99a3e]">
                            {{ $category->name }}
                            <span class="text-[#667085] normal-case font-semibold">({{ $category->regulations->count() }})</span>
                        </span>
                        <svg class="w-4 h-4 text-[#667085] shrink-0 transition-transform" :class="open['{{ $category->id }}'] ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                    </button>
                    <div x-show="open['{{ $category->id }}']" x-collapse>
                        <div class="px-3 pb-3 space-y-2">
                            @foreach($category->regulations as $regulation)
                                <label data-search="{{ strtolower($regulation->regulation_number.' '.$regulation->title) }}" x-show="{{ $showFilters ? "hasRegulation('".$regulation->id."')" : '!query || $el.dataset.search.includes(query)' }}" class="flex items-start gap-3 p-3 rounded-xl bg-white ring-1 ring-[#e7eaf0] hover:ring-[#c99a3e]/40 cursor-pointer transition">
                                    <input type="checkbox" name="regulation_ids[]" value="{{ $regulation->id }}" {{ in_array($regulation->id, $selectedIds) ? 'checked' : '' }} class="checkbox-premium mt-0.5">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-[#071833]">{{ $regulation->regulation_number }}</p>
                                        <p class="text-xs text-[#667085] mt-0.5 line-clamp-1">{{ $regulation->title }}</p>
                                        <div class="flex items-center gap-2 mt-1">
                                            @if($regulation->type)
                                                <x-badge :color="$regulation->type->levelBadgeColor()">Lv{{ $regulation->type->level }}</x-badge>
                                            @endif
                                            <span class="text-[10px] text-[#667085]">{{ $regulation->year }}</span>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        @empty
            <div @if($showFilters) x-show="false" @endif class="text-center py-8 text-sm text-[#667085]">Belum ada regulasi. Silakan tambahkan regulasi terlebih dahulu.</div>
        @endforelse
    </div>
</div>