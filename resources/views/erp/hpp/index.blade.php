@extends('layouts.app')

@section('content')
@php
$productionRows = collect([
    (object)['production_no'=>'WO-2026-001','date'=>'19/09/2026','product'=>'Batako Standard','bom'=>'Formula Batako 300','qty'=>290,'material_cost'=>750000,'labor_cost'=>203000,'freight_cost'=>50000,'total_cost'=>1003000,'unit_cost'=>3458.62],
    (object)['production_no'=>'WO-2026-002','date'=>'20/09/2026','product'=>'Paving Block','bom'=>'Formula Paving 500','qty'=>480,'material_cost'=>1200000,'labor_cost'=>240000,'freight_cost'=>60000,'total_cost'=>1500000,'unit_cost'=>3125.00],
    (object)['production_no'=>'WO-2026-003','date'=>'20/09/2026','product'=>'Roster Angin','bom'=>'Formula Roster 200','qty'=>195,'material_cost'=>550000,'labor_cost'=>175500,'freight_cost'=>40000,'total_cost'=>765500,'unit_cost'=>3925.64],
    (object)['production_no'=>'WO-2026-004','date'=>'21/09/2026','product'=>'Batako Standard','bom'=>'Formula Batako 300','qty'=>580,'material_cost'=>1500000,'labor_cost'=>406000,'freight_cost'=>100000,'total_cost'=>2006000,'unit_cost'=>3458.62],
    (object)['production_no'=>'WO-2026-005','date'=>'22/09/2026','product'=>'Paving Block','bom'=>'Formula Paving 500','qty'=>960,'material_cost'=>2400000,'labor_cost'=>480000,'freight_cost'=>120000,'total_cost'=>3000000,'unit_cost'=>3125.00],
    (object)['production_no'=>'WO-2026-006','date'=>'22/09/2026','product'=>'Roster Angin','bom'=>'Formula Roster 200','qty'=>390,'material_cost'=>1100000,'labor_cost'=>351000,'freight_cost'=>80000,'total_cost'=>1531000,'unit_cost'=>3925.64],
]);
$from = strtotime($startDate);
$to = strtotime($endDate);
$productionRows = $productionRows->filter(fn($row) => strtotime(str_replace('/', '-', $row->date)) >= $from && strtotime(str_replace('/', '-', $row->date)) <= $to)->values();
$prodTotalQty=$productionRows->sum('qty');
$prodMaterial=$productionRows->sum('material_cost');
$prodLabor=$productionRows->sum('labor_cost');
$prodOverhead=$productionRows->sum('freight_cost');
$prodTotal=$productionRows->sum('total_cost');
@endphp
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1">HPP</h3>
        <div class="text-secondary">Harga Pokok Penjualan & Harga Pokok Hasil Produksi</div>
    </div>
    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
</div>

<div class="card shadow-sm">
    <div class="card-body border-bottom">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label">Tanggal Mulai</label><input type="date" name="start_date" class="form-control" value="{{ $startDate }}"></div>
            <div class="col-md-3"><label class="form-label">Tanggal Akhir</label><input type="date" name="end_date" class="form-control" value="{{ $endDate }}"></div>
            <div class="col-md-auto"><button class="btn btn-primary">Tampilkan</button></div>
        </form>
    </div>
    <div class="card-header bg-white border-0 pt-3">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#hppRetail" type="button">HPP Retail</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#hppProduksi" type="button">HPP Produksi</button></li>
        </ul>
    </div>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="hppRetail">
            <div class="p-3">
                <div class="d-flex justify-content-between align-items-center mb-3"><div><div class="fw-semibold">HPP Retail — Perpetual</div><small class="text-secondary">Cost persediaan berjalan dan HPP setiap barang yang terjual.</small></div><span class="badge text-bg-primary">PERPETUAL</span></div>
                <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Kode Item</th><th>Item</th><th>Satuan</th><th class="text-end">Qty Masuk</th><th class="text-end">Nilai Masuk</th><th class="text-end">Qty Keluar</th><th class="text-end">HPP / Unit</th><th class="text-end">HPP Penjualan</th><th class="text-end">Saldo Qty</th><th class="text-end">Nilai Persediaan</th></tr></thead>
                    <tbody>@forelse($retailRows as $row)<tr><td>{{ $row->code }}</td><td>{{ $row->name }}</td><td>{{ $row->unit }}</td><td class="text-end">{{ number_format($row->qty_in,3,',','.') }}</td><td class="text-end">Rp {{ number_format($row->value_in,0,',','.') }}</td><td class="text-end">{{ number_format($row->qty_out,3,',','.') }}</td><td class="text-end">Rp {{ number_format($row->hpp_unit,0,',','.') }}</td><td class="text-end">Rp {{ number_format($row->hpp_sales,0,',','.') }}</td><td class="text-end">{{ number_format($row->balance_qty,3,',','.') }}</td><td class="text-end">Rp {{ number_format($row->balance_value,0,',','.') }}</td></tr>@empty<tr><td colspan="10" class="text-center text-secondary py-4">Belum ada data HPP Retail. Mesin perhitungan perpetual akan diaktifkan setelah transaksi costing dikunci.</td></tr>@endforelse</tbody>
                </table></div>
                <div class="alert alert-light border mt-3 mb-0"><strong>Formula HPP Retail:</strong> HPP Penjualan = Qty Terjual × HPP / Unit. HPP / Unit mengikuti cost persediaan aktual secara perpetual.</div>
            </div>
        </div>
        <div class="tab-pane fade" id="hppProduksi">
            <div class="p-3">
                <div class="d-flex justify-content-between align-items-center mb-3"><div><div class="fw-semibold">HPP Produksi — Perpetual</div><small class="text-secondary">Cost hasil produksi berdasarkan BOM, pemakaian aktual, dan biaya produksi.</small></div><span class="badge text-bg-success">PERPETUAL</span></div>
                <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>No. Produksi</th><th>Tanggal</th><th>Produk Jadi</th><th>BOM / Formula</th><th class="text-end">Qty Hasil (Bagus)</th><th class="text-end">Bahan Baku (Rp)</th><th class="text-end">Tenaga Kerja (Rp)</th><th class="text-end">Overhead / Angkut (Rp)</th><th class="text-end">Total HPP (Rp)</th><th class="text-end">HPP / Unit (Rp)</th></tr></thead>
                    <tbody>@forelse($productionRows as $row)<tr><td>{{ $row->production_no }}</td><td>{{ $row->date }}</td><td>{{ $row->product }}</td><td>{{ $row->bom }}</td><td class="text-end">{{ number_format($row->qty,0,',','.') }}</td><td class="text-end">Rp {{ number_format($row->material_cost,0,',','.') }}</td><td class="text-end">Rp {{ number_format($row->labor_cost,0,',','.') }}</td><td class="text-end">Rp {{ number_format($row->freight_cost,0,',','.') }}</td><td class="text-end fw-semibold">Rp {{ number_format($row->total_cost,0,',','.') }}</td><td class="text-end fw-semibold">Rp {{ number_format($row->unit_cost,2,',','.') }}</td></tr>@empty<tr><td colspan="10" class="text-center text-secondary py-4">Belum ada data HPP Produksi.</td></tr>@endforelse</tbody>
                    <tfoot class="table-light fw-semibold"><tr><td colspan="4">TOTAL</td><td class="text-end">{{ number_format($prodTotalQty,0,',','.') }}</td><td class="text-end">Rp {{ number_format($prodMaterial,0,',','.') }}</td><td class="text-end">Rp {{ number_format($prodLabor,0,',','.') }}</td><td class="text-end">Rp {{ number_format($prodOverhead,0,',','.') }}</td><td class="text-end">Rp {{ number_format($prodTotal,0,',','.') }}</td><td></td></tr></tfoot>
                </table></div>
                <div class="alert alert-light border mt-3 mb-0"><strong>Formula HPP Produksi:</strong> Total HPP = Bahan Baku + Tenaga Kerja + Overhead / Angkut. HPP / Unit = Total HPP ÷ Qty Hasil (Bagus). BOM / Formula merupakan acuan; HPP final menggunakan biaya aktual produksi.</div>
            </div>
        </div>
    </div>
</div>
@endsection
