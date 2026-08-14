@extends('layouts.app')

@section('title', 'Edit Category')
@section('header', 'Edit Category')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <x-card>
                <x-slot name="header">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.16em] uppercase text-[#c99a3e]">Editing</p>
                        <h3 class="mt-1 text-xl font-bold text-[#071833]">{{ $regulationCategory->name }}</h3>
                        <p class="text-sm text-[#667085] mt-1">Update the regulation category details.</p>
                    </div>
                </x-slot>

                <form method="POST" action="{{ route('regulation-categories.update', $regulationCategory) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="name" class="block text-sm font-semibold text-[#071833] mb-2">Name <span class="text-[#c99a3e]">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name', $regulationCategory->name) }}" required class="input-premium">
                        @error('name')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-semibold text-[#071833] mb-2">Description</label>
                        <textarea name="description" id="description" rows="4" class="input-premium input-textarea">{{ old('description', $regulationCategory->description) }}</textarea>
                        @error('description')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="sector_id" class="block text-sm font-semibold text-[#071833] mb-2">Sektor</label>
                        <select name="sector_id" id="sector_id" class="select-premium">
                            <option value="">-- Pilih Sektor (Opsional) --</option>
                            @foreach ($sectors as $sector)
                                <option value="{{ $sector->id }}" {{ old('sector_id', $regulationCategory->sector_id) == $sector->id ? 'selected' : '' }}>
                                    {{ $sector->name }}</option>
                            @endforeach
                        </select>
                        @error('sector_id')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3 pt-3 border-t border-[#e7eaf0]">
                        <x-button type="submit" variant="primary" size="lg">Update Category</x-button>
                        <x-button href="{{ route('regulation-categories.show', $regulationCategory) }}" variant="outline" size="lg">Cancel</x-button>
                    </div>
                </form>
            </x-card>
        </div>

        <aside>
            <x-card>
                <x-slot name="header">
                    <h3 class="text-base font-bold text-[#071833]">Last Updated</h3>
                </x-slot>
                <p class="text-2xl font-bold text-[#071833]">{{ $regulationCategory->updated_at->format('d M Y') }}</p>
                <p class="text-xs text-[#667085] mt-1">{{ $regulationCategory->updated_at->diffForHumans() }}</p>
            </x-card>
        </aside>
    </div>
@endsection
