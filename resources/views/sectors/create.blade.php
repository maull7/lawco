@extends('layouts.app')

@section('title', 'Tambah Sektor')
@section('header', 'Tambah Sektor')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <x-card>
                <x-slot name="header">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.16em] uppercase text-[#c99a3e]">Master Data</p>
                        <h3 class="mt-1 text-xl font-bold text-[#071833]">Tambah Sektor</h3>
                        <p class="text-sm text-[#667085] mt-1">Buat sektor industri baru untuk mengelompokkan kategori regulasi.</p>
                    </div>
                </x-slot>

                <form method="POST" action="{{ route('sectors.store') }}" class="space-y-6">
                    @csrf
                    <div>
                        <label for="name" class="block text-sm font-semibold text-[#071833] mb-2">Nama Sektor <span class="text-[#c99a3e]">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required class="input-premium" placeholder="Contoh: Perbankan">
                        @error('name')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-semibold text-[#071833] mb-2">Deskripsi</label>
                        <textarea name="description" id="description" rows="3" class="input-premium input-textarea" placeholder="Jelaskan ruang lingkup sektor ini...">{{ old('description') }}</textarea>
                        @error('description')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3 pt-3 border-t border-[#e7eaf0]">
                        <x-button type="submit" variant="primary" size="lg">Simpan</x-button>
                        <x-button href="{{ route('sectors.index') }}" variant="outline" size="lg">Batal</x-button>
                    </div>
                </form>
            </x-card>
        </div>
    </div>
@endsection