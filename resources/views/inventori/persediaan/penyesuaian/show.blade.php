@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold text-dark"><i class="bi bi-file-earmark-check me-2 text-success"></i> Detail Penyesuaian Stok #{{ $adj->adjustment_no }}</h3>
            <div class="text-secondary small">Status: <span class="badge {{ $adj->status === 'posted' ? 'bg-success' : 'bg-secondary' }}">{{ strtoupper($adj->status) }}</span></div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('inventori.penyesuaian.print-detail', $adj->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-printer me-1"></i> Cetak Voucher
            </a>
            <a href="{{ route('inventori.penyesuaian.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke List
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-md-3"><div class="text-muted small">No. Adjustment</div><div class="fw-bold font-monospace text-primary fs-6">{{ $adj->adjustment_no }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Ref Stock Opname</div><div class="fw-bold font-monospace">{{ $adj->opname_no ?? '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Unit Bisnis</div><div class="fw-bold">{{ $adj->business_unit_name ?? '-' }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Lokasi Gudang</div><div class="fw-bold text-dark">{{ $adj->warehouse_name }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Tanggal Transaksi</div><div class="fw-bold">{{ \Carbon\Carbon::parse($adj->adjustment_date)->format('d/m/Y') }}</div></div>
                <div class="col-md-3"><div class="text-muted small">Dibuat Oleh</div><div class="fw-bold">{{ $adj->creator_name }}</div></div>
                <div class="col-md-6"><div class="text-muted small">Alasan</div><div class="fw-bold">{{ $adj->reason ?? '-' }}</div></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white py-2">
            <span class="fw-bold small"><i class="bi bi-box-seam me-1"></i> Rincian Koreksi Stok Fisik & Nilai Modal</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.88rem;">
                    <thead class="table-light text-center">
                        <tr>
                            <th style="width: 40px;">No</th>
                            <th style="width: 140px;">Kode Barang</th>
                            <th class="text-start">Nama Produk</th>
                            <th style="width: 120px;">System Qty</th>
                            <th style="width: 120px;">Adjust Qty</th>
                            <th style="width: 120px;">Final Qty</th>
                            <th style="width: 130px;">Unit Cost (HPP)</th>
                            <th style="width: 140px;">Total Cost (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $idx => $i)
                            @php $qty = (float) $i->adjustment_qty; @endphp
                            <tr>
                                <td class="text-center">{{ $idx + 1 }}</td>
                                <td class="font-monospace text-center fw-semibold">{{ $i->product_code }}</td>
                                <td><div class="fw-semibold text-dark">{{ $i->product_name }}</div></td>
                                <td class="text-center">{{ number_format($i->system_qty, 2, ',', '.') }}</td>
                                <td class="text-center fw-bold {{ $qty < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ $qty > 0 ? '+' . number_format($qty, 2, ',', '.') : number_format($qty, 2, ',', '.') }}
                                </td>
                                <td class="text-center fw-bold">{{ number_format($i->final_qty, 2, ',', '.') }}</td>
                                <td class="text-end font-monospace">Rp {{ number_format($i->unit_cost, 0, ',', '.') }}</td>
                                <td class="text-end font-monospace fw-bold">Rp {{ number_format($i->total_cost, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if(count($journals) > 0)
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white py-2">
                <span class="fw-bold small"><i class="bi bi-journal-text me-1"></i> Audit Trail Jurnal Umum (GL) Terbentuk</span>
            </div>
            <div class="card-body p-3">
                @foreach($journals as $j)
                    <div class="mb-3 p-3 bg-light rounded border">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold font-monospace text-primary">{{ $j->journal_no }}</span>
                            <span class="small text-muted">{{ $j->description }}</span>
                        </div>
                        <table class="table table-sm table-bordered bg-white mb-0" style="font-size: 0.85rem;">
                            <thead class="table-secondary">
                                <tr>
                                    <th>Kode Akun</th>
                                    <th>Nama Akun (COA)</th>
                                    <th class="text-end" style="width: 150px;">Debit (Rp)</th>
                                    <th class="text-end" style="width: 150px;">Kredit (Rp)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($j->entries as $e)
                                    <tr>
                                        <td class="font-monospace">{{ $e->account_code }}</td>
                                        <td>{{ $e->account_name }}</td>
                                        <td class="text-end font-monospace">{{ $e->debit > 0 ? number_format($e->debit, 0, ',', '.') : '-' }}</td>
                                        <td class="text-end font-monospace">{{ $e->credit > 0 ? number_format($e->credit, 0, ',', '.') : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</div>
@endsection
