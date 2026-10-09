@extends('layouts.app')

@section('content')
<div class="container-fluid px-0 py-0">

    <!-- Header & Action Buttons -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold text-dark">Penyesuaian Stok / Adjustment</h3>
            <div class="text-secondary small">Koreksi Stok Fisik, Pergerakan Stock Movements</div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('inventori.penyesuaian.create') }}" class="btn btn-primary btn-sm px-3 fw-semibold shadow-sm">
                <i class="bi bi-plus-circle me-1"></i> + Penyesuaian Baru
            </a>
            <a href="{{ route('inventori.penyesuaian.print-list', request()->all()) }}" target="_blank" class="btn btn-secondary btn-sm px-3 fw-semibold shadow-sm">
                <i class="bi bi-printer me-1"></i> Cetak List
            </a>
        </div>
    </div>

    <!-- Filter Card -->
     <div class="card shadow-sm border-0 mb-2">
        <div class="card-body p-3">
            <form method="GET" action="{{ route('inventori.penyesuaian.index') }}" class="d-flex align-items-center gap-2 flex-nowrap">
                <label class="small fw-bold mb-0 text-nowrap">Mulai</label>
                <input type="date" id="filter-start-date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}" style="width: 145px;">

                <label class="small fw-bold mb-0 text-nowrap">Sampai</label>
                <input type="date" id="filter-end-date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}" style="width: 145px;">

                <label class="small fw-bold mb-0 text-nowrap">Unit Bisnis</label>
                <select id="filter-bu" name="business_unit_id" class="form-select form-select-sm" style="width: 210px;">
                    <option value="">Semua Unit Bisnis</option>
                    @foreach($businessUnits as $bu)
                        <option value="{{ $bu->id }}" {{ (string) $businessUnitId === (string) $bu->id ? 'selected' : '' }}>{{ $bu->code }} - {{ $bu->name }}</option>
                    @endforeach
                </select>

                <label class="small fw-bold mb-0 text-nowrap">Gudang</label>
                <select id="filter-wh" name="warehouse_id" class="form-select form-select-sm" style="width: 190px;">
                    <option value="">Semua Gudang</option>
                    @foreach($warehouses as $w)
                        <option value="{{ $w->id }}" {{ (string) $warehouseId === (string) $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                    @endforeach
                </select>

                <button type="submit" class="btn btn-primary btn-sm text-nowrap">
                    <i class="bi bi-search me-1"></i>Filter
                </button>
                <a href="{{ route('inventori.penyesuaian.index') }}" class="btn btn-outline-secondary btn-sm text-nowrap">Reset</a>
            </form>
        </div>
    </div>

    <!-- DataTables Table -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="table-adjustments" class="table table-hover table-striped align-middle w-100" style="font-size: 0.88rem;">
                    <thead class="table-dark text-center">
                        <tr>
                            <th style="width: 90px;">Tanggal</th>
                            <th style="width: 150px;">No. Adjustment</th>
                            <th style="width: 140px;">Ref Opname</th>
                            <th>Gudang</th>
                            <th style="width: 140px;">Unit Bisnis</th>
                            <th style="width: 130px;">Total Nilai</th>
                            <th style="width: 90px;">Status</th>
                            <th style="width: 200px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- DataTables & SweetAlert2 JS Integration -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    // SweetAlert2 Session Flash Message Popups
    @if(session('swal_success'))
        Swal.fire({
            icon: 'success',
            title: 'Berhasil!',
            text: "{{ session('swal_success') }}",
            timer: 3000,
            showConfirmButton: false
        });
    @endif

    @if(session('swal_error'))
        Swal.fire({
            icon: 'error',
            title: 'Gagal!',
            text: "{{ session('swal_error') }}",
            confirmButtonColor: '#d33'
        });
    @endif

    // DataTables AJAX Initialization
    const table = $('#table-adjustments').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ route('inventori.penyesuaian.data') }}",
            data: function (d) {
                d.start_date       = $('#filter-start-date').val();
                d.end_date         = $('#filter-end-date').val();
                d.business_unit_id = $('#filter-bu').val();
                d.warehouse_id     = $('#filter-wh').val();
            }
        },
        columns: [
            { data: 'formatted_date', className: 'text-center' },
            { data: 'adjustment_no', className: 'text-center font-monospace fw-bold text-primary' },
            { data: 'opname_no', className: 'text-center font-monospace', defaultContent: 'Manual' },
            { data: 'warehouse_name', className: 'fw-semibold text-dark' },
            { data: 'business_unit_name', className: 'text-center', defaultContent: '-' },
            { data: 'formatted_total', className: 'text-end fw-bold font-monospace' },
            {
                data: 'status',
                className: 'text-center',
                render: function (data) {
                    return data === 'draft'
                        ? '<span class="badge bg-secondary"><i class="bi bi-pencil me-1"></i> DRAFT</span>'
                        : '<span class="badge bg-success"><i class="bi bi-check-all me-1"></i> POSTED</span>';
                }
            },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render: function (data, type, row) {
                    let actions = `
                        <div class="btn-group btn-group-sm">
                            <a href="{{ url('/inventori/penyesuaian') }}/${row.id}" class="btn btn-outline-info" title="Lihat Detail">
                                <i class="bi bi-eye"></i>
                            </a>
                            <a href="{{ url('/inventori/penyesuaian') }}/${row.id}/print-detail" target="_blank" class="btn btn-outline-secondary" title="Cetak Voucher">
                                <i class="bi bi-printer"></i>
                            </a>
                    `;

                    if (row.status === 'draft') {
                        actions += `
                            <a href="{{ url('/inventori/penyesuaian') }}/${row.id}/edit" class="btn btn-outline-warning" title="Edit Draft">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <button type="button" class="btn btn-success btn-post-item" data-id="${row.id}" data-no="${row.adjustment_no}" title="Posting">
                                <i class="bi bi-check-circle"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-delete-item" data-id="${row.id}" data-no="${row.adjustment_no}" title="Hapus">
                                <i class="bi bi-trash"></i>
                            </button>
                        `;
                    }

                    actions += `</div>`;
                    return actions;
                }
            }
        ]
    });

    // Filter Trigger
    $('#btn-apply-filter').on('click', function () { table.ajax.reload(); });
    $('#btn-reset-filter').on('click', function () {
        $('#form-filter')[0].reset();
        table.ajax.reload();
    });

    // SweetAlert2 Confirmation for Posting
    $(document).on('click', '.btn-post-item', function () {
        const id = $(this).data('id');
        const no = $(this).data('no');

        Swal.fire({
            title: 'Posting Penyesuaian Stok?',
            text: `Posting [${no}] akan merubah saldo stok gudang & menerbitkan jurnal GL. Tindakan ini bersifat final!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#198754',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Post Sekarang!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                  form.action = `{{ url('/inventori/penyesuaian') }}/${id}/post`;
                form.innerHTML = `@csrf`;
                document.body.appendChild(form);
                form.submit();
            }
        });
    });

    // SweetAlert2 Confirmation for Delete
    $(document).on('click', '.btn-delete-item', function () {
        const id = $(this).data('id');
        const no = $(this).data('no');

        Swal.fire({
            title: 'Hapus Draft Penyesuaian?',
            text: `Draft [${no}] akan dihapus permanent dari sistem!`,
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = document.createElement('form');
                form.method = 'POST';
                  form.action = `{{ url('/inventori/penyesuaian') }}/${id}`;
                form.innerHTML = `@csrf @method('DELETE')`;
                document.body.appendChild(form);
                form.submit();
            }
        });
    });

});
</script>
@endsection
