@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h3 class="mb-1 fw-bold">Detail Retur Pembelian</h3><div class="text-muted">{{ $return->return_no }}</div></div>
        <div class="d-flex gap-2">
            <a href="{{ route('inventori.pembelian.retur') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
            <a href="{{ route('inventori.pembelian.retur.print', $return->id) }}" target="_blank" class="btn btn-outline-primary btn-sm"><i class="bi bi-printer me-1"></i>Cetak</a>
            @if($return->status === 'draft')
                <form method="POST" action="{{ route('inventori.pembelian.retur.post', $return->id) }}" class="d-inline">
                    @csrf
                    <button class="btn btn-success btn-sm" onclick="return confirm('Post retur ini?')"><i class="bi bi-check-circle me-1"></i>Post Retur</button>
                </form>
            @endif
        </div>
    </div>
    <div class="card shadow-sm border-0 mb-3"><div class="card-body"><div class="row g-3">
        <div class="col-md-3"><div class="text-muted small">No. Retur</div><strong>{{ $return->return_no }}</strong></div>
        <div class="col-md-3"><div class="text-muted small">Tanggal</div><strong>{{ Carbon\Carbon::parse($return->return_date)->format('d/m/Y') }}</strong></div>
        <div class="col-md-3"><div class="text-muted small">Faktur</div><strong>{{ $return->invoice_no }}</strong></div>
        <div class="col-md-3"><div class="text-muted small">Supplier</div><strong>{{ $return->supplier_name }}</strong></div>
        <div class="col-md-3"><div class="text-muted small">Gudang</div><strong>{{ $return->warehouse_name }}</strong></div>
        <div class="col-md-3"><div class="text-muted small">Status</div><span class="badge {{ $return->status === 'posted' ? 'bg-success' : 'bg-secondary' }}">{{ strtoupper($return->status) }}</span></div>
        <div class="col-md-6"><div class="text-muted small">Alasan</div><strong>{{ $return->reason ?: '-' }}</strong></div>
    </div></div></div>
    <div class="card shadow-sm border-0"><div class="card-body p-0"><div class="table-responsive">
        <table class="table table-bordered table-sm mb-0 align-middle">
            <thead class="table-dark text-center"><tr><th>No</th><th>Kode</th><th>Nama Barang</th><th>Qty</th><th>Kondisi</th><th>HPP/Unit</th><th>Total</th></tr></thead>
            <tbody>
            @foreach($items as $i => $item)
                <tr>
                    <td class="text-center">{{ $i + 1 }}</td><td>{{ $item->product_code }}</td><td>{{ $item->product_name }}</td>
                    <td class="text-end">{{ number_format($item->qty, 2, ',', '.') }} {{ $item->unit_name }}</td>
                    <td class="text-center"><span class="badge {{ $item->condition === 'reject' ? 'bg-danger' : 'bg-success' }}">{{ $item->condition === 'reject' ? 'RUSAK' : 'BAGUS' }}</span></td>
                    <td class="text-end">Rp {{ number_format($item->unit_value, 0, ',', '.') }}</td><td class="text-end fw-bold">Rp {{ number_format($item->return_value, 0, ',', '.') }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr><th colspan="6" class="text-end">Total</th><th class="text-end">Rp {{ number_format($return->total, 0, ',', '.') }}</th></tr></tfoot>
        </table>
    </div></div></div>
</div>
@endsection
