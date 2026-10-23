<x-modal name="cancel-purchase-{{ $userPackage->id }}" title="Batalkan Pembelian" maxWidth="md">
    <div class="flex items-start gap-4 text-left">
        <span class="shrink-0 flex items-center justify-center w-10 h-10 rounded-full bg-rose-50 text-rose-500">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </span>
        <div>
            <p class="text-sm font-semibold text-[#071833]">Batalkan paket {{ $userPackage->package?->name }}?</p>
            <p class="mt-1 text-sm text-[#667085] leading-relaxed">Pembelian akan dibatalkan dan tidak bisa
                dilanjutkan. Anda tetap bisa memilih paket lagi nanti.</p>
        </div>
    </div>
    <x-slot name="footer">
        <x-button type="button" variant="outline"
            @click="$dispatch('close-modal-cancel-purchase-{{ $userPackage->id }}')">Kembali</x-button>
        <form method="POST" action="{{ route('packages.payment.cancel', $userPackage) }}">
            @csrf
            <x-button type="submit" variant="danger">Ya, Batalkan</x-button>
        </form>
    </x-slot>
</x-modal>
