@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-1">Histori Harga Pokok Pembelian</h4>
            <div class="text-muted small">Perbandingan harga PO, penerimaan, dan faktur. Laporan ini hanya menampilkan histori dan tidak mengubah stok atau jurnal.</div>
        </div>
    </div>

    <form method="GET" action="{{ route('inventori.pembelian.histori-harga') }}" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label for="search" class="form-label">Cari barang / kode / PO / supplier</label>
                <input id="search" name="search" class="form-control" value="{{ $filters['search'] ?? '' }}" placeholder="Ketik kata kunci">
            </div>
            <div class="col-md-3">
                <label for="product_id" class="form-label">Barang</label>
                <select id="product_id" name="product_id" class="form-select">
                    <option value="">Semua barang</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" @selected((string)($filters['product_id'] ?? '') === (string)$product->id)>{{ $product->code }} — {{ $product->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="start_date" class="form-label">PO dari tanggal</label>
                <input type="date" id="start_date" name="start_date" class="form-control" value="{{ $filters['start_date'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label for="end_date" class="form-label">Sampai tanggal</label>
                <input type="date" id="end_date" name="end_date" class="form-control" value="{{ $filters['end_date'] ?? '' }}">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary" title="Filter"><i class="bi bi-search"></i></button>
                <a href="{{ route('inventori.pembelian.histori-harga') }}" class="btn btn-outline-secondary" title="Reset"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-sm table-bordered table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Tanggal PO</th>
                    <th>No. PO</th>
                    <th>Supplier</th>
                    <th>Barang</th>
                    <th class="text-end">Qty PO</th>
                    <th class="text-end">Harga PO</th>
                    <th>No. Penerimaan</th>
                    <th class="text-end">Qty Diterima</th>
                    <th>Tanggal Terima</th>
                    <th>No. Faktur</th>
                    <th class="text-end">Harga Faktur</th>
                    <th class="text-end">Selisih</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="text-nowrap">{{ \Carbon\Carbon::parse($row->po_date)->format('d/m/Y') }}</td>
                        <td class="text-nowrap">{{ $row->po_no }}</td>
                        <td>{{ $row->supplier_name ?? '—' }}</td>
                        <td><div class="fw-semibold">{{ $row->product_name }}</div><small class="text-muted">{{ $row->product_code }}</small></td>
                        <td class="text-end">{{ number_format((float)$row->ordered_base_qty, 2, ',', '.') }} {{ $row->unit_code }}</td>
                        <td class="text-end text-nowrap">Rp {{ number_format($row->po_unit_price, 2, ',', '.') }}</td>
                        <td>{{ $row->receipt_numbers ?: '—' }}</td>
                        <td class="text-end">{{ $row->received_base_qty !== null ? number_format((float)$row->received_base_qty, 2, ',', '.') : '—' }}</td>
                        <td class="text-nowrap">{{ $row->last_receipt_date ? \Carbon\Carbon::parse($row->last_receipt_date)->format('d/m/Y') : '—' }}</td>
                        <td>{{ $row->invoice_numbers ?: 'Belum ada faktur' }}</td>
                        <td class="text-end text-nowrap">{{ $row->invoice_unit_price !== null ? 'Rp '.number_format($row->invoice_unit_price, 2, ',', '.') : '—' }}</td>
                        <td class="text-end text-nowrap {{ $row->price_difference !== null && $row->price_difference != 0 ? ($row->price_difference > 0 ? 'text-danger' : 'text-success') : '' }}">
                            {{ $row->price_difference !== null ? (($row->price_difference > 0 ? '+' : '').'Rp '.number_format($row->price_difference, 2, ',', '.')) : '—' }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="text-center text-muted py-4">Histori harga pembelian tidak ditemukan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="small text-muted">Harga PO ditampilkan per item PO. Harga faktur dihitung sebagai nilai faktur dibagi kuantitas dasar yang ditagihkan untuk barang dan PO tersebut; jika belum ada faktur, kolom dibiarkan kosong.</div>
</div>
@endsection
