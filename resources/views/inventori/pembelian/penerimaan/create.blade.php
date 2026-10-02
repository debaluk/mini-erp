@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Penerimaan Barang</h4>
        <div class="text-secondary small">Penerimaan barang berdasarkan Purchase Order</div>
    </div>
    <a href="{{ route('inventori.penerimaan') }}" class="btn btn-outline-secondary">← Kembali</a>
</div>

@if(!$po)
    <div class="alert alert-warning">
        <i class="bi bi-info-circle me-1"></i>
        Penerimaan Barang dibuat dari <strong>Purchase Order</strong>. Buka PO berstatus APPROVED/PARTIAL lalu klik <strong>Penerimaan</strong>.
    </div>
@else
<form method="POST" action="{{ route('inventori.penerimaan.store') }}" id="receiptForm">
    @csrf
    <input type="hidden" name="po_id" value="{{ $po->id }}">

    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold">Informasi Penerimaan</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Tanggal</label>
                    <input type="date" name="receipt_date" class="form-control" value="{{ now()->toDateString() }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Nomor Penerimaan</label>
                    <input class="form-control" value="Otomatis" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">No. PO</label>
                    <input class="form-control fw-bold text-primary" value="{{ $po->po_no }}" readonly>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Supplier</label>
                    <input class="form-control" value="{{ $po->supplier_name }}" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Unit Bisnis</label>
                    <input class="form-control" value="{{ $po->business_unit_name ?? '-' }}" readonly>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Gudang Penerimaan <span class="text-danger">*</span></label>
                    <select name="warehouse_id" class="form-select" required>
                        <option value="">-- Pilih Gudang --</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}">{{ $w->code }} - {{ $w->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Gudang harus sesuai Unit Bisnis PO.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold">Detail Barang dari PO</div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light text-center">
                    <tr>
                        <th style="width:120px">Kode</th>
                        <th>Barang</th>
                        <th style="width:110px">Satuan</th>
                        <th style="width:120px">Qty PO</th>
                        <th style="width:120px">Sudah Diterima</th>
                        <th style="width:130px">Sisa</th>
                        <th style="width:150px">Qty Diterima</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($poItems as $index => $item)
                    <tr>
                        <td class="font-monospace text-center">{{ $item->product_code }}</td>
                        <td>{{ $item->product_name }}</td>
                        <td class="text-center">{{ $item->unit_code ?? '-' }}</td>
                        <td class="text-end">{{ rtrim(rtrim(number_format($item->ordered_qty, 3, '.', ''), '0'), '.') }}</td>
                        <td class="text-end text-muted">{{ rtrim(rtrim(number_format($item->received_qty, 3, '.', ''), '0'), '.') }}</td>
                        <td class="text-end fw-semibold text-primary">{{ rtrim(rtrim(number_format($item->remaining_qty, 3, '.', ''), '0'), '.') }}</td>
                        <td>
                            <input type="hidden" name="items[{{ $index }}][purchase_order_item_id]" value="{{ $item->purchase_order_item_id }}">
                            <input
                                type="number"
                                name="items[{{ $index }}][qty]"
                                class="form-control text-end qty-received"
                                min="0"
                                max="{{ $item->remaining_qty }}"
                                step="0.001"
                                value="0"
                                data-remaining="{{ $item->remaining_qty }}"
                            >
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            Semua item PO sudah diterima.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold">Catatan</div>
        <div class="card-body">
            <textarea name="memo" class="form-control" rows="3" placeholder="Catatan penerimaan..."></textarea>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('inventori.pembelian.po.index') }}" class="btn btn-outline-secondary">Batal</a>
        <button type="submit" class="btn btn-primary" id="saveReceipt">
            <i class="bi bi-box-arrow-in-down me-1"></i> Simpan Penerimaan
        </button>
    </div>
</form>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('receiptForm');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        const inputs = form.querySelectorAll('.qty-received');
        let total = 0;
        let invalid = false;

        inputs.forEach(function (input) {
            const qty = parseFloat(input.value || 0);
            const remaining = parseFloat(input.dataset.remaining || 0);

            if (qty < 0 || qty > remaining) {
                invalid = true;
            }

            total += qty;
        });

        if (invalid) {
            e.preventDefault();
            alert('Qty penerimaan tidak boleh melebihi sisa Qty PO.');
            return;
        }

        if (total <= 0) {
            e.preventDefault();
            alert('Minimal satu item harus diisi Qty Diterima.');
        }
    });
});
</script>
@endpush
