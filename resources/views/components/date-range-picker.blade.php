@props([
    'fromName',
    'toName',
    'from' => null,
    'to' => null,
    'today' => null,
    'placeholder' => 'Pilih tanggal',
    'class' => '',
])

@php
    $today ??= now()->format('Y-m-d');
    $displayDate = fn (string $date): string => \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $date)->format('d/m/Y');
    $initialLabel = ! $from ? $placeholder : ($from === $to || ! $to ? $displayDate($from) : $displayDate($from).' – '.$displayDate($to));
@endphp

{{-- Date range picker: klik tanggal awal lalu tanggal akhir, lalu form induk otomatis disubmit. --}}
<input type="hidden" name="{{ $fromName }}" value="{{ $from }}">
<input type="hidden" name="{{ $toName }}" value="{{ $to }}">
<div class="relative {{ $class }}" x-data="dateRangePicker(@js($from), @js($to), @js($today), @js($fromName), @js($toName), @js($placeholder))" @click.outside="open = false" @keydown.escape.window="open = false">
    <button type="button" @click="open = !open" :aria-expanded="open" aria-haspopup="dialog"
        class="flex h-8 w-52 items-center justify-between gap-1.5 rounded-lg border border-[#e7eaf0] bg-white px-2.5 py-1 text-xs text-[#071833] focus:border-[#c99a3e] focus:outline-none focus:ring-2 focus:ring-[#c99a3e]/20 transition-colors">
        <span x-text="label()" class="truncate text-center flex-1 tabular-nums">{{ $initialLabel }}</span>
        <svg class="h-3.5 w-3.5 shrink-0 text-[#667085]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
    </button>
    <div x-show="open" x-cloak x-transition role="dialog" aria-label="Pilih rentang tanggal"
        class="absolute right-0 z-30 mt-2 w-64 rounded-xl border border-[#e7eaf0] bg-white p-3 shadow-lg">
        <div class="mb-2 flex items-center justify-between">
            <button type="button" @click="shiftMonth(-1)" class="rounded-md px-2 py-1 text-[#667085] hover:bg-[#f6f8fb]" aria-label="Bulan sebelumnya">&lsaquo;</button>
            <span class="text-xs font-semibold text-[#071833]" x-text="monthLabel()"></span>
            <button type="button" @click="shiftMonth(1)" :disabled="isCurrentMonth()" class="rounded-md px-2 py-1 text-[#667085] hover:bg-[#f6f8fb] disabled:opacity-30" aria-label="Bulan berikutnya">&rsaquo;</button>
        </div>
        <div class="grid grid-cols-7 text-center text-[10px] font-semibold text-[#98a2b3]">
            <template x-for="day in ['Sn','Sl','Rb','Km','Jm','Sb','Mg']"><span x-text="day"></span></template>
        </div>
        <div class="mt-1 grid grid-cols-7 gap-y-1 text-center text-xs">
            <template x-for="(cell, index) in cells()" :key="cell.key">
                <div :class="bandClass(cell.date, index)" class="flex h-8 items-center justify-center">
                    <button type="button" x-text="cell.day" :disabled="!cell.date || cell.date > today" @click="pick(cell.date)" @mouseenter="hover = cell.date"
                        :aria-pressed="inRange(cell.date)"
                        :class="{
                            'invisible': !cell.date,
                            'text-[#d0d5dd] cursor-not-allowed': cell.date && cell.date > today,
                            'bg-blue-600 text-white font-semibold': isEdge(cell.date),
                            'text-blue-900': inRange(cell.date) && !isEdge(cell.date),
                            'hover:bg-blue-50': cell.date && cell.date <= today && !inRange(cell.date),
                            'ring-1 ring-blue-600': cell.date === today && !isEdge(cell.date),
                        }"
                        class="h-7 w-7 rounded-full"></button>
                </div>
            </template>
        </div>
        <p class="mt-2 text-[11px] text-[#667085]" x-text="start && !end ? 'Pilih tanggal akhir (atau klik tanggal yang sama untuk 1 hari)' : 'Klik tanggal awal lalu tanggal akhir'"></p>
    </div>
</div>

@once
    @push('scripts')
        <script>
            function dateRangePicker(from, to, today, fromName, toName, placeholder) {
                const pad = (n) => String(n).padStart(2, '0');
                const toDisplay = (date) => date.split('-').reverse().join('/');
                const initialView = to || from || today;

                return {
                    open: false,
                    start: from || null,
                    end: from ? (to || from) : null,
                    hover: null,
                    today,
                    view: new Date(Number(initialView.slice(0, 4)), Number(initialView.slice(5, 7)) - 1, 1),
                    label() {
                        if (!this.start) {
                            return placeholder;
                        }

                        return this.start === this.end || !this.end ? toDisplay(this.start) : `${toDisplay(this.start)} – ${toDisplay(this.end)}`;
                    },
                    monthLabel() {
                        return this.view.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
                    },
                    isCurrentMonth() {
                        return `${this.view.getFullYear()}-${pad(this.view.getMonth() + 1)}` === this.today.slice(0, 7);
                    },
                    shiftMonth(step) {
                        this.view = new Date(this.view.getFullYear(), this.view.getMonth() + step, 1);
                    },
                    cells() {
                        const year = this.view.getFullYear();
                        const month = this.view.getMonth();
                        const blanks = (new Date(year, month, 1).getDay() + 6) % 7;
                        const days = new Date(year, month + 1, 0).getDate();
                        const cells = Array.from({ length: blanks }, (_, i) => ({ key: `b${i}`, day: '', date: null }));
                        for (let day = 1; day <= days; day++) {
                            cells.push({ key: `d${day}`, day, date: `${year}-${pad(month + 1)}-${pad(day)}` });
                        }

                        return cells;
                    },
                    bounds() {
                        if (!this.start) {
                            return [null, null];
                        }
                        const last = this.end ?? this.hover ?? this.start;

                        return this.start <= last ? [this.start, last] : [last, this.start];
                    },
                    inRange(date) {
                        const [low, high] = this.bounds();

                        return !!date && !!low && date >= low && date <= high;
                    },
                    bandClass(date, index) {
                        const [low, high] = this.bounds();
                        if (!this.inRange(date) || low === high) {
                            return '';
                        }
                        const column = index % 7;
                        const roundLeft = date === low || column === 0 ? ' rounded-l-full' : '';
                        const roundRight = date === high || column === 6 ? ' rounded-r-full' : '';

                        return 'bg-blue-100' + roundLeft + roundRight;
                    },
                    isEdge(date) {
                        const [low, high] = this.bounds();

                        return !!date && !!low && (date === low || date === high);
                    },
                    pick(date) {
                        if (!this.start || this.end !== null) {
                            this.start = date;
                            this.end = null;

                            return;
                        }
                        [this.start, this.end] = this.start <= date ? [this.start, date] : [date, this.start];
                        const form = this.$el.closest('form');
                        form.querySelector(`[name="${fromName}"]`).value = this.start;
                        form.querySelector(`[name="${toName}"]`).value = this.end;
                        form.submit();
                    },
                };
            }
        </script>
    @endpush
@endonce
