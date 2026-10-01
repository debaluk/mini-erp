@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold">Edit Faktur Pembelian</h3>
            <div class="text-muted">{{ $p->purchase_no }}</div>
        </div>
        <a href="{{ route('inventori.pembelian.show', $p->id) }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
    </div>

    <div class="alert alert-info">
        Edit hanya tersedia untuk faktur <strong>DRAFT</strong>. Faktur POSTED tidak dapat diedit.
    </div>

    <form method="POST" action="{{ route('inventori.pembelian.update', $p->id) }}">
        @csrf
        @method('PUT')
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tanggal Faktur</label>
                        <input type="date" name="purchase_date" class="form-control" value="{{ CarbonCarbon::parse($p->purchase_date)->format('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">No. Faktur Supplier</label>
                        <input type="text" name="supplier_invoice_no" class="form-control" value="{{ $p->supplier_invoice_no }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cara Bayar</label>
                        <select name="payment_method" class="form-select" required>
                            <option value="cash" @selected($p->payment_method === 'cash')>Cash</option>
                            <option value="credit" @selected($p->payment_method === 'credit')>Kredit</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Memo</label>
                        <textarea name="memo" class="form-control" rows="3">{{ $p->memo }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header fw-bold">Item Pembelian</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0">
                        <thead class="table-dark"><tr><th>Kode</th><th>Barang</th><th>Qty</th><th>Harga</th><th>Diskon</th><th>Total</th></tr></thead>
                        <tbody>
                        @foreach($items as $item)
                            <tr>
                                <td>{{ $item->product_code }}</td>
                                <td>{{ $item->product_name }}</td>
                                <td>{{ number_format($item->qty, 2, ',', '.') }} {{ $item->unit_name }}</td>
                                <td class="text-end">Rp {{ number_format($item->unit_cost, 0, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($item->discount, 0, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($item->total, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <button class="btn btn-warning">Simpan Perubahan</button>
    </form>
</div>
@endsection
