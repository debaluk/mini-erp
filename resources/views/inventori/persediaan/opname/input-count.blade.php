@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold text-dark"><i class="bi bi-calculator me-2 text-primary"></i> Tahap 2: Input Hasil Perhitungan Fisik</h3>
            <div class="text-secondary small">Dokumen: <strong class="font-monospace text-primary">{{ $opname->opname_no }}</strong> | Gudang: <strong>{{ $opname->warehouse_name }}</strong> | Tgl: {{ \Carbon\Carbon::parse($opname->opname_date)->format('d/m/Y') }}</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('inventori.stock-opname.print-sheet', $opname->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-printer me-1"></i> Cetak Blind Count Sheet
            </a>
            <a href="{{ route('inventori.stock-opname.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke List SO
            </a>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="alert alert-warning py-2 small" role="alert">
        <strong>Perhatian:</strong> tombol finalisasi mengunci hasil hitung Stock Opname. Stok sistem tidak berubah dari proses ini; selisih diproses terpisah melalui menu <strong>Penyesuaian Stok</strong>.
    </div>

    <form action="{{ route('inventori.stock-opname.store-count', $opname->id) }}" method="POST">
        @csrf
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-dark text-white py-2 d-flex justify-content-between align-items-center">
                <span class="fw-bold small"><i class="bi bi-box-seam me-1"></i> Input Hasil Fisik & Kalkulasi Selisih (Total {{ count($items) }} Item Barang)</span>
                <span class="badge bg-primary">Gudang: {{ $opname->warehouse_name }}</span>
            </div>
            <div class="card-body p-0">
                <div class="p-3 border-bottom bg-light d-flex justify-content-end">
                    <div class="col-md-6">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="search" id="searchItem" class="form-control"
                                   placeholder="Cari kode atau nama barang..." autocomplete="off">
                            <button type="button" class="btn btn-outline-secondary" id="clearSearch">
                                Bersihkan
                            </button>
                        </div>
                    </div>
                </div>
                <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-hover table-bordered align-middle mb-0" style="font-size: 0.84rem;">
                        <thead class="table-light text-center sticky-top">
                            <tr>
                                <th style="width: 40px;">No</th>
                                <th style="width: 130px;">Kode Barang</th>
                                <th>Nama Produk / Deskripsi Barang</th>
                                <th style="width: 140px;">Stok Sistem</th>
                                <th style="width: 180px;">Hasil Fisik (Actual Qty)</th>
                                <th style="width: 140px;">Selisih (Varian)</th>
                                <th style="width: 100px;">Satuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $idx => $i)
                                @php
                                    $sys  = (float) $i->system_qty;
                                    $act  = (float) $i->actual_qty;
                                    $diff = $act - $sys;
                                @endphp
                                <tr data-item-search="{{ strtolower($i->product_code . ' ' . $i->product_name) }}">
                                    <td class="text-center">{{ $idx + 1 }}</td>
                                    <td class="font-monospace fw-semibold text-center">{{ $i->product_code }}</td>
                                    <td>
                                        <input type="hidden" name="item_ids[]" value="{{ $i->id }}">
                                        <div class="fw-semibold text-dark">{{ $i->product_name }}</div>
                                    </td>
                                    <td class="text-center fw-bold bg-light">{{ number_format($sys, 2, ',', '.') }}</td>
                                    <td class="p-0">
                                        <input type="number" name="actual_qty[]" class="form-control form-control-sm text-center fw-bold input-actual-val"
                                               data-sys="{{ $sys }}" step="0.001" value="{{ $act }}" required>
                                    </td>
                                    <td class="text-center fw-bold cell-diff-val {{ $diff < 0 ? 'text-danger' : ($diff > 0 ? 'text-warning' : 'text-success') }}">
                                        {{ $diff > 0 ? '+' . number_format($diff, 2, ',', '.') : number_format($diff, 2, ',', '.') }}
                                    </td>
                                    <td class="text-center">{{ $i->unit_name ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                <a href="{{ route('inventori.stock-opname.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Kembali ke List SO
                </a>
                <div>
                    <button type="button" class="btn btn-secondary me-2" onclick="window.location.reload()">
                        Batal
                    </button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold" onclick="return confirm('Finalisasi hasil hitung Stock Opname? Setelah finalisasi, hasil fisik tidak dapat diubah. Selisih diproses melalui Penyesuaian Stok.');">
                        <i class="bi bi-check2-circle me-1"></i> Finalisasi Hasil Fisik
                    </button>
                </div>
            </div>
        </div>
    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Search barang — client-side, tidak reload dan tidak menghilangkan input
    const searchItem = document.getElementById('searchItem');
    const clearSearch = document.getElementById('clearSearch');

    function filterItems() {
        const keyword = searchItem.value.trim().toLowerCase();

        document.querySelectorAll('tbody tr[data-item-search]').forEach(row => {
            row.style.display = !keyword || row.dataset.itemSearch.includes(keyword) ? '' : 'none';
        });
    }

    searchItem.addEventListener('input', filterItems);

    clearSearch.addEventListener('click', function () {
        searchItem.value = '';
        filterItems();
        searchItem.focus();
    });

    // Realtime Kalkulasi Selisih saat Angka Fisik Diketik
    document.querySelectorAll('.input-actual-val').forEach(input => {
        input.addEventListener('input', function () {
            const sysVal  = parseFloat(this.dataset.sys);
            const actVal  = parseFloat(this.value) || 0;
            const diffVal = actVal - sysVal;

            const row = this.closest('tr');
            const cellDiff = row.querySelector('.cell-diff-val');

            cellDiff.innerText = diffVal > 0 ? '+' + diffVal.toFixed(2) : diffVal.toFixed(2);

            if (Math.abs(diffVal) < 0.0001) {
                cellDiff.className = 'text-center fw-bold cell-diff-val text-success';
            } else if (diffVal < 0) {
                cellDiff.className = 'text-center fw-bold cell-diff-val text-danger';
            } else {
                cellDiff.className = 'text-center fw-bold cell-diff-val text-warning';
            }
        });
    });
});
</script>
@endsection