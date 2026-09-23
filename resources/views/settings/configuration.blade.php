@extends('layouts.app')

@section('content')
<div class="mb-4">
    <h3 class="mb-1">Konfigurasi</h3>
    <div class="text-secondary">Pengaturan dasar cara kerja sistem ERP.</div>
</div>

<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#mapping-coa" type="button" role="tab">
            Mapping COA
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#nota-invoice" type="button" role="tab">
            Setup Nota/Invoice
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#nomor-dokumen" type="button" role="tab">
            Nomor Dokumen
        </button>
    </li>
</ul>

@if(session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger py-2">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="tab-content">
    <div class="tab-pane fade show active" id="mapping-coa" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Mapping COA Unit Bisnis</div>
            <div class="card-body">
                <p class="text-secondary mb-3">
                    Atur akun COA berdasarkan kebutuhan transaksi masing-masing Unit Bisnis.
                </p>
                <a href="{{ route('pengaturan.account-mapping') }}" class="btn btn-primary">
                    Buka Mapping COA
                </a>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="nota-invoice" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Setup Nota/Invoice</div>
            <div class="card-body text-secondary">
                Setup Nota/Invoice disiapkan pada tahap berikutnya.
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="nomor-dokumen" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Nomor Dokumen</div>
            <div class="card-body text-secondary">
                Setup Nomor Dokumen disiapkan pada tahap berikutnya.
            </div>
        </div>
    </div>
</div>
@endsection
