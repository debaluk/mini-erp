@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold">Buat Retur Pembelian</h3>
            <div class="text-muted small">Retur wajib berasal dari satu Penerimaan Barang yang terposting.</div>
        </div>
        <a href="{{ route('inventori.penerimaan') }}" class="btn btn-outline-secondary btn-sm">Kembali ke Penerimaan</a>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><div class="small text-muted">No. Penerimaan Sumber</div><strong>{{ $receipt->receipt_no }}</strong></div>
                <div class="col-md-3"><div class="small text-muted">Faktur Pembelian</div><strong>{{ $receipt->purchase_no }}</strong></div>
                <div class="col-md-3"><div class="small text-muted">Supplier</div><strong>{{ $receipt->supplier_name }}</strong></div>
                <div class="col-md-3"><div class="small text-muted">Gudang Sumber</div><strong>{{ $receipt->warehouse_name }}</strong></div>
                <div class="col-md-3"><div class="small text-muted">Unit Bisnis</div><strong>{{ $receipt->business_unit_name }}</strong></div>
                <div class="col-md-3"><div class="small text-muted">Tanggal Penerimaan</div><strong>{{ \Carbon\Carbon::parse($receipt->receipt_date)->format('d/m/Y') }}</strong></div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('inventori.pembelian.retur.store-from-receipt', $receipt->id) }}">
        @csrf
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger">{{ $errors->first() }}</div>
                @endif
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Tanggal Retur</label>
                        <input type="date" name="return_date" class="form-control" value="{{ old('return_date', now()->toDateString()) }}" required>
                    </div>
                    <div class="col-md-9">
                        <label class="form-label">Alasan Retur</label>
                        <input type="text" name="reason" class="form-control" maxlength="1000" value="{{ old('reason') }}" placeholder="Alasan retur (opsional)">
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead class="table-dark text-center">
                            <tr><th>Kode</th><th>Barang</th><th>Qty Penerimaan</th><th>Sisa Bisa Diretur</th><th>Harga Pokok Pembelian Saat Penerimaan</th><th style="width:150px">Qty Retur</th><th style="width:120px">Kondisi</th></tr>
                        </thead>
                        <tbody>
                        @foreach($items as $item)
                            <tr>
                                <td>{{ $item->product_code }}</td>
                                <td>{{ $item->product_name }}</td>
                                <td class="text-end">{{ number_format((float)$item->qty, 3, ',', '.') }} {{ $item->unit_name }}</td>
                                <td class="text-end">{{ number_format((float)$item->returnable_qty, 3, ',', '.') }} {{ $item->unit_name }}</td>
                                <td class="text-end">Rp {{ number_format((float)$item->base_unit_cost, 0, ',', '.') }} <span class="text-muted small">/ satuan dasar</span></td>
                                <td>
                                    @if($item->returnable_qty > 0)
                                        <input type="number" name="items[{{ $item->id }}][qty]" class="form-control form-control-sm text-end" min="0.001" max="{{ $item->returnable_qty }}" step="0.001" value="{{ old('items.'.$item->id.'.qty') }}" placeholder="0">
                                    @else
                                        <span class="text-muted">Habis diretur</span>
                                    @endif
                                </td>
                                <td>
                                    @if($item->returnable_qty > 0)
                                        <select name="items[{{ $item->id }}][condition]" class="form-select form-select-sm">
                                            <option value="good">Baik</option>
                                            <option value="reject">Rusak</option>
                                        </select>
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="small text-muted mb-3">Nomor penerimaan, barang, satuan, dan gudang dikunci mengikuti penerimaan sumber. Qty dibatasi oleh sisa penerimaan yang belum diretur. Harga retur memakai harga pokok pembelian pada penerimaan sumber.</div>
                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('inventori.penerimaan') }}" class="btn btn-outline-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan Draft Retur</button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
