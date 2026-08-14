@extends('layouts.app')

@section('title', 'Regulation Categories')
@section('header', 'Regulation Categories')

@section('content')
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
        <div>
            <p class="text-xs font-semibold tracking-[0.16em] uppercase text-[#c99a3e]">Master Data</p>
            <h2 class="mt-2 text-3xl font-bold text-[#071833] tracking-tight">Regulation Categories</h2>
            <p class="mt-1.5 text-sm text-[#667085]">Curate the regulatory framework that documents will be reviewed against.
            </p>
        </div>
        <x-button href="{{ route('regulation-categories.create') }}" variant="primary">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Add Category
        </x-button>
    </div>

    @if ($categories->isEmpty())
        <x-card class="mt-6">
            <div class="text-center py-12">
                <div class="mx-auto w-16 h-16 rounded-2xl bg-[#f6f8fb] flex items-center justify-center text-[#c99a3e]">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                    </svg>
                </div>
                <p class="mt-4 text-base font-bold text-[#071833]">No categories yet</p>
                <p class="mt-1 text-sm text-[#667085]">Create your first regulation category to organize compliance review.
                </p>
                <x-button href="{{ route('regulation-categories.create') }}" variant="primary" size="sm"
                    class="mt-5">Add Category</x-button>
            </div>
        </x-card>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5 mt-6">
            @foreach ($categories as $category)
                <div class="group card-premium card-premium-hover relative p-6 overflow-hidden">
                    <span
                        class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-[#c99a3e]/70 via-[#e6c06a]/70 to-transparent"></span>

                    <div class="flex items-start gap-4">
                        <div
                            class="shrink-0 w-12 h-12 rounded-2xl bg-gradient-to-br from-[#c99a3e] to-[#e6c06a] text-white flex items-center justify-center shadow-[0_8px_20px_rgba(201,154,62,.28)]">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center space-x-1.5">
                                <h3
                                    class="text-base font-bold text-[#071833] group-hover:text-[#c99a3e] transition truncate">
                                    {{ $category->name }}
                                </h3>
                                <x-badge color="yellow">
                                    <h5 class="text-xs font-bold">{{ $category->sector->name ?? 'No Sektor' }}</h5>
                                </x-badge>

                            </div>

                            @if ($category->description)
                                <p class="mt-1 text-sm text-[#667085] line-clamp-2">{{ $category->description }}</p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-5 pt-4 border-t border-dashed border-[#e7eaf0] flex items-center justify-between">
                        <span class="inline-flex items-center gap-2 text-xs font-semibold text-[#071833]">
                            <span class="w-7 h-7 rounded-lg bg-[#f6f8fb] flex items-center justify-center text-[#c99a3e]">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                    stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25M5.625 21h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125Z" />
                                </svg>
                            </span>
                            <span><span class="text-base font-bold">{{ $category->regulations->count() }}</span> <span
                                    class="text-[#667085]">Regulation</span></span>
                        </span>

                        <x-badge color="yellow">{{ $category->regulations->count() }}</x-badge>

                        <a href="{{ route('regulation-categories.edit', $category) }}"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#c99a3e] group-hover:gap-2.5 transition-all">
                            Edit
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                        <a href="{{ route('regulation-categories.show', $category) }}"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#c99a3e] group-hover:gap-2.5 transition-all">
                            Manage
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
