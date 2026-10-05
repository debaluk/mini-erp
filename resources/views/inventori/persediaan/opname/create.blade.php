@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold text-dark">
                <i class="bi bi-camera me-2 text-primary"></i>
                {{ isset($opname) ? 'Update Snapshot Stock Opname' : 'Tahap 1: Buat Snapshot Stock Opname' }}
            </h3>
            <div class="text-secondary small">Langkah pertama: Penguncian snapshot stok sistem (system_qty) berdasarkan gudang terpilih</div>
        </div>
        <a href="{{ route('inventori.stock-opname.index') }}" class="btn btn-outline-secondary btn-sm px-3">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke List SO
        </a>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form action="{{ isset($opname) ? route('inventori.stock-opname.update', $opname->id) : route('inventori.stock-opname.store-snapshot') }}" method="POST">
                @csrf
                @if(isset($opname))
                    @method('PUT')
                @endif
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">No. Opname (Otomatis)</label>
                        <input type="text" class="form-control font-monospace fw-bold bg-light" value="{{ $autoCode }}" readonly>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Unit Bisnis <span class="text-danger">*</span></label>
                        <select name="business_unit_id" class="form-select" required>
                            <option value="">-- Pilih Unit Bisnis --</option>
                            @foreach($businessUnits as $bu)
                                <option value="{{ $bu->id }}" {{ isset($opname) && $opname->business_unit_id == $bu->id ? 'selected' : '' }}>{{ $bu->code }} - {{ $bu->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Tgl. Opname <span class="text-danger">*</span></label>
                        <x-date-input-id name="opname_date" :value="isset($opname) ? $opname->opname_date : date('Y-m-d')" required />
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Lokasi Gudang <span class="text-danger">*</span></label>
                        <select name="warehouse_id" class="form-select" required>
                            <option value="">-- Pilih Gudang Freeze --</option>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}" {{ isset($opname) && $opname->warehouse_id == $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="p-3 bg-light rounded border mb-4">
                    <h6 class="fw-bold text-dark mb-1"><i class="bi bi-info-circle me-1 text-info"></i> Penjelasan Prosedur Snapshot:</h6>
                    <ul class="small text-muted mb-0">
                        <li>Sistem akan secara otomatis mengambil seluruh saldo stok sistem (<code>system_qty</code>) dari tabel <code>warehouses_stocks</code> untuk gudang yang Anda pilih saat ini.</li>
                        <li>Dokumen SO akan disimpan terlebih dahulu sebagai <strong>DRAFT / SNAPSHOT</strong>.</li>
                        <li>Setelah disimpan, Anda dapat mencetak <strong>Lembar Hitung Fisik (Blind Count Sheet)</strong> untuk petugas gudang, kemudian melanjutkan ke <strong>Tahap 2 (Input Hasil Fisik)</strong>.</li>
                    </ul>
                </div>

                <div class="text-end">
                    <a href="{{ route('inventori.stock-opname.index') }}" class="btn btn-secondary me-2">Batal</a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        <i class="bi bi-save me-1"></i>
                        {{ isset($opname) ? 'Update Snapshot' : 'Simpan & Freeze Snapshot Stok' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection