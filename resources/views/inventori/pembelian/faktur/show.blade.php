@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold">Detail Faktur Pembelian</h3>
            <div class="text-muted">{{ $p->invoice_no }}</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('inventori.pembelian.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
            <a href="{{ route('inventori.pembelian.print-invoice', $p->id) }}" target="_blank" class="btn btn-secondary btn-sm">
                <i class="bi bi-printer me-1"></i> Print Invoice
            </a>
            @if($p->status === 'draft')
                <a href="{{ route('inventori.pembelian.edit', $p->id) }}" class="btn btn-warning btn-sm">Edit</a>
            @endif
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><div class="text-muted small">No. Faktur</div><strong>{{ $p->invoice_no }}</strong></div>
                <div class="col-md-3"><div class="text-muted small">Tanggal</div><strong>{{ CarbonCarbon::parse($p->purchase_date)->format('d/m/Y') }}</strong></div>
                <div class="col-md-3"><div class="text-muted small">Supplier</div><strong>{{ $p->supplier_name }}</strong></div>
                <div class="col-md-3"><div class="text-muted small">Unit Bisnis</div><strong>{{ $p->business_unit_name }}</strong></div>
                <div class="col-md-3"><div class="text-muted small">PO</div><strong>{{ $p->po_no ?? 'Non-PO' }}</strong></div>
                <div class="col-md-3"><div class="text-muted small">Pembayaran</div><strong>{{ strtoupper($p->payment_type) }}</strong></div>
                <div class="col-md-3"><div class="text-muted small">Barang Diterima</div><strong>{{ (int)$p->goods_received === 1 ? 'YA' : 'TIDAK' }}</strong></div>
                <div class="col-md-3"><div class="text-muted small">Status</div>
                    @if($p->status === 'posted')
                        <span class="badge bg-success">POSTED</span>
                    @else
                        <span class="badge bg-secondary">DRAFT</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-sm mb-0 align-middle">
                    <thead class="table-dark text-center">
                        <tr>
                            <th>No</th><th>Kode</th><th>Nama Barang</th><th>Qty</th><th>Harga</th><th>Diskon</th><th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($items as $i => $item)
                        <tr>
                            <td class="text-center">{{ $i + 1 }}</td>
                            <td>{{ $item->product_code }}</td>
                            <td>{{ $item->product_name }}</td>
                            <td class="text-end">{{ number_format($item->qty, 2, ',', '.') }} {{ $item->unit_name }}</td>
                            <td class="text-end">Rp {{ number_format($item->unit_cost, 0, ',', '.') }}</td>
                            <td class="text-end">Rp {{ number_format($item->discount, 0, ',', '.') }}</td>
                            <td class="text-end fw-bold">Rp {{ number_format($item->total, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot>
                        <tr><th colspan="6" class="text-end">Subtotal</th><th class="text-end">Rp {{ number_format($p->subtotal, 0, ',', '.') }}</th></tr>
                        <tr><th colspan="6" class="text-end">PPN</th><th class="text-end">Rp {{ number_format($p->ppn_amount, 0, ',', '.') }}</th></tr>
                        <tr><th colspan="6" class="text-end">Total</th><th class="text-end">Rp {{ number_format($p->grand_total, 0, ',', '.') }}</th></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    @if($journals->count())
        <div class="card shadow-sm border-0">
            <div class="card-header fw-bold">Jurnal Penerimaan</div>
            <div class="card-body">
                @foreach($journals as $journal)
                    <div class="small text-muted mb-2">{{ $journal->journal_no ?? $journal->id }} — {{ $journal->journal_date }}</div>
                    <table class="table table-sm">
                        <thead><tr><th>Akun</th><th class="text-end">Debit</th><th class="text-end">Kredit</th></tr></thead>
                        <tbody>
                        @foreach($journal->entries as $entry)
                            <tr>
                                <td>{{ $entry->account_code }} - {{ $entry->account_name }}</td>
                                <td class="text-end">Rp {{ number_format($entry->debit, 0, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format($entry->credit, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
