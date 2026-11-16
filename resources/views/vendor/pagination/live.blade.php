@php
    $current = $paginator->currentPage();
    $hasNext = $hasNextPage ?? $paginator->hasMorePages();
    $maxPage = $hasNext ? $paginator->lastPage() : $current;
    $displayTotal = ! empty($realTotal) ? $realTotal : $paginator->total();
    $start = max(1, $current - 1);
    $end = $hasNext ? min($maxPage, $current + 1) : $current;
    $query = collect(request()->query())->except('page')->filter(fn ($value) => is_scalar($value));
    $base = 'inline-flex items-center justify-center h-7 text-xs rounded-md whitespace-nowrap border transition-colors';
    $link = $base.' font-medium text-slate-700 bg-white border-slate-200 hover:text-[#946d25] hover:bg-amber-50/40';
    $active = $base.' font-bold text-white bg-[#946d25] border-[#946d25] select-none';
    $disabled = $base.' font-medium text-slate-300 bg-slate-50 border-slate-200 cursor-not-allowed select-none';
@endphp

{{-- Pagination daftar live portal MA: jumlah halaman hanya perkiraan, jadi tanpa lompatan ke halaman terakhir
     dan tanpa halaman setelah halaman yang tidak penuh. --}}
<nav role="navigation" aria-label="Navigasi Halaman" class="flex items-center justify-between gap-3 w-full">
    <p class="shrink-0 text-xs text-slate-600 font-medium">
        Menampilkan
        <span class="font-bold text-[#071833]">{{ number_format($paginator->firstItem() ?? 0, 0, ',', '.') }}–{{ number_format($paginator->lastItem() ?? 0, 0, ',', '.') }}</span>
        dari <span class="font-bold text-[#946d25]">{{ number_format($displayTotal, 0, ',', '.') }}</span> putusan
    </p>

    <div class="flex shrink-0 items-center gap-1">
        @if ($paginator->onFirstPage())
            <span class="{{ $disabled }} px-2.5" aria-disabled="true">&lsaquo; Sebelumnya</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $link }} px-2.5">&lsaquo; Sebelumnya</a>
        @endif

        @if ($start > 1)
            <a href="{{ $paginator->url(1) }}" class="{{ $link }} min-w-7 px-2">1</a>
            @if ($start > 2)
                <span class="inline-flex items-center justify-center min-w-5 h-7 text-xs font-bold text-slate-400 select-none">&hellip;</span>
            @endif
        @endif

        @foreach (range($start, $end) as $page)
            @if ($page === $current)
                <span class="{{ $active }} min-w-7 px-2" aria-current="page">{{ number_format($page, 0, ',', '.') }}</span>
            @else
                <a href="{{ $paginator->url($page) }}" class="{{ $link }} min-w-7 px-2">{{ number_format($page, 0, ',', '.') }}</a>
            @endif
        @endforeach

        @if ($hasNext && $end < $maxPage)
            <span class="inline-flex items-center justify-center min-w-5 h-7 text-xs font-bold text-slate-400 select-none">&hellip;</span>
        @endif

        @if ($hasNext)
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $link }} px-2.5">Selanjutnya &rsaquo;</a>
        @else
            <span class="{{ $disabled }} px-2.5" aria-disabled="true">Selanjutnya &rsaquo;</span>
        @endif
    </div>

    @if ($maxPage > 1)
        <form method="GET" action="{{ $paginator->path() }}" class="flex shrink-0 items-center gap-1.5">
            @foreach ($query as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <label for="jump-page" class="text-xs text-slate-500">Ke halaman</label>
            <input id="jump-page" type="number" name="page" min="1" max="{{ $maxPage }}" value="{{ $current }}" required
                class="w-16 h-7 px-1.5 text-xs text-center text-slate-700 bg-white border border-slate-200 rounded-md focus:border-[#946d25] focus:ring-1 focus:ring-[#946d25] focus:outline-none">
            <button type="submit" class="inline-flex items-center justify-center h-7 px-3 text-xs font-semibold text-white bg-[#946d25] hover:bg-[#7d5c1f] rounded-md transition-colors">
                Buka
            </button>
        </form>
    @endif
</nav>
