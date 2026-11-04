@extends('layouts.app')

@section('title', 'Manage Users')
@section('header', 'Manage Users')

@section('content')
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
        <div>
            <p class="text-xs font-semibold tracking-[0.16em] uppercase text-[#c99a3e]">Administration</p>
            <h2 class="mt-2 text-3xl font-bold text-[#071833] tracking-tight">Manage Users</h2>
            <p class="mt-1.5 text-sm text-[#667085]">Kelola user, role, dan permission akses ke sistem.</p>
        </div>
        <x-button href="{{ route('users.create') }}" variant="primary">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Tambah User
        </x-button>
    </div>

    {{-- Filters --}}
    <x-card class="mt-6">
        <form method="GET" action="{{ route('users.index') }}">
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1">
                    <input type="text" name="search" value="{{ request('search') }}" class="input-premium" placeholder="Cari user berdasarkan nama atau email...">
                </div>
                <div class="flex items-center gap-2">
                    <x-button type="submit" variant="primary" size="md">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                        Cari
                    </x-button>
                    <div class="w-40 sm:w-44">
                        <select name="role" class="select-premium !rounded-full text-sm" onchange="this.form.submit()">
                            <option value="">Semua Role</option>
                            <option value="user" @selected(request('role') === 'user')>User</option>
                            <option value="sub_admin" @selected(request('role') === 'sub_admin')>Sub Admin</option>
                            <option value="reviewer" @selected(request('role') === 'reviewer')>Reviewer</option>
                            <option value="admin" @selected(request('role') === 'admin')>Admin</option>
                        </select>
                    </div>
                    <x-button href="{{ route('users.index') }}" variant="outline" size="md">Reset</x-button>
                </div>
            </div>
        </form>
    </x-card>

    {{-- Table --}}
    <x-card :padding="false" class="mt-6">
        @if($users->isEmpty())
            <div class="text-center py-14">
                <div class="mx-auto w-16 h-16 rounded-2xl bg-[#f6f8fb] flex items-center justify-center text-[#c99a3e]">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.4"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                </div>
                <p class="mt-4 text-base font-bold text-[#071833]">Belum ada user</p>
                <p class="mt-1 text-sm text-[#667085]">Tambahkan user baru untuk memberikan akses ke sistem.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="table-premium">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Permissions</th>
                            <th>Token</th>
                            <th>Kak Vesta</th>
                            <th>Dibuat</th>
                            <th class="text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $index => $user)
                            <tr>
                                <td class="font-semibold">{{ $users->firstItem() + $index }}</td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#071b3a] to-[#0b2a55] flex items-center justify-center text-white font-bold text-sm">
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        </div>
                                        <span class="font-semibold text-[#071833]">{{ $user->name }}</span>
                                    </div>
                                </td>
                                <td class="text-sm text-[#667085]">{{ $user->email }}</td>
                                <td>
                                    @php
                                        $roleColors = ['admin' => 'yellow', 'sub_admin' => 'blue', 'reviewer' => 'purple', 'user' => 'gray'];
                                    @endphp
                                    <x-badge :color="$roleColors[$user->role] ?? 'gray'">
                                        {{ str_replace('_', ' ', ucfirst($user->role)) }}
                                    </x-badge>
                                </td>
                                <td>
                                    @if($user->isAdmin())
                                        <span class="text-xs text-[#667085]">Semua akses</span>
                                    @elseif($user->permissions)
                                        <div class="flex flex-wrap gap-1">
                                            @foreach($user->permissions as $perm)
                                                <x-badge color="gray" size="sm">{{ $perm }}</x-badge>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-xs text-[#667085]">-</span>
                                    @endif
                                </td>
                                <td class="text-xs text-[#667085] whitespace-nowrap">
                                    @php $used = $usages[$user->id] ?? 0; @endphp
                                    <span @class([
                                        'font-bold' => $used >= $dailyLimit,
                                        'text-rose-600' => $used >= $dailyLimit,
                                        'text-[#667085]' => $used < $dailyLimit,
                                    ])>
                                        {{ number_format($used) }}
                                    </span>
                                    <span class="text-[#b0b8c5]"> / {{ number_format($dailyLimit) }}</span>
                                    @if ($used >= $dailyLimit)
                                        <span class="ml-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-600">Penuh</span>
                                    @endif
                                </td>
                                <td class="text-xs text-[#667085] whitespace-nowrap">
                                    @php $usage = $user->kakVestaUsage(); @endphp
                                    @if ($usage)
                                        @php
                                            $allowedHours = intdiv($usage['allowed_minutes'], 60);
                                            $hours = intdiv($usage['elapsed_minutes'], 60);
                                            $minutes = $usage['elapsed_minutes'] % 60;
                                            $text = $usage['elapsed_minutes'] === 0
                                                ? 'Belum dipakai'
                                                : ($hours > 0
                                                    ? "{$hours} jam {$minutes} mnt / {$allowedHours} jam"
                                                    : "{$minutes} mnt / {$allowedHours} jam");
                                        @endphp
                                        <span @class([
                                            'font-bold' => $usage['expired'],
                                            'text-rose-600' => $usage['expired'],
                                            'text-[#667085]' => ! $usage['expired'],
                                        ])>{{ $text }}</span>
                                        @if ($usage['expired'])
                                            <span class="ml-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-600">Habis</span>
                                        @endif
                                    @else
                                        <span class="text-[#b0b8c5]">-</span>
                                    @endif
                                </td>
                                <td class="text-xs text-[#667085] whitespace-nowrap">{{ $user->created_at->format('d M Y H:i') }}</td>
                                <td>
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('users.show', $user) }}" class="inline-flex items-center justify-center w-9 h-9 rounded-xl text-[#667085] hover:bg-[#f6f8fb] transition" title="Logs">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                                        </a>
                                        <a href="{{ route('users.edit', $user) }}" class="inline-flex items-center justify-center w-9 h-9 rounded-xl text-[#667085] hover:bg-[#f6f8fb] transition" title="Edit">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487 18.55 2.8a2.121 2.121 0 1 1 3 3L19.863 7.487m-3-3L8.25 13.1l-1.5 4.5 4.5-1.5 8.613-8.613m-3-3 3 3"/></svg>
                                        </a>
                                        @if(auth()->id() !== $user->id)
                                            <form method="POST" action="{{ route('users.destroy', $user) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus user ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center justify-center w-9 h-9 rounded-xl text-rose-600 hover:bg-rose-50 transition" title="Hapus">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($users->hasPages())
                <div class="px-6 py-4 border-t border-[#e7eaf0]">
                    {{ $users->links() }}
                </div>
            @endif
        @endif
    </x-card>
@endsection
