@extends('layouts.app')
@section('title', 'Keputusan Hukum')
@section('header', 'Keputusan Hukum')
@section('content')
    <div>
        <h2 class="text-3xl font-bold text-[#071833]">Keputusan Hukum</h2>
        <p class="mt-2 text-sm text-[#667085]">Cari peraturan dari dokumen regulasi Lawco.</p>
    </div>
    <div class="mt-6 grid grid-cols-1 xl:grid-cols-[280px_minmax(0,1fr)] gap-6 items-start">
        <x-card>
            <h3 class="font-bold text-[#071833] mb-4">Pencarian</h3>
            <form method="GET" action="{{ route('legal-decisions.index') }}" class="space-y-4">
                <div>
                    <label for="decision-search" class="block text-sm font-medium mb-1">Kata Kunci</label>
                    <input id="decision-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}" maxlength="250" placeholder="Judul atau nomor peraturan" class="input-premium w-full">
                </div>
                @foreach (['type_id' => ['Jenis', $types], 'category_id' => ['Klasifikasi Peraturan', $categories]] as $field => [$label, $options])
                    <div>
                        <label for="decision-{{ $field }}" class="block text-sm font-medium mb-1">{{ $label }}</label>
                        <select id="decision-{{ $field }}" name="{{ $field }}" class="input-premium w-full">
                            <option value="">Semua</option>
                            @foreach ($options as $option)
                                <option value="{{ $option->id }}" @selected(($filters[$field] ?? '') == $option->id)>{{ $option->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <div>
                    <label for="decision-year" class="block text-sm font-medium mb-1">Tahun Peraturan</label>
                    <select id="decision-year" name="year" class="input-premium w-full">
                        <option value="">Semua tahun</option>
                        @foreach ($years as $year)
                            <option value="{{ $year }}" @selected(($filters['year'] ?? '') == $year)>{{ $year }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($errors->any())
                    <ul class="text-sm text-red-600">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                @endif
                <div class="flex gap-2">
                    <x-button type="submit" variant="primary">Cari</x-button>
                    <x-button href="{{ route('legal-decisions.index') }}" variant="outline">Reset</x-button>
                </div>
            </form>
        </x-card>
        <x-card>
            @if ($regulations === null)
                <div class="py-10 text-center">
                    <h3 class="font-semibold text-[#071833]">Cari dokumen keputusan hukum</h3>
                    <p class="mt-2 text-sm text-[#667085]">Isi kata kunci atau pilih minimal satu filter, lalu klik Cari untuk menampilkan dokumen.</p>
                </div>
            @else
                <p class="mb-4 text-sm text-[#667085]">{{ number_format($regulations->total()) }} dokumen ditemukan</p>
                <div class="overflow-x-auto">
                    <table class="table-premium w-full">
                        <thead><tr><th>Nomor / Judul</th><th>Jenis</th><th>Klasifikasi</th><th>Tahun</th><th>Aksi</th></tr></thead>
                        <tbody>
                            @forelse ($regulations as $regulation)
                                <tr>
                                    <td><p class="font-semibold">{{ $regulation->regulation_number }}</p><p>{{ $regulation->title }}</p></td>
                                    <td>{{ $regulation->type?->name ?? '—' }}</td>
                                    <td>{{ $regulation->category?->name ?? '—' }}</td>
                                    <td>{{ $regulation->year }}</td>
                                    <td><a class="text-[#946d25] font-semibold" href="{{ route('regulations.show', $regulation) }}">Detail</a>
                                        @if ($regulation->file_path)
                                            <a class="ml-3 text-[#946d25] font-semibold" href="{{ route('regulations.file', $regulation) }}">PDF</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center py-10">Tidak ada dokumen yang sesuai dengan pencarian.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $regulations->links() }}</div>
            @endif
        </x-card>
    </div>
@endsection
