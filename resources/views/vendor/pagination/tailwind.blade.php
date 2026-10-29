@if ($paginator->hasPages())
    @php
        $displayTotal = isset($realTotal) && $realTotal ? $realTotal : $paginator->total();
    @endphp
    <nav role="navigation" aria-label="Navigasi Halaman" class="flex flex-col sm:flex-row items-center justify-between gap-3 w-full">
        {{-- Mobile Compact View --}}
        <div class="flex items-center justify-between w-full sm:hidden gap-2">
            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium text-slate-300 bg-slate-50 border border-slate-200 rounded-lg cursor-not-allowed select-none">
                    &laquo; Sebelumnya
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium text-slate-700 bg-white hover:text-[#946d25] hover:bg-slate-50 border border-slate-200 rounded-lg transition-colors shadow-2xs">
                    &laquo; Sebelumnya
                </a>
            @endif

            <span class="text-xs font-semibold text-slate-600">
                Hal {{ number_format($paginator->currentPage(), 0, ',', '.') }} dari {{ number_format($paginator->lastPage(), 0, ',', '.') }}
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium text-slate-700 bg-white hover:text-[#946d25] hover:bg-slate-50 border border-slate-200 rounded-lg transition-colors shadow-2xs">
                    Selanjutnya &raquo;
                </a>
            @else
                <span class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium text-slate-300 bg-slate-50 border border-slate-200 rounded-lg cursor-not-allowed select-none">
                    Selanjutnya &raquo;
                </span>
            @endif
        </div>

        {{-- Desktop / Tablet View --}}
        <div class="hidden sm:flex sm:items-center sm:justify-between sm:w-full gap-4">
            {{-- Summary Counter --}}
            <div class="text-xs md:text-sm text-slate-600 font-medium">
                Menampilkan
                @if ($paginator->firstItem())
                    <span class="font-bold text-[#071833]">{{ number_format($paginator->firstItem(), 0, ',', '.') }}</span>
                    sampai
                    <span class="font-bold text-[#071833]">{{ number_format($paginator->lastItem(), 0, ',', '.') }}</span>
                @else
                    <span class="font-bold text-[#071833]">{{ number_format($paginator->count(), 0, ',', '.') }}</span>
                @endif
                dari
                <span class="font-bold text-[#946d25]">{{ number_format($displayTotal, 0, ',', '.') }}</span>
                hasil
            </div>

            {{-- Page Buttons --}}
            <div class="inline-flex items-center gap-1">
                {{-- Previous Link --}}
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true" class="inline-flex items-center justify-center h-8 px-2.5 text-xs font-medium text-slate-300 bg-slate-50 border border-slate-200 rounded-lg cursor-not-allowed select-none">
                        <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        <span>Sebelumnya</span>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center justify-center h-8 px-2.5 text-xs font-medium text-slate-700 bg-white hover:text-[#946d25] hover:bg-amber-50/40 border border-slate-200 rounded-lg transition-colors shadow-2xs">
                        <svg class="w-4 h-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                        <span>Sebelumnya</span>
                    </a>
                @endif

                {{-- Elements (Numbers & Separators) --}}
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="inline-flex items-center justify-center min-w-8 h-8 px-1 text-xs font-bold text-slate-400 select-none">
                            {{ $element }}
                        </span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="inline-flex items-center justify-center min-w-8 h-8 px-2 text-xs font-bold text-white bg-[#946d25] border border-[#946d25] rounded-lg shadow-sm select-none">
                                    {{ number_format($page, 0, ',', '.') }}
                                </span>
                            @else
                                <a href="{{ $url }}" class="inline-flex items-center justify-center min-w-8 h-8 px-2 text-xs font-medium text-slate-700 bg-white hover:text-[#946d25] hover:bg-amber-50/40 border border-slate-200 rounded-lg transition-colors shadow-2xs">
                                    {{ number_format($page, 0, ',', '.') }}
                                </a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Next Link --}}
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center justify-center h-8 px-2.5 text-xs font-medium text-slate-700 bg-white hover:text-[#946d25] hover:bg-amber-50/40 border border-slate-200 rounded-lg transition-colors shadow-2xs">
                        <span>Selanjutnya</span>
                        <svg class="w-4 h-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                @else
                    <span aria-disabled="true" class="inline-flex items-center justify-center h-8 px-2.5 text-xs font-medium text-slate-300 bg-slate-50 border border-slate-200 rounded-lg cursor-not-allowed select-none">
                        <span>Selanjutnya</span>
                        <svg class="w-4 h-4 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </span>
                @endif
            </div>
        </div>
    </nav>
@endif
