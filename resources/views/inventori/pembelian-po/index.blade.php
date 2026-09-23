@extends('layouts.app')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-1">Purchase Order</h4>
        <div class="text-secondary small">Kelola transaksi Purchase Order.</div>
    </div>
    <a href="{{ route('inventori.pembelian-po.create') }}" class="btn btn-primary btn-sm">
        + Buat PO
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold mb-1">Mulai</label>
                <input type="date" name="start_date" class="form-control form-control-sm"
                       value="{{ request('start_date', now()->startOfMonth()->toDateString()) }}">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold mb-1">Sampai</label>
                <input type="date" name="end_date" class="form-control form-control-sm"
                       value="{{ request('end_date', now()->endOfMonth()->toDateString()) }}">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold mb-1">Cari No. PO / Vendor</label>
                <input type="search" name="search" class="form-control form-control-sm"
                       value="{{ request('search') }}" placeholder="Cari...">
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label small fw-semibold mb-1">Business Unit</label>
                <select name="unit_id" class="form-select form-select-sm">
                    <option value="">Semua Business Unit</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}" @selected((string) request('unit_id') === (string) $unit->id)>
                            {{ $unit->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-1">
                <button class="btn btn-outline-secondary btn-sm w-100" type="submit">Terapkan</button>
            </div>
        </form>

        <div class="d-flex justify-content-end mb-2">
            <div class="dropdown">
                <button class="btn btn-outline-success btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    Export PO
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><button type="button" class="dropdown-item disabled">Excel</button></li>
                    <li><button type="button" class="dropdown-item disabled">PDF</button></li>
                </ul>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center" style="width:55px;">No</th>
                        <th>No. PO</th>
                        <th>Tanggal</th>
                        <th>Vendor</th>
                        <th>Business Unit</th>
                        <th class="text-end">Total</th>
                        <th class="text-center" style="width:120px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="fw-semibold text-secondary">Belum ada data Purchase Order</div>
                            <div class="small text-secondary mt-1">Klik <strong>+ Buat PO</strong> untuk membuat Purchase Order baru.</div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 small text-secondary">
            <span>Menampilkan 0 dari 0 PO</span>
            <nav aria-label="Pagination">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item disabled"><span class="page-link">‹</span></li>
                    <li class="page-item active"><span class="page-link">1</span></li>
                    <li class="page-item disabled"><span class="page-link">›</span></li>
                </ul>
            </nav>
        </div>
    </div>
</div>
@endsection
