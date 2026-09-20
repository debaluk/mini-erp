@extends('layouts.app')

@section('content')
<div class="mb-4">
    <h3 class="mb-1">Konfigurasi</h3>
    <div class="text-secondary">Pengaturan dasar cara kerja sistem ERP.</div>
</div>

<div class="border-bottom mb-4">
    <ul class="nav nav-tabs border-0">
        <li class="nav-item">
            <a class="nav-link active fw-semibold" href="{{ route('pengaturan.unit-bisnis') }}">
                Setup Unit
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#nota-invoice" data-bs-toggle="tab">
                Setup Nota/Invoice
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="#nomor-dokumen" data-bs-toggle="tab">
                Nomor Dokumen
            </a>
        </li>
    </ul>
</div>

<div class="tab-content">
    <div class="tab-pane fade show active" id="setup-unit">
        <div class="mb-3">
            <h5 class="mb-1">Setup Unit</h5>
            <div class="text-secondary small">
                Atur Unit Bisnis, Tipe Usaha, Metode HPP, Akun HPP, dan status unit.
            </div>
        </div>

        <a href="{{ route('pengaturan.unit-bisnis') }}" class="btn btn-primary">
            Buka Setup Unit
        </a>
    </div>

    <div class="tab-pane fade" id="nota-invoice">
        <div class="mb-3">
            <h5 class="mb-1">Setup Nota/Invoice</h5>
            <div class="text-secondary small">
                Pengaturan identitas, header, dan format cetak nota/invoice.
            </div>
        </div>

        <div class="alert alert-light border mb-0">
            Setup Nota/Invoice disiapkan pada tahap berikutnya.
        </div>
    </div>

    <div class="tab-pane fade" id="nomor-dokumen">
        <div class="mb-3">
            <h5 class="mb-1">Nomor Dokumen</h5>
            <div class="text-secondary small">
                Pengaturan format dan penomoran dokumen transaksi.
            </div>
        </div>

        <div class="alert alert-light border mb-0">
            Setup Nomor Dokumen disiapkan pada tahap berikutnya.
        </div>
    </div>
</div>
@endsection
