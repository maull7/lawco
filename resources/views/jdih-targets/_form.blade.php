<div>
    <label for="name" class="mb-2 block text-sm font-semibold text-[#071833]">Nama Website <span class="text-[#c99a3e]">*</span></label>
    <input type="text" name="name" id="name" value="{{ old('name', $target?->name) }}" required class="input-premium" placeholder="Contoh: JDIH Komdigi">
    @error('name')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
</div>

<div>
    <label for="source" class="mb-2 block text-sm font-semibold text-[#071833]">Source Scraper <span class="text-[#c99a3e]">*</span></label>
    <input type="text" name="source" id="source" value="{{ old('source', $target?->source) }}" required class="input-premium" placeholder="Contoh: jdih_komdigi">
    <p class="mt-1 text-xs text-[#667085]">Harus sama dengan nilai source yang dihasilkan scraper.</p>
    @error('source')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
</div>

<div>
    <label for="target_url" class="mb-2 block text-sm font-semibold text-[#071833]">Target URL <span class="text-[#c99a3e]">*</span></label>
    <input type="url" name="target_url" id="target_url" value="{{ old('target_url', $target?->target_url) }}" required class="input-premium" placeholder="https://jdih.example.go.id">
    @error('target_url')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
</div>

<div>
    <label for="sector_id" class="mb-2 block text-sm font-semibold text-[#071833]">Sektor <span class="text-[#c99a3e]">*</span></label>
    <select name="sector_id" id="sector_id" required class="select-premium">
        <option value="">-- Pilih sektor --</option>
        @foreach ($sectors as $sector)
            <option value="{{ $sector->id }}" @selected((string) old('sector_id', $target?->sector_id) === (string) $sector->id)>{{ $sector->name }} (#{{ $sector->id }})</option>
        @endforeach
    </select>
    @error('sector_id')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
</div>

<div>
    <input type="hidden" name="is_active" value="0">
    <label class="flex items-center gap-2 text-sm font-semibold text-[#071833]">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $target?->is_active ?? true)) class="rounded border-gray-300 text-[#c99a3e] focus:ring-[#c99a3e]">
        Aktifkan target
    </label>
    @error('is_active')<p class="mt-1.5 text-xs font-medium text-rose-600">{{ $message }}</p>@enderror
</div>
