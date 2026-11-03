@extends('layouts.app')

@section('title', 'Activity Logs — ' . $user->name)
@section('header', 'Activity Logs')

@php
    $actionLabels = [
        'created' => ['bg-emerald-100 text-emerald-700', 'Menambahkan'],
        'updated' => ['bg-blue-100 text-blue-700', 'Memperbarui'],
        'deleted' => ['bg-rose-100 text-rose-700', 'Menghapus'],
        'uploaded' => ['bg-purple-100 text-purple-700', 'Mengunggah'],
        'parsed' => ['bg-cyan-100 text-cyan-700', 'Parse'],
        'reanalyzed' => ['bg-indigo-100 text-indigo-700', 'Re-Analisis'],
        'toggled' => ['bg-amber-100 text-amber-700', 'Toggle'],
        'submitted' => ['bg-teal-100 text-teal-700', 'Submit'],
        'completed' => ['bg-emerald-100 text-emerald-700', 'Selesai'],
        'revision_requested' => ['bg-orange-100 text-orange-700', 'Revisi'],
        'rejected' => ['bg-rose-100 text-rose-700', 'Ditolak'],
        'downloaded' => ['bg-emerald-100 text-emerald-700', 'Mengunduh'],
        'download_failed' => ['bg-rose-100 text-rose-700', 'Gagal Unduh'],
        'generated' => ['bg-purple-100 text-purple-700', 'Generated'],
    ];

    $subjectLabels = [
        'App\Models\User' => 'User',
        'App\Models\Regulation' => 'Regulasi',
        'App\Models\RegulationCategory' => 'Kategori',
        'App\Models\RegulationType' => 'Jenis Regulasi',
        'App\Models\SubCategory' => 'Sub Kategori',
        'App\Models\ReviewDocument' => 'Dokumen Review',
        'App\Models\Review' => 'Review',
        'App\Models\AiPrompt' => 'AI Prompt',
        'App\Models\CategoryFile' => 'File',
        'App\Models\DownloadedLegalDecision' => 'Putusan MA',
        'App\Models\LegalDecision' => 'Putusan MA',
        'App\Models\LegalDecisionDownloadFailure' => 'Putusan MA',
    ];
@endphp

@section('content')
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
        <div>
            <p class="text-xs font-semibold tracking-[0.16em] uppercase text-[#c99a3e]">Administration</p>
            <h2 class="mt-2 text-3xl font-bold text-[#071833] tracking-tight">Activity Logs</h2>
            <p class="mt-1.5 text-sm text-[#667085]">
                Log aktivitas untuk <span class="font-semibold text-[#071833]">{{ $user->name }}</span>
                ({{ $user->email }}) — Role: {{ str_replace('_', ' ', ucfirst($user->role)) }}
            </p>
        </div>
        <a href="{{ route('users.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold text-[#667085] bg-[#f6f8fb] ring-1 ring-[#e7eaf0] hover:bg-white hover:ring-[#c99a3e]/40 transition">
            Kembali ke Users
        </a>
    </div>

    {{-- Ringkasan Statistik Unduhan Putusan MA per User --}}
    @if(isset($downloadStats))
        <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="p-4 rounded-2xl bg-white border border-[#e7eaf0] shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Percobaan Download</p>
                    <p class="text-2xl font-bold text-[#071833] mt-1">{{ number_format($downloadStats->total, 0, ',', '.') }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-emerald-50/60 border border-emerald-200/80 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-emerald-800 uppercase tracking-wider">Berhasil Di-download</p>
                    <p class="text-2xl font-bold text-emerald-700 mt-1">{{ number_format($downloadStats->success, 0, ',', '.') }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-rose-50/60 border border-rose-200/80 shadow-xs flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-rose-800 uppercase tracking-wider">Gagal Download</p>
                    <p class="text-2xl font-bold text-rose-700 mt-1">{{ number_format($downloadStats->failure, 0, ',', '.') }}</p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
            </div>
        </div>
    @endif

    <x-card :padding="false" class="mt-6">
        @if($logs->isEmpty())
            <div class="text-center py-14">
                <div class="mx-auto w-16 h-16 rounded-2xl bg-[#f6f8fb] flex items-center justify-center text-[#c99a3e]">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                </div>
                <p class="mt-4 text-base font-bold text-[#071833]">Belum ada aktivitas</p>
                <p class="mt-1 text-sm text-[#667085]">User ini belum melakukan aktivitas apapun.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="table-premium">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Aksi</th>
                            <th>Subjek</th>
                            <th>Deskripsi</th>
                            <th>IP Address</th>
                            <th class="text-right">Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                            @php
                                $actionData = $actionLabels[$log->action] ?? ['bg-gray-100 text-gray-600', ucfirst($log->action)];
                            @endphp
                            <tr>
                                <td class="font-semibold text-[#667085]">{{ $logs->firstItem() + $loop->index }}</td>
                                <td>
                                    <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold {{ $actionData[0] }}">
                                        {{ $actionData[1] }}
                                    </span>
                                </td>
                                <td>
                                    @if($log->subject_type)
                                        <span class="text-xs font-semibold text-[#071833]">
                                            {{ $subjectLabels[$log->subject_type] ?? class_basename($log->subject_type) }}
                                        </span>
                                    @else
                                        <span class="text-xs text-[#667085]">-</span>
                                    @endif
                                </td>
                                <td class="text-sm text-[#071833] max-w-xs truncate">{{ $log->description }}</td>
                                <td class="text-xs font-mono text-[#667085]">{{ $log->ip_address ?? '-' }}</td>
                                <td class="text-right text-xs text-[#667085] whitespace-nowrap">
                                    <span>{{ $log->created_at->format('d M Y H:i') }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($logs->hasPages())
                <div class="px-6 py-4 border-t border-[#e7eaf0]">
                    {{ $logs->links() }}
                </div>
            @endif
        @endif
    </x-card>
@endsection
