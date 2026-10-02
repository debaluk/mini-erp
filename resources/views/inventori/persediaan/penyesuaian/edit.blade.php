@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold text-dark"><i class="bi bi-pencil-square me-2 text-warning"></i> Edit Draft Penyesuaian Stok #{{ $adj->adjustment_no }}</h3>
            <div class="text-secondary small">Perbarui nilai kuantitas koreksi sebelum dokumen diposting secara final.</div>
        </div>
        <a href="{{ route('inventori.penyesuaian.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke List
        </a>
    </div>

    <form action="{{ route('inventori.penyesuaian.update', $adj->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">No. Adjustment</label>
                        <input type="text" class="form-control font-monospace fw-bold bg-light" value="{{ $adj->adjustment_no }}" readonly>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Tanggal Adjustment <span class="text-danger">*</span></label>
                        <input type="date" name="adjustment_date" class="form-control" value="{{ $adj->adjustment_date }}" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Alasan / Catatan Penyesuaian</label>
                        <input type="text" name="reason" class="form-control" value="{{ $adj->reason }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-dark text-white py-2">
                <span class="fw-bold small"><i class="bi bi-box-seam me-1"></i> Edit Item Kuantitas Penyesuaian</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0" style="font-size: 0.88rem;">
                        <thead class="table-light text-center">
                            <tr>
                                <th style="width: 40px;">No</th>
                                <th style="width: 140px;">Kode Barang</th>
                                <th>Nama Produk</th>
                                <th style="width: 120px;">Stok Sistem</th>
                                <th style="width: 140px;">Adjustment Qty (+/-)</th>
                                <th style="width: 120px;">Final Qty</th>
                                <th style="width: 130px;">HPP (unit_cost)</th>
                                <th style="width: 80px;">Satuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $idx => $item)
                                @php
                                    $sys  = (float) $item->system_qty;
                                    $adj  = (float) $item->adjustment_qty;
                                    $cost = (float) $item->unit_cost;
                                @endphp
                                <tr>
                                    <td class="text-center">{{ $idx + 1 }}</td>
                                    <td class="font-monospace fw-semibold text-center">{{ $item->product_code }}</td>
                                    <td>
                                        <input type="hidden" name="item_ids[]" value="{{ $item->id }}">
                                        <div class="fw-semibold text-dark">{{ $item->product_name }}</div>
                                    </td>
                                    <td class="text-center bg-light">{{ number_format($sys, 2, ',', '.') }}</td>
                                    <td>
                                        <input type="number" name="adjustment_qty[]" class="form-control form-control-sm text-center fw-bold input-adj-edit {{ $adj < 0 ? 'text-danger' : 'text-success' }}"
                                               data-sys="{{ $sys }}" step="0.001" value="{{ $adj }}" required>
                                    </td>
                                    <td class="text-center font-monospace fw-bold cell-final-edit">{{ number_format($sys + $adj, 2, ',', '.') }}</td>
                                    <td class="text-end font-monospace">Rp {{ number_format($cost, 0, ',', '.') }}</td>
                                    <td class="text-center">{{ $item->unit_name ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light p-3 text-end">
                <a href="{{ route('inventori.penyesuaian.index') }}" class="btn btn-secondary me-2">Batal</a>
                <button type="submit" name="update_draft" value="1" class="btn btn-primary me-2">
                    <i class="bi bi-save me-1"></i> Perbarui Draft
                </button>
                <button type="submit" name="post_now" value="1" class="btn btn-success px-4 fw-bold">
                    <i class="bi bi-check-circle me-1"></i> Perbarui & Posting
                </button>
            </div>
        </div>
    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.input-adj-edit').forEach(input => {
        input.addEventListener('input', function () {
            const sys = parseFloat(this.dataset.sys);
            const adj = parseFloat(this.value) || 0;
            const finalQty = sys + adj;

            const row = this.closest('tr');
            row.querySelector('.cell-final-edit').innerText = finalQty.toFixed(2);
        });
    });
});
</script>
@endsection