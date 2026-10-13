@props(['sectors', 'selected' => [], 'disabled' => false, 'emptyLabel' => 'Belum ada sektor dipilih'])

@php
    $options = $sectors->map(fn ($sector) => ['id' => (string) $sector->id, 'name' => $sector->name])->values()->all();
    $initialSelected = array_values((array) $selected);
@endphp

<div x-data="sectorSelector(@js($options), @js($initialSelected))" {{ $attributes->class(['min-w-0 rounded-xl border border-[#e7eaf0] bg-white overflow-hidden']) }}>
    <div class="p-3 border-b border-[#e7eaf0] space-y-3" x-cloak>
        <input type="search" x-model.debounce.150ms="query" class="input-premium" placeholder="Cari nama sektor..." aria-label="Cari sektor yang diparse" @disabled($disabled)>
        <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
            <span class="font-semibold text-[#071833]" role="status" aria-live="polite" x-text="selected.length + ' dari {{ count($options) }} sektor dipilih'"></span>
            <div class="flex flex-wrap gap-3">
                <button type="button" class="font-semibold text-[#071833] hover:underline disabled:opacity-50" @click="selectVisible()" :disabled="@js((bool) $disabled) || filteredOptions.length === 0" @disabled($disabled)>Pilih semua hasil</button>
                <button type="button" class="text-[#667085] hover:underline disabled:opacity-50" @click="clear()" :disabled="@js((bool) $disabled) || selected.length === 0" @disabled($disabled)>Kosongkan</button>
            </div>
        </div>
        <div class="flex flex-wrap gap-1.5" x-show="selected.length > 0">
            <template x-for="sector in selectedOptions.slice(0, 3)" :key="sector.id">
                <span class="max-w-full truncate rounded-full bg-[#f6f8fb] px-2.5 py-1 text-xs text-[#071833]" :title="sector.name" x-text="sector.name"></span>
            </template>
            <span x-show="selected.length > 3" class="rounded-full bg-[#f6f8fb] px-2.5 py-1 text-xs text-[#667085]" x-text="'+' + (selected.length - 3) + ' lainnya'"></span>
        </div>
    </div>
    <div class="h-48 overflow-y-auto overscroll-contain p-3">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-1">
            @foreach ($sectors as $sector)
                <label class="flex min-w-0 items-center gap-2 rounded-lg px-2 py-2 text-sm hover:bg-[#f6f8fb]" x-show="isVisible(@js((string) $sector->id))" title="{{ $sector->name }}">
                    <input type="checkbox" name="sector_ids[]" value="{{ $sector->id }}" x-model="selected" @checked(in_array($sector->id, $initialSelected)) @disabled($disabled) class="shrink-0">
                    <span class="min-w-0 break-words">{{ $sector->name }}</span>
                </label>
            @endforeach
        </div>
        <p x-show="filteredOptions.length === 0" x-cloak class="py-6 text-center text-sm text-[#667085]">Tidak ada sektor yang sesuai pencarian.</p>
        @if ($sectors->isEmpty())<p class="py-6 text-center text-sm text-[#667085]">Belum ada sektor aktif.</p>@endif
    </div>
    <p class="border-t border-[#e7eaf0] px-3 py-2 text-xs text-[#667085]" x-show="selected.length === 0">{{ $emptyLabel }}</p>
</div>
