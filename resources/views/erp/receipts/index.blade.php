@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h4 class="mb-1">Penerimaan Barang</h4><div class="text-secondary small">Daftar penerimaan barang masuk ke gudang</div></div>
    <a href="{{ route('inventori.penerimaan.create') }}" class="btn btn-primary">+ Tambah Penerimaan</a>
</div>

<div class="card shadow-sm">
    <div class="card-body border-bottom">
        <form method="GET" action="{{ route('inventori.penerimaan') }}" class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label">Tanggal Mulai</label><input type="date" name="start_date" value="{{ $startDate }}" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Tanggal Akhir</label><input type="date" name="end_date" value="{{ $endDate }}" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Supplier</label><select name="supplier_id" class="form-select"><option value="">Semua Supplier</option>@foreach($suppliers as $s)<option value="{{ $s->id }}" @selected((string)request('supplier_id')===(string)$s->id)>{{ $s->code }} - {{ $s->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Gudang</label><select name="warehouse_id" class="form-select"><option value="">Semua Gudang</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" @selected((string)request('warehouse_id')===(string)$w->id)>{{ $w->code }} - {{ $w->name }}</option>@endforeach</select></div>
            <div class="col-md-1"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">Semua</option><option value="draft">Draft</option><option value="posted">Posted</option></select></div>
            <div class="col-md-1"><button class="btn btn-outline-primary w-100" type="submit">Cari</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
                <th>No. Penerimaan</th><th>Tanggal</th><th>No. PO</th><th>Supplier</th><th>Gudang</th><th>Total Item</th><th>Status</th>
            </tr></thead>
            <tbody>
            @forelse($rows as $r)
                <tr><td class="fw-semibold">{{ $r->receipt_no }}</td><td>{{ $r->receipt_date }}</td><td>{{ $r->po_no ?? '-' }}</td><td>{{ $r->supplier_name ?? '-' }}</td><td>{{ $r->warehouse_name ?? '-' }}</td><td>{{ $r->total_items ?? 0 }}</td><td><span class="badge text-bg-secondary">{{ $r->status }}</span></td></tr>
            @empty
                <tr><td colspan="7" class="text-center text-secondary py-4">Belum ada penerimaan barang.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-secondary">Penerimaan barang akan menambah stok setelah dokumen diposting.</div>
</div>
@endsection
