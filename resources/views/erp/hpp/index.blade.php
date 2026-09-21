@extends('layouts.app')

@section('content')
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
            <div class="col-md-3">
                <label class="form-label">Tanggal Mulai</label>
                <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tanggal Akhir</label>
                <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Unit Bisnis</label>
                <select name="business_unit_id" class="form-select">
                    <option value="">Semua Unit</option>
                    @foreach($businessUnits as $bu)
                        <option value="{{ $bu->id }}" @selected((int)$businessUnitId === (int)$bu->id)>{{ $bu->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto">
                <button class="btn btn-primary">Tampilkan</button>
            </div>
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
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="fw-semibold">HPP Retail</div>
                        <small class="text-secondary">HPP aktual yang tersimpan pada setiap detail penjualan.</small>
                    </div>
                    <span class="badge text-bg-primary">PERPETUAL / AVG</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>Kode</th><th>Item</th><th>Satuan</th>
                            <th class="text-end">Qty Masuk</th>
                            <th class="text-end">Nilai Masuk</th>
                            <th class="text-end">Qty Keluar</th>
                            <th class="text-end">HPP / Unit</th>
                            <th class="text-end">HPP Penjualan</th>
                            <th class="text-end">Saldo Qty</th>
                            <th class="text-end">Nilai Persediaan</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($retailRows as $row)
                            <tr>
                                <td>{{ $row->code }}</td>
                                <td>{{ $row->name }}</td>
                                <td>{{ $row->unit ?? '-' }}</td>
                                <td class="text-end">{{ number_format((float)$row->qty_in,3,',','.') }}</td>
                                <td class="text-end">Rp {{ number_format((float)$row->value_in,0,',','.') }}</td>
                                <td class="text-end">{{ number_format((float)$row->qty_out,3,',','.') }}</td>
                                <td class="text-end">Rp {{ number_format((float)$row->hpp_unit,4,',','.') }}</td>
                                <td class="text-end">Rp {{ number_format((float)$row->hpp_sales,0,',','.') }}</td>
                                <td class="text-end">{{ number_format((float)$row->balance_qty,3,',','.') }}</td>
                                <td class="text-end">Rp {{ number_format((float)$row->balance_value,0,',','.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center text-secondary py-4">Belum ada transaksi costing pada periode ini.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-light border mt-3 mb-0">
                    <strong>Trace HPP:</strong> setiap penjualan menyimpan <code>hpp_unit</code> dan <code>hpp_total</code> pada <code>sale_items</code>.
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="hppProduksi">
            <div class="p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="fw-semibold">HPP Produksi</div>
                        <small class="text-secondary">Bahan aktual + tenaga kerja + overhead/biaya lain - nilai recoverable reject.</small>
                    </div>
                    <span class="badge text-bg-success">ACTUAL COST</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>No. Produksi</th><th>Tanggal</th><th>Produk Jadi</th><th>BOM</th>
                            <th class="text-end">Qty Bagus</th><th class="text-end">Bahan</th>
                            <th class="text-end">Tenaga Kerja</th><th class="text-end">Overhead/Lain</th>
                            <th class="text-end">Total HPP</th><th class="text-end">HPP / Unit</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($productionRows as $row)
                            <tr>
                                <td>{{ $row->production_no }}</td>
                                <td>{{ CarbonCarbon::parse($row->date)->format('d/m/Y H:i') }}</td>
                                <td><strong>{{ $row->code }}</strong><div class="small text-secondary">{{ $row->product }}</div></td>
                                <td>{{ $row->bom }}</td>
                                <td class="text-end">{{ number_format((float)$row->qty,3,',','.') }}</td>
                                <td class="text-end">Rp {{ number_format((float)$row->material_cost,0,',','.') }}</td>
                                <td class="text-end">Rp {{ number_format((float)$row->labor_cost,0,',','.') }}</td>
                                <td class="text-end">Rp {{ number_format((float)$row->overhead_cost,0,',','.') }}</td>
                                <td class="text-end fw-semibold">Rp {{ number_format((float)$row->total_cost,0,',','.') }}</td>
                                <td class="text-end fw-semibold">Rp {{ number_format((float)$row->unit_cost,4,',','.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center text-secondary py-4">Belum ada produksi pada periode ini.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-light border mt-3 mb-0">
                    <div><strong>Bahan:</strong> pemakaian aktual × HPP bahan saat produksi.</div>
                    <div><strong>Total HPP:</strong> bahan + tenaga kerja + overhead/lain - nilai recoverable reject.</div>
                    <div><strong>HPP/unit:</strong> Total HPP ÷ Qty Barang Jadi Baik.</div>
                    <div><strong>Reject:</strong> scrap tetap membebani biaya; hanya nilai recoverable yang mengurangi cost produksi.</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
