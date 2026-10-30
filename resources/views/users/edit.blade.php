@extends('layouts.app')

@section('title', 'Edit User')
@section('header', 'Edit User')

@section('content')
    <div x-data="{ role: '{{ $user->role }}', permissions: {{ json_encode($user->permissions ?? []) }} }" class="max-w-2xl">
        <x-card>
            <x-slot name="header">
                <div>
                    <p class="text-xs font-semibold tracking-[0.16em] uppercase text-[#c99a3e]">Administration</p>
                    <h3 class="mt-1 text-xl font-bold text-[#071833]">Edit User</h3>
                    <p class="text-sm text-[#667085] mt-1">Ubah data user dan atur role serta permission aksesnya.</p>
                </div>
            </x-slot>

            <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div>
                    <label for="name" class="block text-sm font-semibold text-[#071833] mb-2">Nama <span class="text-[#c99a3e]">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required class="input-premium" placeholder="Nama lengkap user">
                    @error('name')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-semibold text-[#071833] mb-2">Email <span class="text-[#c99a3e]">*</span></label>
                    <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required class="input-premium" placeholder="user@example.com">
                    @error('email')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-semibold text-[#071833] mb-2">Password Baru</label>
                    <input type="password" name="password" id="password" class="input-premium" placeholder="Kosongkan jika tidak ingin mengubah">
                    @error('password')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-semibold text-[#071833] mb-2">Konfirmasi Password Baru</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" class="input-premium" placeholder="Ulangi password baru">
                </div>

                <div>
                    <label for="role" class="block text-sm font-semibold text-[#071833] mb-2">Role <span class="text-[#c99a3e]">*</span></label>
                    <select name="role" id="role" x-model="role" required class="select-premium">
                        <option value="user">User</option>
                        <option value="sub_admin">Sub Admin</option>
                        <option value="reviewer">Reviewer</option>
                        <option value="admin">Admin</option>
                    </select>
                    @error('role')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div x-show="role === 'sub_admin'" x-cloak>
                    <label class="block text-sm font-semibold text-[#071833] mb-3">Permissions</label>
                    <div class="space-y-3">
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-[#e7eaf0] hover:bg-[#f6f8fb] transition cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="upload_regulations" class="w-4 h-4 rounded border-[#d0d5dd] text-[#c99a3e] focus:ring-[#c99a3e]" x-model="permissions">
                            <div>
                                <p class="text-sm font-semibold text-[#071833]">Upload Regulasi</p>
                                <p class="text-xs text-[#667085]">Membuat dan mengedit regulasi/peraturan</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-[#e7eaf0] hover:bg-[#f6f8fb] transition cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="manage_categories" class="w-4 h-4 rounded border-[#d0d5dd] text-[#c99a3e] focus:ring-[#c99a3e]" x-model="permissions">
                            <div>
                                <p class="text-sm font-semibold text-[#071833]">Manage Kategori</p>
                                <p class="text-xs text-[#667085]">Mengelola kategori regulasi</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-[#e7eaf0] hover:bg-[#f6f8fb] transition cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="manage_types" class="w-4 h-4 rounded border-[#d0d5dd] text-[#c99a3e] focus:ring-[#c99a3e]" x-model="permissions">
                            <div>
                                <p class="text-sm font-semibold text-[#071833]">Manage Jenis Regulasi</p>
                                <p class="text-xs text-[#667085]">Mengelola jenis/tipe regulasi</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-[#e7eaf0] hover:bg-[#f6f8fb] transition cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="manage_sub_categories" class="w-4 h-4 rounded border-[#d0d5dd] text-[#c99a3e] focus:ring-[#c99a3e]" x-model="permissions">
                            <div>
                                <p class="text-sm font-semibold text-[#071833]">Manage Sub Kategori</p>
                                <p class="text-xs text-[#667085]">Mengelola sub kategori regulasi</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-[#e7eaf0] hover:bg-[#f6f8fb] transition cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="manage_prompts" class="w-4 h-4 rounded border-[#d0d5dd] text-[#c99a3e] focus:ring-[#c99a3e]" x-model="permissions">
                            <div>
                                <p class="text-sm font-semibold text-[#071833]">Manage AI Prompts</p>
                                <p class="text-xs text-[#667085]">Mengelola prompt untuk AI summary</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-xl border border-[#e7eaf0] hover:bg-[#f6f8fb] transition cursor-pointer">
                            <input type="checkbox" name="permissions[]" value="access_legal_decisions" class="w-4 h-4 rounded border-[#d0d5dd] text-[#c99a3e] focus:ring-[#c99a3e]" x-model="permissions">
                            <div>
                                <p class="text-sm font-semibold text-[#071833]">Putusan MA</p>
                                <p class="text-xs text-[#667085]">Mengakses Putusan MA (langsung &amp; download)</p>
                            </div>
                        </label>
                    </div>
                    @error('permissions')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                    @error('permissions.*')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div class="flex flex-col sm:flex-row gap-3 pt-3 border-t border-[#e7eaf0]">
                    <x-button type="submit" variant="primary" size="lg">Update User</x-button>
                    <x-button href="{{ route('users.index') }}" variant="outline" size="lg">Batal</x-button>
                </div>
            </form>
        </x-card>
    </div>
@endsection
