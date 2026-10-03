@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Hasil Produksi</h4>
        <div class="text-secondary small">Pencatatan hasil aktual dari SPK yang sedang diproses.</div>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Tanggal Awal</label>
                <input type="date" class="form-control" value="{{ request('start_date', now()->startOfMonth()->toDateString()) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tanggal Akhir</label>
                <input type="date" class="form-control" value="{{ request('end_date', now()->endOfMonth()->toDateString()) }}">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-primary w-100">Tampilkan</button>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-primary bg-opacity-10 text-primary border-bottom d-flex justify-content-between align-items-center py-3">
        <div class="fw-semibold">
            <i class="bi bi-box-arrow-in-down me-2"></i>SPK Siap Hasil Produksi
        </div>
        <span class="badge text-bg-primary">{{ $rows->count() }} SPK</span>
    </div>

    <div class="card-body">
        <div id="productionResultControls" class="d-flex justify-content-between align-items-center flex-nowrap gap-3 mb-3">
            <div class="d-flex align-items-center gap-2 flex-nowrap">
                <label for="productionResultPageLength" class="mb-0 text-nowrap">Tampilkan</label>
                <select id="productionResultPageLength" class="form-select form-select-sm" style="width:80px;">
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span class="text-nowrap">baris</span>
            </div>
            <div class="d-flex align-items-center gap-2 flex-nowrap ms-auto">
                <label for="productionResultSearch" class="mb-0 text-nowrap">Cari:</label>
                <input type="search" id="productionResultSearch" class="form-control form-control-sm" style="width:240px;" placeholder="Cari...">
            </div>
        </div>

        <div class="table-responsive">
            <table id="productionResultTable" class="table table-hover align-middle mb-0 w-100">
                <thead class="table-primary">
                    <tr>
                        <th>No. SPK</th>
                        <th>Tanggal</th>
                        <th>Produk</th>
                        <th class="text-end">Target</th>
                        <th>Gudang</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="fw-semibold">{{ $row->production_no ?? $row->work_order_no ?? '-' }}</td>
                        <td>{{ isset($row->wo_date) ? \Carbon\Carbon::parse($row->wo_date)->format('d/m/Y') : '-' }}</td>
                        <td>{{ $row->product_name ?? '-' }}</td>
                        <td class="text-end">{{ isset($row->target_qty) ? \App\Helpers\FormatHelper::indo((float) $row->target_qty, 2) : '-' }}</td>
                        <td>{{ $row->warehouse_name ?? '-' }}</td>
                        <td><span class="badge text-bg-primary">On Progress</span></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#hasilProduksiModal">
                                <i class="bi bi-plus-circle me-1"></i>Hasil
                            </button>
                        </td>
                    </tr>
                @empty
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="hasilProduksiModal" tabindex="-1" aria-labelledby="hasilProduksiModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary bg-opacity-10 text-primary">
                <h5 class="modal-title" id="hasilProduksiModalLabel">
                    <i class="bi bi-box-seam me-2"></i>Input Hasil Produksi
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border mb-4">
                    <div class="fw-semibold mb-1">SPK yang dipilih</div>
                    <div class="text-secondary small">Detail SPK akan ditampilkan setelah SPK dipilih dari daftar.</div>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tanggal Hasil</label>
                        <input type="date" class="form-control" value="{{ now()->toDateString() }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Hasil Bagus</label>
                        <input type="number" class="form-control" min="0" step="0.01" placeholder="0,00">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Reject / Scrap</label>
                        <input type="number" class="form-control" min="0" step="0.01" value="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Catatan</label>
                        <textarea class="form-control" rows="3" placeholder="Catatan hasil produksi (opsional)"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary">Simpan Hasil Produksi</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<style>
    #productionResultControls {
        width: 100%;
    }

    #productionResultControls > div {
        min-width: 0;
    }

    #productionResultTable_wrapper .dataTables_filter,
    #productionResultTable_wrapper .dataTables_length {
        display: none !important;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.jQuery) return;

    const table = $('#productionResultTable').DataTable({
        dom: 't<"d-flex justify-content-between align-items-center px-3 py-3"i p>',
        pageLength: 15,
        lengthMenu: [[15, 25, 50, 100], [15, 25, 50, 100]],
        autoWidth: false,
        order: [[1, 'desc']],
        language: {
            info: 'Menampilkan _START_–_END_ dari _TOTAL_ SPK',
            infoEmpty: 'Tidak ada SPK',
            zeroRecords: 'Data tidak ditemukan',
            paginate: {
                previous: '‹',
                next: '›'
            }
        },
        columnDefs: [
            { targets: [6], orderable: false }
        ]
    });

    $('#productionResultPageLength').on('change', function () {
        table.page.len(this.value).draw();
    });

    $('#productionResultSearch').on('input', function () {
        table.search(this.value).draw();
    });
});
</script>
@endpush
