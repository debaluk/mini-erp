@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold text-dark"><i class="bi bi-clipboard-check me-2 text-success"></i> Detail Laporan Hasil Stock Opname</h3>
            <div class="text-secondary small">Dokumen: <strong class="font-monospace text-primary">{{ $opname->opname_no }}</strong></div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('inventori.stock-opname.print-report', $opname->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-printer me-1"></i> Cetak Laporan SO
            </a>
            <a href="{{ route('inventori.stock-opname.export-detail', $opname->id) }}" class="btn btn-success btn-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel Detail
            </a>
            <a href="{{ route('inventori.stock-opname.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke List
            </a>
        </div>
    </div>

    <!-- Info Header Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted small">No. Dokumen Opname</div>
                    <div class="fw-bold font-monospace fs-6 text-primary">{{ $opname->opname_no }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Tanggal Opname</div>
                    <div class="fw-bold">{{ \Carbon\Carbon::parse($opname->opname_date)->format('d/m/Y') }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Lokasi Gudang</div>
                    <div class="fw-bold text-dark">{{ $opname->warehouse_name }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Petugas Opname</div>
                    <div class="fw-bold">{{ $opname->creator_name }}</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Items -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white py-2">
            <span class="fw-bold small"><i class="bi bi-list-check me-1"></i> Rincian Perbandingan Stok Sistem vs Hasil Fisik</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.88rem;">
                    <thead class="table-light text-center">
                        <tr>
                            <th style="width: 40px;">No</th>
                            <th style="width: 140px;">Kode Barang</th>
                            <th class="text-start">Nama Produk</th>
                            <th style="width: 130px;">Stok Sistem</th>
                            <th style="width: 130px;">Stok Fisik</th>
                            <th style="width: 130px;">Selisih (Varian)</th>
                            <th style="width: 90px;">Satuan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $idx => $i)
                            @php $diff = (float) $i->difference; @endphp
                            <tr>
                                <td class="text-center">{{ $idx + 1 }}</td>
                                <td class="font-monospace text-center fw-semibold">{{ $i->product_code }}</td>
                                <td><div class="fw-semibold text-dark">{{ $i->product_name }}</div></td>
                                <td class="text-center">{{ number_format($i->system_qty, 2, ',', '.') }}</td>
                                <td class="text-center fw-bold">{{ number_format($i->actual_qty, 2, ',', '.') }}</td>
                                <td class="text-center fw-bold {{ $diff < 0 ? 'text-danger' : ($diff > 0 ? 'text-warning' : 'text-success') }}">
                                    {{ $diff > 0 ? '+' . number_format($diff, 2, ',', '.') : number_format($diff, 2, ',', '.') }}
                                </td>
                                <td class="text-center">{{ $i->unit_name ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($opname->status === 'draft')
        <form id="formUpdateSO" action="{{ route('inventori.stock-opname.update', $opname->id) }}" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="business_unit_id" value="{{ $opname->business_unit_id }}">
            <input type="hidden" name="warehouse_id" value="{{ $opname->warehouse_id }}">
            <input type="hidden" name="opname_date" value="{{ $opname->opname_date }}">
        </form>

        <div class="card shadow-sm border-0 mt-3">
            <div class="card-footer bg-light d-flex justify-content-between align-items-center">
                <a href="{{ route('inventori.stock-opname.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke List
                </a>

                <div class="d-flex align-items-center gap-3">
                    <label class="form-check mb-0">
                        <input type="checkbox" name="approve_post" value="1" form="formUpdateSO" class="form-check-input">
                        <span class="form-check-label fw-bold">Approve &amp; Post</span>
                    </label>

                    <button type="submit" form="formUpdateSO" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Simpan
                    </button>
                </div>
            </div>
        </div>
    @else
        <div class="card shadow-sm border-0 mt-3">
            <div class="card-footer bg-light text-end">
                <a href="{{ route('inventori.stock-opname.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke List
                </a>
            </div>
        </div>
    @endif

</div>
@endsection