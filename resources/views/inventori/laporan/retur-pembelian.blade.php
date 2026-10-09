@extends('layouts.app')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h4 class="mb-1">Laporan Retur Pembelian</h4>
        <div class="text-secondary small">Ringkasan retur pembelian berdasarkan periode dan unit bisnis.</div>
    </div>
    <a href="{{ route('laporan.retur-pembelian.export', request()->query()) }}" class="btn btn-outline-success">
        <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
    </a>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('laporan.retur-pembelian') }}">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label">Tanggal Mulai</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="form-control">
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label">Tanggal Akhir</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="form-control">
                </div>
                <div class="col-12 col-lg-4">
                    <label class="form-label">Unit Bisnis</label>
                    <select name="unit_id" class="form-select">
                        <option value="">Semua Unit Bisnis</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}" @selected((string) $businessUnitId === (string) $unit->id)>{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-2 d-flex gap-2">
                    <button class="btn btn-outline-primary flex-fill" type="submit">Tampilkan</button>
                    <a class="btn btn-outline-secondary" href="{{ route('laporan.retur-pembelian') }}">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-sm-6 col-lg-4"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Retur Diposting</div><div class="fs-4 fw-bold">{{ format_id_number($summary, 0) }}</div></div></div></div>
    <div class="col-12 col-sm-6 col-lg-4"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Total Qty Retur</div><div class="fs-4 fw-bold">{{ format_id_number($postedQty, 3) }}</div></div></div></div>
    <div class="col-12 col-sm-6 col-lg-4"><div class="card shadow-sm h-100"><div class="card-body"><div class="text-secondary small">Nilai Retur Diposting</div><div class="fs-4 fw-bold">{{ \App\Helpers\FormatHelper::indo($total, 0, true) }}</div></div></div></div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-3">
        <div class="row g-2 small">
            <div class="col-12 col-md-4"><div class="text-secondary">Entitas</div><div class="fw-semibold">{{ $entityName }}</div></div>
            <div class="col-12 col-md-4"><div class="text-secondary">Periode</div><div class="fw-semibold">{{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</div></div>
            <div class="col-12 col-md-4"><div class="text-secondary">Unit Bisnis</div><div class="fw-semibold">{{ $units->firstWhere('id', $businessUnitId)?->name ?? 'Semua Unit Bisnis' }}</div></div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-semibold">Detail Retur Pembelian</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
                <th>No. Retur</th><th>Tanggal</th><th>No. Pembelian</th><th>No. Penerimaan</th><th>Supplier</th><th>Unit Bisnis</th><th class="text-end">Qty</th><th class="text-end">Nilai Retur</th><th>Status</th>
            </tr></thead>
            <tbody>
            @forelse($rows as $r)
                <tr>
                    <td class="fw-semibold text-nowrap">{{ $r->return_no }}</td>
                    <td class="text-nowrap">{{ \Carbon\Carbon::parse($r->return_date)->format('d/m/Y') }}</td>
                    <td>{{ $r->purchase_no ?? '-' }}</td><td>{{ $r->receipt_no ?? '-' }}</td><td>{{ $r->supplier_name ?? '-' }}</td><td>{{ $r->unit_name ?? '-' }}</td>
                    <td class="text-end">{{ format_id_number($r->return_qty, 3) }}</td>
                    <td class="text-end text-nowrap">{{ \App\Helpers\FormatHelper::indo($r->total, 0, true) }}</td>
                    <td><span class="badge {{ $r->status === 'posted' ? 'text-bg-success' : ($r->status === 'cancelled' ? 'text-bg-danger' : 'text-bg-secondary') }}">{{ match($r->status) { 'posted' => 'Diposting', 'draft' => 'Draf', 'cancelled' => 'Dibatalkan', default => $r->status ?: '-' } }}</span></td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-secondary py-4">Belum ada retur pembelian pada periode ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($rows, 'links'))<div class="card-footer">{{ $rows->onEachSide(1)->links('pagination::bootstrap-5') }}</div>@endif
</div>
@endsection
