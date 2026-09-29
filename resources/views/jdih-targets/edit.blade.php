@extends('layouts.app')

@section('title', 'Edit Target Scraper')
@section('header', 'Edit Target Scraper')

@section('content')
    <div class="max-w-3xl">
        <x-card>
            <x-slot name="header">
                <div>
                    <p class="text-xs font-semibold tracking-[0.16em] uppercase text-[#c99a3e]">Editing</p>
                    <h3 class="mt-1 text-xl font-bold text-[#071833]">{{ $target->name }}</h3>
                    <p class="mt-1 text-sm text-[#667085]">Perbarui URL, source, sektor, atau status target.</p>
                </div>
            </x-slot>
            <form method="POST" action="{{ route('jdih-targets.update', $target) }}" class="space-y-5">
                @csrf
                @method('PUT')
                @include('jdih-targets._form')
                <div class="flex flex-col gap-3 border-t border-[#e7eaf0] pt-4 sm:flex-row">
                    <x-button type="submit" variant="primary" size="lg">Perbarui</x-button>
                    <x-button href="{{ route('jdih-targets.index') }}" variant="outline" size="lg">Batal</x-button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
