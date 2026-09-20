@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1">Setup Mapping Account</h3>
        <div class="text-secondary">Tentukan akun HPP utama yang digunakan seluruh Unit Bisnis.</div>
    </div>
    <a href="{{ route('pengaturan.konfigurasi') }}" class="btn btn-outline-secondary">Kembali</a>
</div>

@if(session('success')) <div class="alert alert-success py-2">{{ session('success') }}</div> @endif
@if($errors->any())
<div class="alert alert-danger py-2"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="alert alert-info border small">
    <strong>Konsep Mapping HPP</strong><br>
    Semua Unit Bisnis menggunakan satu akun HPP utama:
    <strong>50001 — Harga Pokok Pendapatan</strong>.
    Tipe Usaha dan Metode HPP menentukan cara perhitungan cost, bukan akun HPP yang digunakan.
    Beban langsung tetap merupakan akun beban dalam kelompok HPP pada Laba Rugi dan bukan akun HPP utama.
</div>

@forelse($units as $unit)
<div class="card shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <strong>{{ $unit->code }} — {{ $unit->name }}</strong>
            <span class="badge text-bg-secondary ms-2">
                {{ ['retail'=>'Retail','production'=>'Produksi','service'=>'Jasa'][$unit->business_type] ?? $unit->business_type }}
            </span>
        </div>
        <span class="text-secondary small">
            {{ $unit->hpp_method === 'periodic' ? 'Periodik' : 'Perpetual — Moving Average' }}
        </span>
    </div>

    <div class="card-body">
        <div class="row align-items-end g-3">
            <div class="col-md-8">
                <label class="form-label">Akun HPP Utama</label>
                <div class="form-control bg-light">
                    @if($hppAccount)
                        {{ $hppAccount->code }} — {{ $hppAccount->name }}
                    @else
                        <span class="text-danger">50001 — Harga Pokok Pendapatan belum tersedia</span>
                    @endif
                </div>
                <div class="form-text">
                    Akun ini berlaku untuk semua tipe usaha. Tidak ada pilihan akun HPP lain.
                </div>
            </div>

            <div class="col-md-4">
                <form method="POST" action="{{ route('pengaturan.account-mapping.save', $unit->id) }}">
                    @csrf
                    <button class="btn btn-primary w-100" {{ $hppAccount ? '' : 'disabled' }}>
                        Simpan Mapping HPP
                    </button>
                </form>
            </div>
        </div>

        @if(isset($mappings[$unit->id]))
            <div class="small text-success mt-3">
                ✓ Mapping HPP sudah tersimpan.
            </div>
        @else
            <div class="small text-warning mt-3">
                ⚠ Mapping HPP belum disimpan untuk Unit Bisnis ini.
            </div>
        @endif
    </div>
</div>
@empty
<div class="alert alert-light border">Belum ada Unit Bisnis. Buat Unit Bisnis terlebih dahulu.</div>
@endforelse

<div class="alert alert-light border small">
    <strong>Catatan:</strong> biaya langsung seperti tenaga kerja, bahan langsung, dan jasa angkut tetap diperlakukan sebagai akun beban dalam Group HPP sesuai konfigurasi COA. Akun tersebut tidak dipetakan sebagai akun HPP utama.
</div>
@endsection