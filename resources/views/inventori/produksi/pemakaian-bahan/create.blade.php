@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Pemakaian Bahan Baku</h4>
        <div class="text-secondary small">Input aktual pemakaian berdasarkan rencana SPK</div>
    </div>
    <a href="{{ route('produksi.pemakaian-bahan') }}" class="btn btn-outline-secondary">Kembali</a>
</div>

<form method="POST" action="{{ route('produksi.pemakaian-bahan.store', $wo->id) }}">
@csrf
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header fw-semibold">Informasi SPK</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3"><div class="text-secondary small">No. SPK</div><div class="fw-semibold">{{ $wo->wo_no }}</div></div>
            <div class="col-md-3"><div class="text-secondary small">Tanggal SPK</div><div>{{ CarbonCarbon::parse($wo->wo_date)->format('d/m/Y') }}</div></div>
            <div class="col-md-3"><div class="text-secondary small">Produk</div><div class="fw-semibold">{{ $wo->product_name }}</div></div>
            <div class="col-md-3"><div class="text-secondary small">Gudang</div><div>{{ $wo->warehouse_name }}</div></div>
            <div class="col-md-3"><div class="text-secondary small">BOM</div><div>{{ $wo->bom_code }}</div></div>
            <div class="col-md-3"><div class="text-secondary small">Target Produksi</div><div>{{ AppHelpersFormatHelper::indo((float) $wo->target_output_qty, 2) }}</div></div>
            <div class="col-md-3">
                <label class="form-label">Tanggal Pemakaian</label>
                <input type="date" name="usage_date" class="form-control" value="{{ old('usage_date', now()->toDateString()) }}" required>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-warning bg-opacity-10 fw-semibold">Rencana & Aktual Pemakaian</div>
    <div class="card-body">
        <div class="alert alert-info">Rencana diambil dari SPK/BOM. Isi hanya jumlah aktual yang dipakai.</div>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead class="table-light">
                    <tr>
                        <th>SKU</th>
                        <th>Bahan</th>
                        <th class="text-end">Rencana dari SPK</th>
                        <th>Satuan</th>
                        <th style="width:220px">Aktual Dipakai</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($items as $item)
                    <tr>
                        <td>{{ $item['sku'] }}</td>
                        <td>{{ $item['name'] }}</td>
                        <td class="text-end">{{ AppHelpersFormatHelper::indo($item['planned_qty'], 3) }}</td>
                        <td>{{ $item['unit'] }}</td>
                        <td>
                            <input type="hidden" name="product_id[]" value="{{ $item['product_id'] }}">
                            <input type="number" step="0.001" min="0.001" name="actual_qty[]" class="form-control text-end" value="{{ old('actual_qty.'. $loop->index, $item['planned_qty']) }}" required>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <label class="form-label">Catatan</label>
        <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
    </div>
</div>

<div class="d-flex justify-content-end gap-2">
    <a href="{{ route('produksi.pemakaian-bahan') }}" class="btn btn-outline-secondary">Batal</a>
    <button class="btn btn-primary">Simpan Draft</button>
</div>
</form>
@endsection
