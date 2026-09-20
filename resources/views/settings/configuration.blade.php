@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1">Konfigurasi</h3>
        <div class="text-secondary">Pengaturan cara kerja sistem ERP.</div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="mb-1">Setup Unit Bisnis</h5>
                <p class="text-secondary small mb-3">Atur unit usaha, tipe usaha, metode HPP, dan status unit.</p>
                <a href="{{ route('pengaturan.unit-bisnis') }}" class="btn btn-primary btn-sm">Buka Setup</a>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="mb-1">Setup Mapping Account</h5>
                <p class="text-secondary small mb-3">Mapping akun untuk proses HPP dan transaksi otomatis.</p>
                <a href="{{ route('pengaturan.account-mapping') }}" class="btn btn-primary btn-sm">Buka Setup</a>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="mb-1">Setup Cetak Invoice</h5>
                <p class="text-secondary small mb-0">Pengaturan header dan identitas cetak dokumen.</p>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="mb-1">Setup Nomor Dokumen</h5>
                <p class="text-secondary small mb-0">Pengaturan format dan penomoran dokumen transaksi.</p>
            </div>
        </div>
    </div>

    <div class="col-md-6 col-xl-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="mb-1">Setup Periode Akuntansi</h5>
                <p class="text-secondary small mb-0">Pengaturan periode, Stock Opname, finalisasi HPP, dan Closing.</p>
            </div>
        </div>
    </div>
</div>
@endsection
