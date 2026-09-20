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
            <div class="col-md-auto">
                <button class="btn btn-primary">Tampilkan</button>
            </div>
        </form>
    </div>

    <div class="card-header bg-white border-0 pt-3">
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#hppRetail" type="button">HPP Retail</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#hppProduksi" type="button">HPP Produksi</button>
            </li>
        </ul>
    </div>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="hppRetail">
            <div class="p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="fw-semibold">HPP Retail — Perpetual</div>
                        <small class="text-secondary">Cost persediaan berjalan dan HPP setiap barang yang terjual.</small>
                    </div>
                    <span class="badge text-bg-primary">PERPETUAL</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Kode Item</th>
                                <th>Item</th>
                                <th>Satuan</th>
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
                                <td>{{ $row->unit }}</td>
                                <td class="text-end">{{ number_format($row->qty_in, 3, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($row->value_in, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($row->qty_out, 3, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($row->hpp_unit, 0, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($row->hpp_sales, 0, ',', '.') }}</td>
                                <td class="text-end">{{ number_format($row->balance_qty, 3, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($row->balance_value, 0, ',', '.') }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="10" class="text-center text-secondary py-4">Belum ada data HPP Retail. Mesin perhitungan perpetual akan diaktifkan setelah transaksi costing dikunci.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="hppProduksi">
            <div class="p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <div class="fw-semibold">HPP Produksi — Perpetual</div>
                        <small class="text-secondary">Cost hasil produksi berdasarkan BOM, pemakaian aktual, dan biaya produksi.</small>
                    </div>
                    <span class="badge text-bg-success">PERPETUAL</span>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No. Produksi</th>
                                <th>Tanggal</th>
                                <th>Produk Jadi</th>
                                <th>BOM / Formula</th>
                                <th class="text-end">Qty Hasil</th>
                                <th class="text-end">Bahan Baku</th>
                                <th class="text-end">Tenaga Kerja</th>
                                <th class="text-end">Overhead</th>
                                <th class="text-end">Total HPP</th>
                                <th class="text-end">HPP / Unit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($productionRows as $row)
                            <tr>
                                <td>{{ $row->production_no }}</td>
                                <td>{{ $row->date }}</td>
                                <td>{{ $row->product }}</td>
                                <td>{{ $row->bom }}</td>
                                <td class="text-end">{{ number_format($row->qty, 3, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($row->material_cost, 0, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($row->labor_cost, 0, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($row->freight_cost, 0, ',', '.') }}</td>
                                <td class="text-end fw-semibold">Rp {{ number_format($row->total_cost, 0, ',', '.') }}</td>
                                <td class="text-end fw-semibold">Rp {{ number_format($row->unit_cost, 0, ',', '.') }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="10" class="text-center text-secondary py-4">Belum ada data HPP Produksi. Perhitungan akan mengikuti BOM dan realisasi biaya produksi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
