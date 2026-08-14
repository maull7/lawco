@props(['color' => 'gray', 'dot' => true])

@php
    $palette = match ($color) {
        'green' => [
            'bg' => 'bg-emerald-50',
            'text' => 'text-emerald-700',
            'ring' => 'ring-emerald-200/70',
            'dot' => 'bg-emerald-500',
        ],
        'yellow' => [
            'bg' => 'bg-amber-50',
            'text' => 'text-amber-700',
            'ring' => 'ring-amber-200/70',
            'dot' => 'bg-amber-500',
        ],
        'red' => [
            'bg' => 'bg-rose-50',
            'text' => 'text-rose-700',
            'ring' => 'ring-rose-200/70',
            'dot' => 'bg-rose-500',
        ],
        'blue' => ['bg' => 'bg-sky-50', 'text' => 'text-sky-700', 'ring' => 'ring-sky-200/70', 'dot' => 'bg-sky-500'],
        'orange' => [
            'bg' => 'bg-orange-50',
            'text' => 'text-orange-700',
            'ring' => 'ring-orange-200/70',
            'dot' => 'bg-orange-500',
        ],
        'purple' => [
            'bg' => 'bg-violet-50',
            'text' => 'text-violet-700',
            'ring' => 'ring-violet-200/70',
            'dot' => 'bg-violet-500',
        ],
        'gold' => [
            'bg' => 'bg-[#fdf6e7]',
            'text' => 'text-[#8c6a25]',
            'ring' => 'ring-[#c99a3e]/30',
            'dot' => 'bg-[#c99a3e]',
        ],
        'navy' => [
            'bg' => 'bg-[#eef2f8]',
            'text' => 'text-[#071b3a]',
            'ring' => 'ring-[#071b3a]/15',
            'dot' => 'bg-[#071b3a]',
        ],
        default => [
            'bg' => 'bg-slate-50',
            'text' => 'text-slate-700',
            'ring' => 'ring-slate-200/70',
            'dot' => 'bg-slate-400',
        ],
    };
@endphp

<span
    class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-semibold rounded-full ring-1 ring-inset {{ $palette['bg'] }} {{ $palette['text'] }} {{ $palette['ring'] }}">
    @if ($dot)
        <span class="w-1.5 h-1.5 rounded-full {{ $palette['dot'] }}"></span>
    @endif
    {{ $slot }}
</span>
