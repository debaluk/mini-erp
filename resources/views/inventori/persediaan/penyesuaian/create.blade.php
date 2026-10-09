@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold text-dark"><i class="bi bi-sliders me-2 text-primary"></i> Buat Penyesuaian Stok Baru</h3>
            <div class="text-secondary small">Auto-load item selisih dari Ref SO.</div>
        </div>
        <a href="{{ route('inventori.penyesuaian.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Kembali ke List
        </a>
    </div>

    <form action="{{ route('inventori.penyesuaian.store') }}" method="POST">
        @csrf
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">No. Adjustment (Auto)</label>
                        <input type="text" class="form-control font-monospace fw-bold bg-light" value="{{ $autoCode }}" readonly>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Ref Stock Opname</label>
                        <select name="stock_opname_id" id="select-ref-opname" class="form-select">
                            <option value="">-- Pilih Ref Opname (Opsional) --</option>
                            @foreach($postedOpnames as $po)
                                <option value="{{ $po->id }}" {{ (string) $fromOpnameId === (string) $po->id ? 'selected' : '' }}>
                                    {{ $po->opname_no }} ({{ $po->warehouse_name }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Unit Bisnis <span class="text-danger">*</span></label>
                        <select name="business_unit_id" class="form-select" required>
                            <option value="">-- Pilih Unit Bisnis --</option>
                            @foreach($businessUnits as $bu)
                                <option value="{{ $bu->id }}" {{ $selectedOpname && $selectedOpname->business_unit_id == $bu->id ? 'selected' : '' }}>
                                    {{ $bu->code }} - {{ $bu->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Lokasi Gudang <span class="text-danger">*</span></label>
                        <select name="warehouse_id" class="form-select" required>
                            <option value="">-- Pilih Gudang --</option>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}" {{ $selectedOpname && $selectedOpname->warehouse_id == $w->id ? 'selected' : '' }}>
                                    {{ $w->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Tanggal Adjustment <span class="text-danger">*</span></label>
                        <x-date-input-id name="adjustment_date" :value="date('Y-m-d')" required />
                    </div>

                    <div class="col-md-9">
                        <label class="form-label fw-bold">Alasan / Catatan Penyesuaian</label>
                        <input type="text" name="reason" class="form-control" placeholder="Contoh: Eksekusi selisih hasil stock opname" value="{{ $selectedOpname ? 'Eksekusi Selisih Stock Opname #' . $selectedOpname->opname_no : '' }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-dark text-white py-2 d-flex justify-content-between align-items-center">
                <span class="fw-bold small"><i class="bi bi-box-seam me-1"></i> Item Barang Disesuaikan (Hanya Item Ber-Selisih)</span>
                <span class="badge bg-warning text-dark">{{ count($varianceItems) }} Item Loaded</span>
            </div>
            <div class="card-body p-0">
                @if($selectedOpname)
                    <div class="alert alert-info rounded-0 mb-0 py-2 small">
                        Kuantitas dikunci mengikuti selisih Stock Opname yang sudah difinalisasi. Jika stok berubah setelah opname, posting akan ditolak dan perlu opname ulang.
                    </div>
                @endif
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle mb-0" style="font-size: 0.88rem;">
                        <thead class="table-light text-center">
                            <tr>
                                <th style="width: 40px;">No</th>
                                <th style="width: 140px;">Kode Barang</th>
                                <th>Nama Produk</th>
                                <th style="width: 120px;">Stok Sistem</th>
                                <th style="width: 120px;">Stok Fisik</th>
                                <th style="width: 140px;">Adjustment Qty (+/-)</th>
                                <th style="width: 120px;">Final Qty</th>
                                <th style="width: 130px;">HPP (unit_cost)</th>
                                <th style="width: 80px;">Satuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($varianceItems as $idx => $item)
                                @php
                                    $sys  = (float) $item->system_qty;
                                    $act  = (float) $item->actual_qty;
                                    $adj  = (float) $item->difference;
                                    $cost = (float) ($item->avg_cost ?? 0);
                                @endphp
                                <tr>
                                    <td class="text-center">{{ $idx + 1 }}</td>
                                    <td class="font-monospace fw-semibold text-center">{{ $item->product_code }}</td>
                                    <td>
                                        <input type="hidden" name="products[]" value="{{ $item->product_id }}">
                                        <input type="hidden" name="system_qty[]" value="{{ $sys }}">
                                        <div class="fw-semibold text-dark">{{ $item->product_name }}</div>
                                    </td>
                                      <td class="text-center bg-light">{{ number_format($sys, 2, ',', '.') }}</td>
                                      <td class="text-center bg-light fw-bold">{{ number_format($act, 2, ',', '.') }}</td>
                                      <td>
                                          <input type="number" name="adjustment_qty[]" class="form-control form-control-sm text-center fw-bold input-adj-val {{ $adj < 0 ? 'text-danger' : 'text-success' }}"
                                                 data-sys="{{ $sys }}" step="0.001" value="{{ $adj }}" required {{ $selectedOpname ? 'readonly' : '' }}>
                                      </td>
                                      <td class="text-center font-monospace fw-bold cell-final-val">{{ number_format($sys + $adj, 2, ',', '.') }}</td>
                                      <td class="text-end font-monospace">Rp {{ number_format($cost, 0, ',', '.') }}</td>
                                    <td class="text-center">{{ $item->unit_name ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        Pilih <strong>Ref Stock Opname</strong> di atas untuk memuat item selisih secara otomatis.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light p-3 text-end">
                <a href="{{ route('inventori.penyesuaian.index') }}" class="btn btn-secondary me-2">Batal</a>
                <button type="submit" name="save_draft" value="1" class="btn btn-outline-primary me-2">
                    <i class="bi bi-save me-1"></i> Simpan Draft
                </button>
                <button type="submit" name="post_now" value="1" class="btn btn-success px-4 fw-bold">
                    <i class="bi bi-check-circle me-1"></i> Simpan & Posting
                </button>
            </div>
        </div>
    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('select-ref-opname')?.addEventListener('change', function () {
        const opId = this.value;
        if (opId) {
            window.location.href = `{{ route('inventori.penyesuaian.create') }}?from_opname=${opId}`;
        }
    });

    document.querySelectorAll('.input-adj-val').forEach(input => {
        input.addEventListener('input', function () {
            const sys = parseFloat(this.dataset.sys);
            const adj = parseFloat(this.value) || 0;
            const finalQty = sys + adj;

            const row = this.closest('tr');
            row.querySelector('.cell-final-val').innerText = finalQty.toFixed(2);
        });
    });
});
</script>
@endsection
