@extends('layouts.app')

@section('title', 'Create Category')
@section('header', 'Create Category')

@section('content')
    <div x-data="{ subCategories: [{ name: '' }] }" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <x-card>
                <x-slot name="header">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.16em] uppercase text-[#c99a3e]">Master Data</p>
                        <h3 class="mt-1 text-xl font-bold text-[#071833]">Create Regulation Category</h3>
                        <p class="text-sm text-[#667085] mt-1">Tambah kategori baru beserta sub kategorinya sekaligus.</p>
                    </div>
                </x-slot>

                <form method="POST" action="{{ route('regulation-categories.store') }}" class="space-y-6">
                    @csrf
                    <div>
                        <label for="name" class="block text-sm font-semibold text-[#071833] mb-2">Nama Kategori <span class="text-[#c99a3e]">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" required class="input-premium" placeholder="Contoh: Pasar Modal">
                        @error('name')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-semibold text-[#071833] mb-2">Deskripsi</label>
                        <textarea name="description" id="description" rows="3" class="input-premium input-textarea" placeholder="Jelaskan ruang lingkup kategori ini...">{{ old('description') }}</textarea>
                        @error('description')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="sector_id" class="block text-sm font-semibold text-[#071833] mb-2">Sektor</label>
                        <select name="sector_id" id="sector_id" class="select-premium">
                            <option value="">-- Pilih Sektor (Opsional) --</option>
                            @foreach ($sectors as $sector)
                                <option value="{{ $sector->id }}" {{ old('sector_id') == $sector->id ? 'selected' : '' }}>
                                    {{ $sector->name }}</option>
                            @endforeach
                        </select>
                        @error('sector_id')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    {{-- Sub Categories --}}
                    <div class="border-t border-[#e7eaf0] pt-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-bold text-[#071833]">Sub Category</h3>
                                <p class="text-xs text-[#667085] mt-0.5">Tambahkan sub kategori di bawah kategori yang sedang dibuat.</p>
                            </div>
                            <x-button type="button" variant="outline" size="sm" @click="subCategories.push({ name: '' })">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                                Tambah Sub
                            </x-button>
                        </div>

                        <div class="space-y-3">
                            <template x-for="(sub, index) in subCategories" :key="index">
                                <div class="flex items-center gap-3">
                                    <div class="shrink-0 w-8 h-8 rounded-lg bg-[#f6f8fb] text-[#c99a3e] flex items-center justify-center text-xs font-bold" x-text="index + 1"></div>
                                    <input type="text" name="sub_categories[]" x-model="sub.name" class="input-premium flex-1" placeholder="Nama sub category...">
                                    <button type="button" x-show="subCategories.length > 1" @click="subCategories.splice(index, 1)" class="shrink-0 w-9 h-9 rounded-xl text-rose-500 hover:bg-rose-50 flex items-center justify-center transition">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </template>
                        </div>

                        @error('sub_categories')<p class="mt-3 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                        @error('sub_categories.*')<p class="mt-3 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3 pt-3 border-t border-[#e7eaf0]">
                        <x-button type="submit" variant="primary" size="lg">Simpan Kategori</x-button>
                        <x-button href="{{ route('regulation-categories.index') }}" variant="outline" size="lg">Batal</x-button>
                    </div>
                </form>
            </x-card>
        </div>

        <aside class="space-y-6">
            <x-card>
                <x-slot name="header">
                    <h3 class="text-base font-bold text-[#071833]">Panduan</h3>
                </x-slot>
                <div class="space-y-4">
                    <p class="text-sm text-[#667085] leading-relaxed">Buat kategori dan sub kategori sekaligus dalam satu form. Sub category membantu mengelompokkan regulasi secara lebih spesifik.</p>
                    <div class="p-4 rounded-2xl bg-[#f6f8fb] ring-1 ring-[#e7eaf0]">
                        <p class="text-xs font-bold text-[#071833] mb-2">Contoh:</p>
                        <ul class="text-xs text-[#667085] space-y-1">
                            <li><strong class="text-[#071833]">Kategori:</strong> Pasar Modal</li>
                            <li class="pl-3">- Reksa Dana</li>
                            <li class="pl-3">- Obligasi</li>
                            <li class="pl-3">- Saham</li>
                        </ul>
                    </div>
                </div>
            </x-card>

            <x-card>
                <x-slot name="header">
                    <h3 class="text-base font-bold text-[#071833]">Quick Links</h3>
                </x-slot>
                <div class="space-y-2.5">
                    <x-button href="{{ route('regulation-categories.index') }}" variant="outline" class="w-full justify-start">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
                        Daftar Kategori
                    </x-button>
                    <x-button href="{{ route('sub-categories.index') }}" variant="ghost" class="w-full justify-start">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 0 1 0 2.828l-7 7a2 2 0 0 1-2.828 0l-7-7A2 2 0 0 1 3 12V7a4 4 0 0 1 4-4Z"/></svg>
                        Kelola Sub Category
                    </x-button>
                </div>
            </x-card>
        </aside>
    </div>
@endsection
