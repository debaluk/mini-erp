@extends('layouts.app')

@section('content')
<style>
    .inventory-report .report-kpi { min-height: 108px; }
    .inventory-report .report-kpi .label { color: #6c757d; font-size: .78rem; }
    .inventory-report .report-kpi .value { font-size: 1.22rem; font-weight: 700; letter-spacing: -.02em; }
    .inventory-report .report-kpi .hint { color: #6c757d; font-size: .72rem; }
    .inventory-report .report-section-title { font-weight: 700; }
    .inventory-report .report-table th { white-space: nowrap; vertical-align: middle; }
    .inventory-report .report-table td { vertical-align: middle; }
    .inventory-report .report-detail { min-width: 1050px; }
    .inventory-report .report-detail td { white-space: nowrap; }
    @media print {
        .no-print { display: none !important; }
        .card { border: 0 !important; box-shadow: none !important; }
        .table-responsive { overflow: visible !important; }
        .inventory-report .report-detail { min-width: 0; }
    }
</style>

<div class="inventory-report">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <h4 class="mb-1">Laporan Persediaan</h4>
            <div class="text-secondary small">Ringkasan nilai, posisi stok, dan pergerakan persediaan untuk manajemen.</div>
        </div>
        <div class="d-flex gap-2 no-print">
            <a href="{{ route('laporan.persediaan.export', request()->query()) }}" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
            </a>
            <button type="button" onclick="window.print()" class="btn btn-outline-secondary">
                <i class="bi bi-printer me-1"></i> Cetak
            </button>
        </div>
    </div>

    <div class="card shadow-sm mb-3 no-print">
        <div class="card-body">
            <form method="GET" action="{{ route('laporan.persediaan') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-sm-6 col-lg-2">
                        <label class="form-label">Mulai Mutasi</label>
                        <input type="date" name="start_date" value="{{ $startDate }}" class="form-control">
                    </div>
                    <div class="col-12 col-sm-6 col-lg-2">
                        <label class="form-label">Akhir Mutasi</label>
                        <input type="date" name="end_date" value="{{ $endDate }}" class="form-control">
                    </div>
                    <div class="col-12 col-sm-6 col-lg-3">
                        <label class="form-label">Unit Bisnis</label>
                        <select name="business_unit_id" class="form-select">
                            <option value="">Semua Unit Bisnis</option>
                            @foreach($businessUnits as $unit)
                                <option value="{{ $unit->id }}" @selected((string) request('business_unit_id') === (string) $unit->id)>{{ $unit->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-lg-2">
                        <label class="form-label">Gudang</label>
                        <select name="warehouse_id" class="form-select">
                            <option value="">Semua Gudang</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}" @selected((string) request('warehouse_id') === (string) $warehouse->id)>{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-lg-2">
                        <label class="form-label">Cari Item</label>
                        <input name="search" value="{{ request('search') }}" class="form-control" placeholder="Kode / nama / SKU">
                    </div>
                    <div class="col-12 col-lg-1 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">Tampilkan</button>
                    </div>
                    <div class="col-12">
                        <a href="{{ route('laporan.persediaan') }}" class="btn btn-sm btn-outline-secondary">Reset Filter</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="row g-2 small">
                <div class="col-12 col-md-3">
                    <div class="text-secondary">Entitas</div>
                    <div class="fw-semibold">{{ $entityName }}</div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="text-secondary">Periode Pergerakan</div>
                    <div class="fw-semibold">{{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="text-secondary">Unit Bisnis</div>
                    <div class="fw-semibold">{{ $selectedUnitName }}</div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="text-secondary">Gudang</div>
                    <div class="fw-semibold">{{ $selectedWarehouseName }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card shadow-sm report-kpi h-100"><div class="card-body">
                <div class="label">Nilai Persediaan Saat Ini</div>
                <div class="value">Rp {{ number_format((float) $summary->stock_value, 0, ',', '.') }}</div>
                <div class="hint">Kuantitas saldo × HPP rata-rata</div>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <div class="card shadow-sm report-kpi h-100"><div class="card-body">
                <div class="label">Jenis Item</div>
                <div class="value">{{ number_format((int) $summary->item_count, 0, ',', '.') }}</div>
                <div class="hint">Item unik pada filter</div>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <div class="card shadow-sm report-kpi h-100"><div class="card-body">
                <div class="label">Lokasi Stok Nol/Minus</div>
                <div class="value">{{ number_format((int) $summary->zero_or_negative, 0, ',', '.') }}</div>
                <div class="hint">Item per gudang</div>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-2">
            <div class="card shadow-sm report-kpi h-100"><div class="card-body">
                <div class="label">Barang Masuk</div>
                <div class="value">{{ number_format($movement['qtyIn'], 3, ',', '.') }}</div>
                <div class="hint">Akumulasi kuantitas mutasi</div>
            </div></div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card shadow-sm report-kpi h-100"><div class="card-body">
                <div class="label">Barang Keluar</div>
                <div class="value">{{ number_format($movement['qtyOut'], 3, ',', '.') }}</div>
                <div class="hint">Akumulasi kuantitas mutasi</div>
            </div></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-header report-section-title">Nilai Persediaan per Gudang</div>
                <div class="table-responsive">
                    <table class="table table-hover report-table mb-0">
                        <thead class="table-light"><tr><th>Gudang</th><th class="text-end">Lokasi Item</th><th class="text-end">Nilai Persediaan</th></tr></thead>
                        <tbody>
                        @forelse($byWarehouse as $warehouse)
                            <tr>
                                <td class="fw-semibold">{{ $warehouse->warehouse_name }}</td>
                                <td class="text-end">{{ number_format((int) $warehouse->stock_lines, 0, ',', '.') }}</td>
                                <td class="text-end fw-semibold">Rp {{ number_format((float) $warehouse->stock_value, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-secondary py-4">Belum ada saldo persediaan.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-header report-section-title">10 Posisi Stok Bernilai Terbesar</div>
                <div class="table-responsive">
                    <table class="table table-hover report-table mb-0">
                        <thead class="table-light"><tr><th>Item</th><th>Gudang</th><th class="text-end">Qty</th><th class="text-end">Nilai</th></tr></thead>
                        <tbody>
                        @forelse($topStock as $item)
                            <tr>
                                <td><div class="fw-semibold">{{ $item->product_name }}</div><div class="small text-secondary">{{ $item->code ?: $item->sku ?: '-' }}</div></td>
                                <td>{{ $item->warehouse_name }}</td>
                                <td class="text-end">{{ number_format((float) $item->qty, 3, ',', '.') }} {{ $item->unit_code }}</td>
                                <td class="text-end fw-semibold">Rp {{ number_format((float) $item->stock_value, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-secondary py-4">Belum ada data stok.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div><div class="report-section-title">Rincian Posisi Persediaan</div><div class="small text-secondary">Saldo saat ini per item dan gudang, menggunakan satuan dasar item.</div></div>
            <div class="small text-secondary">Menampilkan {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} dari {{ $rows->total() }} baris</div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover report-table report-detail mb-0">
                <thead class="table-light">
                    <tr><th>No.</th><th>Kode Item</th><th>Nama Item</th><th>Satuan Dasar</th><th>Unit Bisnis</th><th>Gudang</th><th class="text-end">Qty</th><th class="text-end">HPP Rata-rata</th><th class="text-end">Nilai Stok</th></tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                        <td class="fw-semibold">{{ $row->code ?: $row->sku ?: '-' }}</td>
                        <td>{{ $row->product_name }}</td>
                        <td>{{ $row->unit_name ?: $row->unit_code ?: '-' }}</td>
                        <td>{{ $row->business_unit_names ?: '-' }}</td>
                        <td>{{ $row->warehouse_name }}</td>
                        <td class="text-end">{{ number_format((float) $row->qty, 3, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format((float) $row->avg_cost, 0, ',', '.') }}</td>
                        <td class="text-end fw-semibold">Rp {{ number_format((float) $row->stock_value, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-secondary py-4">Tidak ada data persediaan sesuai filter.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($rows->hasPages())
            <div class="card-footer no-print">{{ $rows->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>

    <div class="text-secondary small mt-2">Catatan: nilai persediaan merupakan ringkasan saldo saat ini, bukan saldo historis pada akhir periode. Periode di atas digunakan untuk ringkasan mutasi masuk dan keluar.</div>
</div>
@endsection
