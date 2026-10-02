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
            @if($p->status === 'posted' && $items->sum('returnable_qty') > 0)
                <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#modalReturPembelian">
                    <i class="bi bi-arrow-return-left me-1"></i> Retur Pembelian
                </button>
            @endif
            <a href="{{ route('inventori.pembelian.print-invoice', $p->id) }}" target="_blank" class="btn btn-secondary btn-sm">
                <i class="bi bi-printer me-1"></i> Print Invoice
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><div class="text-muted small">No. Faktur</div><strong>{{ $p->invoice_no }}</strong></div>
                <div class="col-md-3"><div class="text-muted small">Tanggal</div><strong>{{ \Carbon\Carbon::parse($p->purchase_date)->format('d/m/Y') }}</strong></div>
                <div class="col-md-3"><div class="text-muted small">Supplier</div><strong>{{ $p->supplier_name }}</strong></div>
                <div class="col-md-3"><div class="text-muted small">Unit Bisnis</div><strong>{{ $p->business_unit_name }}</strong></div>
                <div class="col-md-3"><div class="text-muted small">PO</div><strong>{{ $p->po_no ?? 'Non-PO' }}</strong></div>
                <div class="col-md-3"><div class="text-muted small">Pembayaran</div><strong>{{ strtoupper($p->payment_type) }}</strong></div>
                <div class="col-md-3"><div class="text-muted small">Barang Diterima</div><strong>{{ (int)$p->goods_received === 1 ? 'YA' : 'TIDAK' }}</strong></div>
                <div class="col-md-3"><div class="text-muted small">Gudang</div><strong>{{ $warehouse->warehouse_name ?? '-' }}</strong></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body p-0">
            <div class="border-bottom px-3 py-2">
                <div class="text-muted small">Status</div>
                @if($p->status === 'posted')
                    <span class="badge bg-success">POSTED</span>
                @else
                    <span class="badge bg-secondary">DRAFT</span>
                @endif
            </div>
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

    @if(isset($purchaseReturns) && $purchaseReturns->count())
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header fw-bold d-flex justify-content-between align-items-center">
                <span>Riwayat Retur Pembelian</span>
                <span class="badge bg-light text-dark">{{ $purchaseReturns->count() }} transaksi</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light text-center"><tr>
                        <th>No. Retur</th><th>Tanggal</th><th>Qty</th><th>Total</th><th>Status</th><th>User</th><th>Aksi</th>
                    </tr></thead>
                    <tbody>
                    @foreach($purchaseReturns as $retur)
                        <tr>
                            <td class="font-monospace fw-semibold">{{ $retur->return_no }}</td>
                            <td class="text-center">{{ Carbon\Carbon::parse($retur->return_date)->format('d/m/Y') }}</td>
                            <td class="text-end">{{ number_format($retur->return_qty, 2, ',', '.') }}</td>
                            <td class="text-end fw-bold">Rp {{ number_format($retur->total, 0, ',', '.') }}</td>
                            <td class="text-center"><span class="badge {{ $retur->status === 'posted' ? 'bg-success' : 'bg-secondary' }}">{{ strtoupper($retur->status) }}</span></td>
                            <td>{{ $retur->user_name }}</td>
                            <td class="text-center"><a href="{{ route('inventori.pembelian.retur.show', $retur->id) }}" class="btn btn-outline-info btn-sm"><i class="bi bi-eye"></i></a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

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
@if($p->status === 'posted' && $items->sum('returnable_qty') > 0)
<div class="modal fade" id="modalReturPembelian" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ route('inventori.pembelian.retur.store') }}">
                @csrf
                <input type="hidden" name="purchase_id" value="{{ $p->id }}">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-arrow-return-left me-2"></i>Retur Pembelian</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-light border small">
                        <strong>{{ $p->invoice_no }}</strong> — {{ $p->supplier_name }} — {{ $warehouse->warehouse_name ?? '-' }}
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Tanggal Retur</label>
                            <input type="date" name="return_date" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small fw-bold">Alasan</label>
                            <input type="text" name="reason" class="form-control form-control-sm" maxlength="1000" placeholder="Alasan retur">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle">
                            <thead class="table-dark text-center"><tr>
                                <th>Kode</th><th>Barang</th><th>Qty Faktur</th><th>Sudah Diterima / Sisa Retur</th><th style="width:140px;">Qty Retur</th><th style="width:130px;">Kondisi</th>
                            </tr></thead>
                            <tbody>
                            @foreach($items as $item)
                                <tr>
                                    <td>{{ $item->product_code }}</td>
                                    <td>{{ $item->product_name }}</td>
                                    <td class="text-end">{{ number_format($item->qty, 2, ',', '.') }} {{ $item->unit_name }}</td>
                                    <td class="text-end">{{ number_format($item->returnable_qty, 2, ',', '.') }} {{ $item->unit_name }}</td>
                                    <td>
                                        @if($item->returnable_qty > 0)
                                            <input type="number" step="0.001" min="0" max="{{ $item->returnable_qty }}" name="items[{{ $item->id }}][qty]" class="form-control form-control-sm text-end" placeholder="0">
                                            <input type="hidden" name="items[{{ $item->id }}][purchase_item_id]" value="{{ $item->id }}">
                                        @else
                                            <span class="text-muted small">Tidak tersedia</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($item->returnable_qty > 0)
                                            <select name="items[{{ $item->id }}][condition]" class="form-select form-select-sm">
                                                <option value="good">BAGUS</option>
                                                <option value="reject">RUSAK</option>
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
                    <div class="small text-muted">Qty retur tidak boleh melebihi qty yang sudah diterima dan belum pernah diretur.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Simpan Draft Retur</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@endsection
