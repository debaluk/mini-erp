@extends('layouts.app')

@section('content')
<div class="container-fluid px-0 py-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold text-dark">Setok Persediaan</h3>
            <div class="text-secondary small">Saldo stok terkini per barang dan gudang. Klik kode atau nama barang untuk melihat histori.</div>
        </div>
        <a href="{{ route('inventori.setok-persediaan.export', request()->query()) }}" class="btn btn-success btn-sm text-nowrap"><i class="bi bi-file-earmark-excel me-1"></i> Export Excel</a>
    </div>
    <div class="card shadow-sm border-0">

    <div class="card-body border-bottom py-2">
        <form method="GET" action="{{ route('inventori.setok-persediaan.index') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label mb-1 small fw-bold">Unit Bisnis</label>
                <select name="business_unit_id" class="form-select form-select-sm">
                    <option value="">Semua Unit Bisnis</option>
                    @foreach($businessUnits as $bu)
                        <option value="{{ $bu->id }}" @selected(request('business_unit_id') == $bu->id)>{{ $bu->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1 small fw-bold">Gudang</label>
                <select name="warehouse_id" class="form-select form-select-sm">
                    <option value="">Semua Gudang</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(request('warehouse_id') == $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label mb-1 small fw-bold">Cari Barang</label>
                <input type="search" name="search" class="form-control form-control-sm" value="{{ request('search') }}" placeholder="Kode / SKU / nama barang">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button class="btn btn-primary btn-sm flex-fill text-nowrap">Tampilkan</button>
                <a href="{{ route('inventori.setok-persediaan.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.88rem;">
            <thead class="table-dark">
                <tr><th class="text-center" style="width:55px">No.</th><th>Kode Barang</th><th>Nama Barang</th><th>Satuan</th><th>Unit Bisnis</th><th>Gudang</th><th class="text-end">Stok Tersedia</th></tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td class="text-center">{{ $rows->firstItem() + $loop->index }}</td>
                    <td class="fw-semibold"><a href="{{ route('inventori.setok-persediaan.history', [$row->product_id, $row->warehouse_id]) }}?start_date={{ now()->startOfMonth()->toDateString() }}&end_date={{ now()->toDateString() }}">{{ $row->code ?: $row->sku ?: '-' }}</a></td>
                    <td><a class="text-decoration-none" href="{{ route('inventori.setok-persediaan.history', [$row->product_id, $row->warehouse_id]) }}?start_date={{ now()->startOfMonth()->toDateString() }}&end_date={{ now()->toDateString() }}">{{ $row->product_name }}</a></td>
                    <td>{{ $row->unit_name ?: $row->unit_code ?: '-' }}</td>
                    <td>{{ $row->business_unit_names ?: '-' }}</td>
                    <td>{{ $row->warehouse_name }}</td>
                    <td class="text-end fw-semibold">{{ rtrim(rtrim(number_format((float) $row->qty, 3, ',', '.'), '0'), ',') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-secondary py-4">Data persediaan tidak ditemukan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="small text-secondary">
            @if($rows->total() > 0) Menampilkan {{ $rows->firstItem() }}–{{ $rows->lastItem() }} dari {{ $rows->total() }} barang @else Tidak ada data @endif
        </div>
        @if($rows->lastPage() > 1)
            <nav aria-label="Pagination Setok Persediaan">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item {{ $rows->onFirstPage() ? 'disabled' : '' }}"><a class="page-link" href="{{ $rows->previousPageUrl() ?? '#' }}">‹</a></li>
                    @foreach($rows->getUrlRange(max(1, $rows->currentPage() - 2), min($rows->lastPage(), $rows->currentPage() + 2)) as $page => $url)
                        <li class="page-item {{ $page == $rows->currentPage() ? 'active' : '' }}"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                    @endforeach
                    <li class="page-item {{ $rows->currentPage() >= $rows->lastPage() ? 'disabled' : '' }}"><a class="page-link" href="{{ $rows->nextPageUrl() ?? '#' }}">›</a></li>
                </ul>
            </nav>
        @endif
    </div>
</div>
</div>
@endsection
