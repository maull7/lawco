@props(['name', 'title' => '', 'maxWidth' => 'lg'])

@php
    $maxWidthClass = match ($maxWidth) {
        'sm' => 'max-w-sm',
        'md' => 'max-w-md',
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        '3xl' => 'max-w-3xl',
        'full' => 'max-w-full mx-4',
        default => 'max-w-lg',
    };
@endphp

<div x-data="{ show: false }" x-on:open-modal-{{ $name }}.window="show = true"
    x-on:close-modal-{{ $name }}.window="show = false" x-on:keydown.escape.window="show = false"
    x-effect="document.body.classList.toggle('overflow-hidden', show)" x-cloak>
    <template x-teleport="body">
        <div x-show="show" x-transition.opacity.duration.150ms class="fixed inset-0 z-[100] overflow-y-auto"
            style="display: none;">
            <div class="fixed inset-0 bg-[#071b3a]/55 backdrop-blur-sm" @click="show = false"></div>

            <div class="relative flex min-h-full items-start justify-center px-4 py-6 sm:items-center sm:py-10"
                @click.self="show = false">
                <div x-show="show" x-transition:enter="ease-out duration-150"
                    x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="ease-in duration-100" x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="relative w-full {{ $maxWidthClass }} bg-white rounded-[24px] shadow-[0_30px_80px_rgba(7,27,58,.18)] ring-1 ring-[#e7eaf0] overflow-hidden">
                    @if ($title)
                        <div class="flex items-center justify-between px-7 py-5 border-b border-[#e7eaf0]">
                            <h3 class="text-lg font-bold text-[#071833] tracking-tight">{{ $title }}</h3>
                            <button @click="show = false" type="button"
                                class="-mr-2 p-2 rounded-xl text-[#667085] hover:bg-[#f6f8fb] hover:text-[#071833] transition">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    @endif

                    <div class="p-7 max-h-[75vh] overflow-y-auto">
                        {{ $slot }}
                    </div>

                    @isset($footer)
                        <div
                            class="px-7 py-4 border-t border-[#e7eaf0] bg-[#f6f8fb]/60 flex items-center justify-end gap-3 rounded-b-[24px]">
                            {{ $footer }}
                        </div>
                    @endisset
                </div>
            </div>
        </div>
    </template>
</div>
