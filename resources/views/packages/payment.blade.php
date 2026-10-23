@extends('layouts.app')

@section('title', 'Pembayaran Paket')
@section('header', 'Pembayaran Paket')

@section('content')
    <div class="mx-auto max-w-xl">
        <x-card>
            <x-slot name="header">
                <div class="text-center">
                    <span
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#c99a3e]/10 ring-1 ring-[#c99a3e]/25 text-[11px] font-bold uppercase tracking-wider text-[#8c6a25]">
                        QRIS Static
                    </span>
                    <h3 class="mt-3 text-xl font-bold text-[#071833]">Bayar Paket {{ $userPackage->package->name }}</h3>
                    <p class="text-sm text-[#667085] mt-1">Scan QRIS di bawah untuk menyelesaikan pembayaran.</p>
                </div>
            </x-slot>

            <div class="mt-4 flex flex-col items-center text-center">
                <p class="text-4xl font-bold text-[#071833]">
                    Rp<span class="text-[#c99a3e]">{{ $userPackage->package->price }}</span>
                    <span class="text-sm font-semibold text-[#667085]">{{ $userPackage->package->price_period }}</span>
                </p>

                <div class="mt-6 p-4 rounded-2xl bg-white ring-1 ring-[#e7eaf0]">
                    @if (file_exists(public_path('qris/qris.png')))
                        <img src="{{ asset('qris/qris.png') }}"
                            alt="QRIS {{ $userPackage->package->name }}" class="w-52 h-52 object-contain rounded-xl">
                    @else
                        <div class="w-52 h-52 rounded-xl bg-[#f6f8fb] ring-2 ring-dashed ring-[#e7eaf0] grid place-items-center text-xs text-[#667085] p-4">
                            Gambar QRIS belum tersedia.<br>Taruh file di <span class="font-mono">public/qris/qris.png</span>
                        </div>
                    @endif
                </div>

                <p class="mt-4 text-xs text-[#667085]">Scan QRIS di atas untuk menyelesaikan pembayaran.</p>

                <div class="mt-6 w-full rounded-2xl bg-[#faf7ef] ring-1 ring-[#e7e0cd] p-4 text-xs text-[#667085] leading-relaxed">
                    <p class="font-bold text-[#071833] mb-1">Cara membayar:</p>
                    <ol class="list-decimal ml-4 space-y-1">
                        <li>Buka aplikasi perbankan / e-wallet (GoPay, OVO, DANA, dst.).</li>
                        <li>Pilih menu QRIS / Scan.</li>
                        <li>Scan kode QRIS di atas.</li>
                        <li>Setelah transfer, upload bukti pembayaran di bawah.</li>
                        <li>Menunggu konfirmasi admin sebelum paket aktif.</li>
                    </ol>
                </div>

                @if ($userPackage->status === 'active')
                    <div class="mt-6 w-full rounded-2xl bg-emerald-50 ring-1 ring-emerald-200 p-5 text-center">
                        <p class="text-sm font-bold text-emerald-700">Paket Anda sudah aktif.</p>
                        <a href="{{ route('dashboard') }}" class="mt-3 inline-block text-xs font-semibold text-emerald-700 underline">Ke Dashboard</a>
                    </div>
                @elseif ($userPackage->status === 'cancelled')
                    <div class="mt-6 w-full rounded-2xl bg-rose-50 ring-1 ring-rose-200 p-5 text-center">
                        <p class="text-sm font-bold text-rose-600">Pembelian paket ini sudah dibatalkan.</p>
                    </div>
                @elseif ($userPackage->payment_proof)
                    <div class="mt-6 w-full rounded-2xl bg-amber-50 ring-1 ring-amber-200 p-5 text-center">
                        <p class="text-sm font-bold text-amber-700">Bukti pembayaran terkirim.</p>
                        <p class="mt-1 text-xs text-amber-700/80">Menunggu konfirmasi admin. Paket aktif setelah diverifikasi.</p>
                        <img src="{{ asset('storage/'.$userPackage->payment_proof) }}" alt="Bukti pembayaran"
                            class="mx-auto mt-3 w-40 h-40 object-contain rounded-xl ring-1 ring-amber-200 bg-white">
                    </div>
                @else
                    <form method="POST" action="{{ route('packages.payment.submit', $userPackage) }}"
                        class="mt-6 w-full" enctype="multipart/form-data">
                        @csrf
                        <div class="w-full rounded-2xl border-2 border-dashed border-[#e7eaf0] bg-[#fafbfd] p-5">
                            <label for="payment_proof" class="block text-center cursor-pointer">
                                <span class="text-xs font-semibold text-[#071833]">Upload Bukti Pembayaran</span>
                                <span class="block mt-1 text-[11px] text-[#667085]">Foto/screenshot transfer (jpg/png, maks 5MB)</span>
                                <input type="file" name="payment_proof" id="payment_proof" required accept="image/*"
                                    class="mx-auto mt-3 block text-xs text-[#667085] file:mr-3 file:rounded-lg file:border-0 file:bg-[#f6f8fb] file:px-3 file:py-2 file:text-xs file:font-bold file:text-[#071833]">
                            </label>
                            @error('payment_proof')
                                <p class="mt-1.5 text-xs font-medium text-rose-600 text-center">{{ $message }}</p>
                            @enderror
                        </div>
                        <x-button type="submit" variant="primary" size="lg" class="w-full mt-4">
                            Kirim Bukti Pembayaran
                        </x-button>
                    </form>
                    @if ($userPackage->awaitingPayment())
                        <button type="button" @click="$dispatch('open-modal-cancel-purchase-{{ $userPackage->id }}')"
                            class="mt-3 w-full text-xs font-semibold text-rose-600 hover:text-rose-700 transition">Batalkan
                            pembelian</button>
                        @include('packages.partials.cancel-modal')
                    @endif
                @endif

                <a href="{{ route('profile.edit') }}"
                    class="mt-3 text-xs font-semibold text-[#667085] hover:text-[#c99a3e] transition">Ganti paket</a>
            </div>
        </x-card>
    </div>
@endsection