{{-- Tabel Rekapitulasi User --}}
<div class="overflow-x-auto rounded-xl border border-slate-200">
    <table class="w-full text-xs text-left">
        <thead class="bg-slate-50 text-slate-600 uppercase font-semibold text-[11px] tracking-wider border-b border-slate-200">
            <tr>
                <th class="py-3 px-4">Nama Pengguna</th>
                <th class="py-3 px-4 text-center">Berhasil Di-download</th>
                <th class="py-3 px-4 text-center">Gagal Download</th>
                <th class="py-3 px-4 text-center">Total Percobaan</th>
                <th class="py-3 px-4 text-center" title="Total percobaan dibagi durasi aktif (unduhan pertama s.d. terakhir) dalam periode">Rata-rata (file/menit)</th>
                <th class="py-3 px-4 text-center border-l border-slate-200">
                    Jam Ini
                    <span class="block normal-case font-normal text-[10px] text-slate-400">{{ $currentHourLabel ?? '' }} (Berhasil / Gagal)</span>
                </th>
                @if($canSendWorkAlert ?? false)
                    <th class="py-3 px-4 text-center">Peringatan</th>
                @endif
            </tr>
            <tr class="normal-case tracking-normal">
                <th colspan="5" class="pb-2 px-4 font-normal text-[11px] text-slate-500 text-center">
                    Periode: <span class="font-semibold text-[#946d25]">{{ $recapPeriodLabel ?? 'Semua Waktu' }}</span>
                </th>
                <th class="border-l border-slate-200"></th>
                @if($canSendWorkAlert ?? false)
                    <th></th>
                @endif
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 bg-white">
            @forelse ($userDownloadStats as $stat)
                @php
                    $isThisUserActive = ((string) ($selectedUserId ?? '')) === ((string) $stat->user_id);
                    $isCurrentUser = ($currentUserId ?? 0) === $stat->user_id;
                @endphp
                <tr class="hover:bg-slate-50 transition-colors {{ $isThisUserActive ? 'bg-[#946d25]/5' : '' }}">
                    <td class="py-3 px-4 align-middle">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-xs shrink-0 {{ $isThisUserActive ? 'bg-[#946d25] text-white shadow-xs' : 'bg-slate-100 text-slate-700' }}">
                                {{ strtoupper(substr($stat->name, 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <div class="text-[#071833] font-semibold flex items-center gap-2">
                                    <span class="truncate">{{ $stat->name }}</span>
                                    @if($isThisUserActive)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-[#946d25] text-white shrink-0">Aktif</span>
                                    @endif
                                    @if($isCurrentUser)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600 shrink-0">Anda</span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-slate-400 font-normal truncate">{{ $stat->email }}</div>
                            </div>
                        </div>
                    </td>
                    <td class="py-3 px-4 text-center align-middle">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                            {{ number_format($stat->success_count, 0, ',', '.') }}
                        </span>
                    </td>
                    <td class="py-3 px-4 text-center align-middle">
                        @if($stat->failure_count > 0)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                <svg class="w-3.5 h-3.5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                {{ number_format($stat->failure_count, 0, ',', '.') }}
                            </span>
                        @else
                            <span class="text-slate-400 font-medium">0</span>
                        @endif
                    </td>
                    <td class="py-3 px-4 text-center align-middle">
                        <span class="text-xs font-bold text-slate-800">
                            {{ number_format($stat->total_count, 0, ',', '.') }}
                        </span>
                    </td>
                    <td class="py-3 px-4 text-center align-middle font-semibold text-slate-700">
                        {{ number_format($stat->per_minute, 0, ',', '.') }} <span class="text-[11px] font-normal text-slate-400">file/menit</span>
                    </td>
                    <td class="py-3 px-4 text-center align-middle border-l border-slate-100 whitespace-nowrap">
                        <span class="font-bold text-emerald-700">{{ number_format($stat->hour_success_count, 0, ',', '.') }}</span>
                        <span class="text-slate-300 mx-1">/</span>
                        <span class="font-bold {{ $stat->hour_failure_count > 0 ? 'text-rose-700' : 'text-slate-400' }}">{{ number_format($stat->hour_failure_count, 0, ',', '.') }}</span>
                    </td>
                    @if($canSendWorkAlert ?? false)
                        <td class="py-3 px-4 text-center align-middle">
                            @if($stat->user_id > 0)
                                <button type="button"
                                    data-url="{{ route('legal-decisions.work-alert.send', $stat->user_id) }}"
                                    data-label="{{ $isCurrentUser ? 'Tes Alert' : 'Peringatkan' }}"
                                    title="{{ $isCurrentUser ? 'Uji coba: bunyikan peringatan di browser Anda sendiri (muncul dalam 15 detik)' : 'Bunyikan peringatan suara di browser '.$stat->name }}"
                                    x-data="workAlertButton()"
                                    x-on:click="send"
                                    x-bind:disabled="sending"
                                    x-bind:class="sent ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-800 border-amber-200 hover:bg-amber-100'"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-semibold border transition disabled:opacity-50">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5L6 9H2v6h4l5 4V5zm4.54 3.46a5 5 0 010 7.07M19.07 4.93a10 10 0 010 14.14" /></svg>
                                    <span x-text="text">{{ $isCurrentUser ? 'Tes Alert' : 'Peringatkan' }}</span>
                                </button>
                            @else
                                <span class="text-[11px] text-slate-400">-</span>
                            @endif
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ ($canSendWorkAlert ?? false) ? 7 : 6 }}" class="py-6 text-center text-slate-400">{{ !empty($filters['recap_from']) ? 'Tidak ada aktivitas download pada tanggal/jam yang dipilih.' : 'Belum ada riwayat aktivitas download pengguna.' }}</td>
                </tr>
            @endforelse
        </tbody>
        @if(!empty($userAverages) && $userDownloadStats->count() > 1)
            <tfoot class="bg-slate-50 border-t border-slate-200 text-xs font-semibold text-slate-700">
                <tr>
                    <td class="py-3 px-4">Rata-rata per Pengguna</td>
                    <td class="py-3 px-4 text-center text-emerald-700">{{ number_format($userAverages->success, 0, ',', '.') }}</td>
                    <td class="py-3 px-4 text-center text-rose-700">{{ number_format($userAverages->failure, 0, ',', '.') }}</td>
                    <td class="py-3 px-4 text-center">{{ number_format($userAverages->total, 0, ',', '.') }}</td>
                    <td class="py-3 px-4 text-center">{{ number_format($userAverages->per_minute, 0, ',', '.') }} <span class="text-[11px] font-normal text-slate-400">file/menit</span></td>
                    <td class="py-3 px-4 border-l border-slate-200"></td>
                    @if($canSendWorkAlert ?? false)
                        <td></td>
                    @endif
                </tr>
            </tfoot>
        @endif
    </table>
</div>

