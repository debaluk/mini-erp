@extends('layouts.app')

@section('content')
<div class="card shadow-sm">
    <div class="card-header">
        <div class="fw-semibold">SETOK PERSEDIAAN</div>
        <div class="small text-secondary">Saldo stok terkini per item dan gudang. Klik kode atau nama item untuk melihat histori.</div>
    </div>
    <div class="card-body border-bottom py-2">
        <form method="GET" class="row align-items-end">
            <div class="col-md-3">
                <label class="form-label">Unit Bisnis</label>
                <select name="business_unit_id" class="form-select">
                    <option value="">Semua Unit Bisnis</option>
                    @foreach($businessUnits as $bu)
                        <option value="{{ $bu->id }}" @selected(request('business_unit_id') == $bu->id)>{{ $bu->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Gudang</label>
                <select name="warehouse_id" class="form-select">
                    <option value="">Semua Gudang</option>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(request('warehouse_id') == $warehouse->id)>{{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Cari Barang</label>
                <input type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="Kode / SKU / nama barang">
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button class="btn btn-primary flex-fill">Tampilkan</button>
                <a href="{{ route('inventori.setok-persediaan.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr><th style="width:55px">No.</th><th>Kode Barang</th><th>Nama Barang</th><th>Satuan</th><th>Unit Bisnis</th><th>Gudang</th><th class="text-end">Stok Tersedia</th></tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $rows->firstItem() + $loop->index }}</td>
                    <td class="fw-semibold"><a href="{{ route('inventori.setok-persediaan.history', [$row->product_id, $row->warehouse_id]) }}?start_date={{ now()->startOfMonth()->toDateString() }}&end_date={{ now()->toDateString() }}">{{ $row->code ?: $row->sku ?: '-' }}</a></td>
                    <td><a class="text-decoration-none" href="{{ route('inventori.setok-persediaan.history', [$row->product_id, $row->warehouse_id]) }}?start_date={{ now()->startOfMonth()->toDateString() }}&end_date={{ now()->toDateString() }}">{{ $row->product_name }}</a></td>
                    <td>{{ $row->unit_name ?: $row->unit_code ?: '-' }}</td>
                    <td>{{ $row->business_unit_names ?: '-' }}</td>
                    <td>{{ $row->warehouse_name }}</td>
                    <td class="text-end fw-semibold">{{ number_format((float) $row->qty, 3, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-secondary py-4">Data persediaan tidak ditemukan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="small text-secondary">
            @if($rows->total()) Menampilkan {{ $rows->firstItem() }}–{{ $rows->lastItem() }} dari {{ $rows->total() }} baris @else Tidak ada data @endif
        </div>
        {{ $rows->links() }}
    </div>
</div>
@endsection
