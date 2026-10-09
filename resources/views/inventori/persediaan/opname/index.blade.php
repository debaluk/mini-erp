@extends('layouts.app')

@section('content')
<div class="container-fluid px-0 py-0">

    <!-- Header & Action Buttons -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold text-dark">Laporan Stock Opname</h3>
            <div class="text-secondary small">Audit Perhitungan Fisik Persediaan & Laporan Varian Selisih Gudang</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('inventori.stock-opname.create') }}" class="btn btn-primary btn-sm px-3 fw-semibold shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> + Stock Opname
            </a>
            <a href="{{ route('inventori.stock-opname.export-list', request()->all()) }}" class="btn btn-success btn-sm px-3 fw-semibold shadow-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel List
            </a>
        </div>
    </div>

    <!-- Notifications Popup -->
    @if(session('success') || session('error'))
        <div class="modal fade" id="notificationModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header {{ session('success') ? 'bg-success' : 'bg-danger' }} text-white">
                        <h5 class="modal-title">
                            <i class="bi {{ session('success') ? 'bi-check-circle' : 'bi-exclamation-triangle' }} me-2"></i>
                            {{ session('success') ? 'Berhasil' : 'Gagal' }}
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-center py-4">
                        {{ session('success') ?? session('error') }}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">OK</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Filter Bar -->
    <div class="card shadow-sm border-0 mb-2">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('inventori.stock-opname.index') }}" class="d-flex align-items-center gap-2 flex-nowrap">
                <label class="small fw-bold mb-0 text-nowrap">Mulai</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}" style="width: 145px;">

                <label class="small fw-bold mb-0 text-nowrap">Sampai</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}" style="width: 145px;">

                <label class="small fw-bold mb-0 text-nowrap">Unit Bisnis</label>
                <select name="business_unit_id" class="form-select form-select-sm" style="width: 210px;">
                    <option value="">Semua Unit Bisnis</option>
                    @foreach($businessUnits as $bu)
                        <option value="{{ $bu->id }}" {{ (string) $businessUnitId === (string) $bu->id ? 'selected' : '' }}>{{ $bu->code }} - {{ $bu->name }}</option>
                    @endforeach
                </select>

                <label class="small fw-bold mb-0 text-nowrap">Gudang</label>
                <select name="warehouse_id" class="form-select form-select-sm" style="width: 190px;">
                    <option value="">Semua Gudang</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" {{ (string) $warehouseId === (string) $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                    @endforeach
                </select>

                <button type="submit" class="btn btn-primary btn-sm text-nowrap">
                    <i class="bi bi-search me-1"></i>Filter
                </button>
                <a href="{{ route('inventori.stock-opname.index') }}" class="btn btn-outline-secondary btn-sm text-nowrap">Reset</a>
            </form>
        </div>
    </div>

    <!-- Table List Stock Opname -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="table-opname" class="table table-hover table-striped align-middle mb-0" style="font-size: 0.88rem;">
                    <thead class="table-dark text-center">
                        <tr>
                            <th style="width: 100px;">Tgl. Opname</th>
                            <th style="width: 160px;">No. Opname</th>
                            <th class="text-start">Lokasi Gudang</th>
                            <th style="width: 150px;">Unit Bisnis</th>
                            <th style="width: 120px;">Petugas</th>
                            <th style="width: 110px;">Total Item</th>
                            <th style="width: 130px;">Status / Selisih</th>
                            <th style="width: 250px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($opnames as $op)
                            <tr>
                                <td class="text-center">{{ \Carbon\Carbon::parse($op->opname_date)->format('d/m/Y') }}</td>
                                <td class="text-center font-monospace fw-bold text-primary">{{ $op->opname_no }}</td>
                                <td><div class="fw-semibold text-dark">{{ $op->warehouse_name }}</div></td>
                                <td class="text-center">{{ $op->business_unit_name ?? '-' }}</td>
                                <td class="text-center">{{ $op->creator_name }}</td>
                                <td class="text-center fw-semibold">{{ $op->total_items }} Barang</td>
                                <td class="text-center">
                                    @if($op->status === 'draft')
                                        <span class="badge bg-secondary"><i class="bi bi-pencil-square me-1"></i> DRAFT</span>
                                        <div class="small text-muted mt-1">Selisih: {{ $op->total_variance_items }} item</div>
                                    @elseif($op->status === 'posted')
                                        @if($op->total_variance_items > 0)
                                            <span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i> PERHATIAN</span>
                                            <div class="small text-danger fw-semibold mt-1">POSTED · Selisih: {{ $op->total_variance_items }} item</div>
                                        @else
                                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> POSTED</span>
                                        @endif
                                    @else
                                        <span class="badge bg-light text-dark">{{ strtoupper($op->status) }}</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Step 2: Input Hasil Fisik (Jika masih Draft/Snapshot) -->
                                        @if($op->status === 'draft')
                                            @if($op->has_physical_count)
                                                <a href="{{ route('inventori.stock-opname.show', $op->id) }}" class="btn btn-outline-info" title="Lihat Detail">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            @else
                                                <a href="{{ route('inventori.stock-opname.edit', $op->id) }}" class="btn btn-outline-primary" title="Edit Snapshot">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                            @endif
                                            <a href="{{ route('inventori.stock-opname.print-sheet', $op->id) }}" target="_blank" class="btn btn-outline-secondary" title="Cetak Lembar Blind Count">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                            <a href="{{ route('inventori.stock-opname.input-count', $op->id) }}" class="btn btn-outline-success" title="Isi Hasil Fisik">
                                                <i class="bi bi-calculator"></i>
                                            </a>
                                        @else
                                            <!-- Lihat Detail -->
                                            <a href="{{ route('inventori.stock-opname.show', $op->id) }}" class="btn btn-outline-info" title="Lihat Detail">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <!-- Cetak Laporan Varian -->
                                            <a href="{{ route('inventori.stock-opname.print-report', $op->id) }}" target="_blank" class="btn btn-outline-secondary" title="Cetak Laporan SO">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                            <!-- Export Excel per No. SO -->
                                            <a href="{{ route('inventori.stock-opname.export-detail', $op->id) }}" class="btn btn-outline-success" title="Excel Detail SO">
                                                <i class="bi bi-file-earmark-excel"></i>
                                            </a>
                                        @endif

                                        <!-- Hapus Dokumen: hanya Draft/Snapshot -->
                                        @if($op->status === 'draft')
                                            <button type="button" class="btn btn-outline-danger" title="Hapus Dokumen"
                                                data-bs-toggle="modal" data-bs-target="#deleteOpnameModal{{ $op->id }}">
                                                <i class="bi bi-trash"></i>
                                            </button>

                                            <div class="modal fade" id="deleteOpnameModal{{ $op->id }}" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-danger text-white">
                                                            <h5 class="modal-title">
                                                                <i class="bi bi-exclamation-triangle me-2"></i>Konfirmasi Hapus
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            Yakin ingin menghapus Stock Opname
                                                            <strong>{{ $op->opname_no }}</strong>?
                                                            <br>
                                                            <small class="text-muted">Data Stock Opname ini akan dihapus.</small>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                            <form action="{{ route('inventori.stock-opname.destroy', $op->id) }}" method="POST" class="d-inline">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="btn btn-danger">
                                                                    <i class="bi bi-trash me-1"></i> Ya, Hapus
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>
@push("scripts")
<script>
document.addEventListener("DOMContentLoaded", function () {

    new DataTable("#table-opname", {
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[0, "desc"]],
        language: {
            search: "Cari:",
            lengthMenu: "Tampilkan _MENU_ data",
            info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ data",
            infoEmpty: "Tidak ada data",
            zeroRecords: "Data tidak ditemukan",
            paginate: {
                first: "Awal",
                last: "Akhir",
                next: "›",
                previous: "‹"
            }
        }
    });

    const notificationModal = document.getElementById("notificationModal");
    if (notificationModal) {
        new bootstrap.Modal(notificationModal).show();
    }

});
</script>
@endpush
@endsection
