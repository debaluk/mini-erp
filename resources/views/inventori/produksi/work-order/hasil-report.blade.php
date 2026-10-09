@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Hasil Produksi</h4>
        <div class="text-secondary small">Rekap hasil produksi dan rincian per pekerja.</div>
    </div>
    <div>
        <a href="{{ route('produksi.work-order.hasil-report.export', request()->query()) }}" class="btn btn-success"><i class="bi bi-file-earmark-excel me-1"></i>Export Excel</a>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Tanggal awal</label>
                <input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tanggal akhir</label>
                <input type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
            </div>
            <div class="col-md-auto"><button class="btn btn-primary">Tampilkan</button></div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header fw-semibold bg-light">Rekap per Tanggal dan SPK</div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr><th>Tanggal</th><th>No. SPK</th><th>Produk</th><th class="text-center">Pekerja</th><th class="text-end">Hasil Bagus</th><th class="text-end">Reject</th><th class="text-end">Biaya Upah Satuan</th><th>Status</th></tr>
            </thead>
            <tbody>
            @forelse($results as $result)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($result->production_date)->format('d/m/Y') }}</td>
                    <td class="fw-semibold">{{ $result->wo_no }}</td>
                    <td>{{ $result->product_name }}</td>
                    <td class="text-center">{{ $result->worker_count }}</td>
                    <td class="text-end">{{ \App\Helpers\FormatHelper::indo((float) $result->good_qty, 3) }}</td>
                    <td class="text-end">{{ \App\Helpers\FormatHelper::indo((float) $result->reject_qty, 3) }}</td>
                    <td class="text-end">Rp {{ number_format((float) $result->unit_labor_cost, 2, ',', '.') }}</td>
                    <td><span class="badge text-bg-{{ $result->status === 'posted' ? 'success' : 'warning' }}">{{ $result->status === 'posted' ? 'Sudah Closing' : 'Draft' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">Belum ada hasil produksi pada periode ini.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header fw-semibold bg-light">Rincian per Pekerja</div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr><th>Tanggal</th><th>Pekerja</th><th>Dasar Upah</th><th class="text-end">Tarif</th><th class="text-end">Bagus</th><th class="text-end">Biaya Bagus</th><th class="text-end">Reject</th><th class="text-end">Biaya Reject</th><th>Status</th></tr>
            </thead>
            <tbody>
            @forelse($lines as $line)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($line->production_date)->format('d/m/Y') }}</td>
                    <td>{{ $line->worker_name }}</td>
                    <td>{{ $line->pay_type === 'satuan' ? 'Satuan' : 'Borongan' }}</td>
                    <td class="text-end">{{ $line->pay_type === 'satuan' ? 'Rp '.number_format((float) $line->unit_rate, 2, ',', '.') : 'Rp '.number_format((float) $line->unit_rate, 2, ',', '.') . ' / SPK' }}</td>
                    <td class="text-end">{{ \App\Helpers\FormatHelper::indo((float) $line->good_qty, 3) }}</td>
                    <td class="text-end">{{ $line->pay_type === 'satuan' ? 'Rp '.number_format((float) $line->good_cost, 2, ',', '.') : '-' }}</td>
                    <td class="text-end">{{ \App\Helpers\FormatHelper::indo((float) $line->reject_qty, 3) }}</td>
                    <td class="text-end">{{ $line->pay_type === 'satuan' ? 'Rp '.number_format((float) $line->reject_cost, 2, ',', '.') : '-' }}</td>
                    <td><span class="badge text-bg-{{ $line->status === 'posted' ? 'success' : 'warning' }}">{{ $line->status === 'posted' ? 'Sudah Closing' : 'Draft' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center text-muted py-4">Belum ada rincian hasil produksi.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
