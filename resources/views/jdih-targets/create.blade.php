@extends('layouts.app')

@section('title', 'Tambah Target Scraper')
@section('header', 'Tambah Target Scraper')

@section('content')
    <div class="max-w-3xl">
        <x-card>
            <x-slot name="header">
                <div>
                    <p class="text-xs font-semibold tracking-[0.16em] uppercase text-[#c99a3e]">Master Data</p>
                    <h3 class="mt-1 text-xl font-bold text-[#071833]">Tambah Target Website</h3>
                    <p class="mt-1 text-sm text-[#667085]">Isi identitas website dan sektor untuk source scraper.</p>
                </div>
            </x-slot>
            <form method="POST" action="{{ route('jdih-targets.store') }}" class="space-y-5">
                @csrf
                @include('jdih-targets._form', ['target' => null])
                <div class="flex flex-col gap-3 border-t border-[#e7eaf0] pt-4 sm:flex-row">
                    <x-button type="submit" variant="primary" size="lg">Simpan</x-button>
                    <x-button href="{{ route('jdih-targets.index') }}" variant="outline" size="lg">Batal</x-button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
