@extends('layouts.app')

@section('content')
<style>
    .report-kpi { min-height: 105px; }
    .report-kpi .label { font-size: .8rem; color: #6c757d; }
    .report-kpi .value { font-size: 1.25rem; font-weight: 700; }
    .report-table th, .report-table td { vertical-align: middle; white-space: nowrap; }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h4 class="mb-1">Laporan Pembelian</h4>
        <div class="text-secondary small">Ringkasan dan detail transaksi pembelian berdasarkan periode dan unit bisnis.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('laporan.pembelian.export', request()->query()) }}" class="btn btn-outline-success">
            📊 Export Excel
        </a>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('laporan.pembelian') }}">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label">Tanggal Mulai</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="form-control">
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label">Tanggal Akhir</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="form-control">
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label">Unit Bisnis</label>
                    <select name="unit_id" class="form-select">
                        <option value="">Semua Unit Bisnis</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}" @selected((string) request('unit_id') === (string) $unit->id)>{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label">Supplier</label>
                    <select name="supplier" class="form-select">
                        <option value="">Semua Supplier</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->name }}" @selected(request('supplier') === $supplier->name)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label">Cara Bayar</label>
                    <select name="payment_method" class="form-select">
                        <option value="">Semua</option>
                        <option value="cash" @selected(request('payment_method') === 'cash')>Tunai</option>
                        <option value="credit" @selected(request('payment_method') === 'credit')>Kredit</option>
                    </select>
                </div>
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua Status</option>
                        @foreach($rows->getCollection()->pluck('status')->filter()->unique() as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary">Tampilkan</button>
                    <a href="{{ route('laporan.pembelian') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body py-3">
        <div class="row g-2 small">
            <div class="col-12 col-md-4">
                <div class="text-secondary">Entitas</div>
                <div class="fw-semibold">{{ $entityName }}</div>
            </div>
            <div class="col-12 col-md-4">
                <div class="text-secondary">Periode</div>
                <div class="fw-semibold">{{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</div>
            </div>
            <div class="col-12 col-md-4">
                <div class="text-secondary">Unit Bisnis</div>
                <div class="fw-semibold">{{ $selectedUnitName }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card shadow-sm report-kpi"><div class="card-body">
            <div class="label">Jumlah Transaksi</div>
            <div class="value">{{ number_format((int) ($summary->transaction_count ?? 0), 0, ',', '.') }}</div>
        </div></div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card shadow-sm report-kpi"><div class="card-body">
            <div class="label">Subtotal</div>
            <div class="value">Rp {{ number_format((float) ($summary->subtotal_total ?? 0), 0, ',', '.') }}</div>
        </div></div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card shadow-sm report-kpi"><div class="card-body">
            <div class="label">Total Diskon</div>
            <div class="value">Rp {{ number_format((float) ($summary->discount_total ?? 0), 0, ',', '.') }}</div>
        </div></div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card shadow-sm report-kpi"><div class="card-body">
            <div class="label">Total Pembelian</div>
            <div class="value">Rp {{ number_format((float) ($summary->purchase_total ?? 0), 0, ',', '.') }}</div>
        </div></div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-semibold">Detail Transaksi Pembelian</div>
    <div class="table-responsive">
        <table class="table table-hover report-table mb-0">
            <thead class="table-light">
                <tr>
                    <th>No.</th><th>Tanggal</th><th>No. Pembelian</th><th>Supplier</th><th>Unit Bisnis</th>
                    <th>Cara Bayar</th><th>Jatuh Tempo</th><th class="text-end">Subtotal</th>
                    <th class="text-end">Diskon</th><th class="text-end">Total</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $rows->firstItem() + $loop->index }}</td>
                        <td>{{ $row->purchase_date ? \Carbon\Carbon::parse($row->purchase_date)->format('d/m/Y') : '-' }}</td>
                        <td class="fw-semibold">{{ $row->purchase_no }}</td>
                        <td>{{ $row->supplier_name ?? '-' }}</td>
                        <td>{{ $row->unit_name ?? '-' }}</td>
                        <td>{{ strtolower($row->payment_method ?? '') === 'credit' ? 'Kredit' : (strtolower($row->payment_method ?? '') === 'cash' ? 'Tunai' : ($row->payment_method ?? '-')) }}</td>
                        <td>{{ $row->due_date ? \Carbon\Carbon::parse($row->due_date)->format('d/m/Y') : '-' }}</td>
                        <td class="text-end">Rp {{ number_format((float) $row->subtotal, 0, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format((float) $row->discount, 0, ',', '.') }}</td>
                        <td class="text-end fw-semibold">Rp {{ number_format((float) $row->total, 0, ',', '.') }}</td>
                        <td><span class="badge bg-secondary">{{ ucfirst($row->status ?? '-') }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="text-center text-secondary py-4">Tidak ada transaksi pembelian pada filter ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($rows, 'links'))
        <div class="card-footer no-print">
            {{ $rows->onEachSide(1)->links("pagination::bootstrap-5") }}
        </div>
    @endif
</div>
@endsection
