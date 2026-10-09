@extends('layouts.app')

@section('content')
<div class="container-fluid px-0 py-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
                <div><h3 class="mb-1 fw-bold text-dark"><i class="bi bi-arrow-return-left me-2 text-primary"></i>Retur Pembelian</h3><div class="text-secondary small">Daftar retur pembelian berdasarkan Penerimaan Barang sumber</div></div>
        <a href="{{ route('inventori.pembelian.retur.print-list') }}" id="btn-print-list" target="_blank" class="btn btn-outline-primary btn-sm"><i class="bi bi-printer me-1"></i>Cetak List</a>
    </div>
    <div class="card shadow-sm border-0 mb-2"><div class="card-body p-3">
        <form id="form-filter" class="row g-2 align-items-end">
            <div class="col-md-2"><label class="form-label mb-1 small fw-bold">Mulai Tanggal</label><input type="date" id="filter-start-date" class="form-control form-control-sm" value="{{ $startDate }}"></div>
            <div class="col-md-2"><label class="form-label mb-1 small fw-bold">Sampai Tanggal</label><input type="date" id="filter-end-date" class="form-control form-control-sm" value="{{ $endDate }}"></div>
            <div class="col-md-3"><label class="form-label mb-1 small fw-bold">Unit Bisnis</label><select id="filter-business-unit" class="form-select form-select-sm"><option value="">Semua Unit Bisnis</option>@foreach($businessUnits as $bu)<option value="{{ $bu->id }}" @selected((string) $businessUnitId === (string) $bu->id)>{{ $bu->code }} - {{ $bu->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label mb-1 small fw-bold">Gudang</label><select id="filter-warehouse" class="form-select form-select-sm"><option value="">Semua Gudang</option>@foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" @selected((string) $warehouseId === (string) $warehouse->id)>{{ $warehouse->name }}</option>@endforeach</select></div>
            <div class="col-md-1"><button type="button" id="btn-apply-filter" class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i>Tampil</button></div>
            <div class="col-md-1"><button type="button" id="btn-reset-filter" class="btn btn-outline-secondary btn-sm w-100">Reset</button></div>
        </form>
    </div></div>
    <div class="card shadow-sm border-0"><div class="card-body p-0"><div class="table-responsive">
        <table id="table-retur-pembelian" class="table table-hover table-striped align-middle w-100" style="font-size:0.88rem;">
            <thead class="table-dark text-center"><tr><th>No. Retur</th><th>Tanggal</th><th>Penerimaan Sumber</th><th>Unit Bisnis</th><th>Supplier</th><th>Gudang</th><th>Barang</th><th>Qty</th><th>Total</th><th>Status</th><th style="width:110px;">Aksi</th></tr></thead>
            <tbody></tbody>
        </table>
    </div></div></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    @if(session('swal_success')) Swal.fire({ icon: 'success', title: 'Berhasil!', text: @json(session('swal_success')), timer: 2500, showConfirmButton: false }); @endif
    @if(session('swal_error')) Swal.fire({ icon: 'error', title: 'Gagal!', text: @json(session('swal_error')), confirmButtonColor: '#d33' }); @endif

    const table = $('#table-retur-pembelian').DataTable({
        processing: true, serverSide: false,
        ajax: { url: "{{ route('inventori.pembelian.retur.data') }}", data: function (d) { d.start_date = $('#filter-start-date').val(); d.end_date = $('#filter-end-date').val(); d.business_unit_id = $('#filter-business-unit').val(); d.warehouse_id = $('#filter-warehouse').val(); } },
        columns: [
            { data: 'return_no', className: 'text-center font-monospace fw-bold text-primary' },
            { data: 'return_date', className: 'text-center' },
            { data: 'source_receipt_no', className: 'text-center font-monospace' },
            { data: 'business_unit_name', className: 'text-center' },
            { data: 'supplier_name', className: 'fw-semibold' },
            { data: 'warehouse_name', className: 'text-center' },
            { data: 'product_names', className: 'small' },
            { data: 'return_qty', className: 'text-end', render: data => Number(data || 0).toLocaleString('id-ID', { maximumFractionDigits: 3 }) },
            { data: 'total', className: 'text-end fw-bold font-monospace', render: data => 'Rp ' + Number(data || 0).toLocaleString('id-ID') },
            { data: 'status', className: 'text-center', render: data => data === 'posted' ? '<span class="badge bg-success">TERPOSTING</span>' : (data === 'cancelled' ? '<span class="badge bg-danger">DIBATALKAN</span>' : '<span class="badge bg-secondary">DRAFT</span>') },
            {
                data: null, className: 'text-center', orderable: false,
                render: function (data, type, row) {
                    let html = '<div class="btn-group btn-group-sm">';
                    html += '<a href="{{ url('/inventori/pembelian/retur') }}/' + row.id + '" class="btn btn-outline-info" title="Detail"><i class="bi bi-eye"></i></a>';
                    html += '<a href="{{ url('/inventori/pembelian/retur') }}/' + row.id + '/print" target="_blank" class="btn btn-outline-secondary" title="Cetak"><i class="bi bi-printer"></i></a>';
                    if (row.status === 'draft') html += '<button type="button" class="btn btn-success btn-post-return" data-id="' + row.id + '" data-no="' + row.return_no + '" title="Post"><i class="bi bi-check-circle"></i></button>';
                    return html + '</div>';
                }
            }
        ]
    });

    $('#btn-print-list').on('click', function () { const q = new URLSearchParams({ start_date: $('#filter-start-date').val(), end_date: $('#filter-end-date').val(), business_unit_id: $('#filter-business-unit').val(), warehouse_id: $('#filter-warehouse').val() }); this.href = '{{ route('inventori.pembelian.retur.print-list') }}?' + q.toString(); });

    $('#btn-apply-filter').on('click', () => table.ajax.reload());
    $('#filter-business-unit, #filter-warehouse').on('change', () => table.ajax.reload());
    $('#btn-reset-filter').on('click', function () {
        $('#filter-start-date').val('{{ now()->startOfMonth()->toDateString() }}');
        $('#filter-end-date').val('{{ now()->endOfMonth()->toDateString() }}');
        $('#filter-business-unit').val('');
        $('#filter-warehouse').val('');
        table.ajax.reload();
    });

    $(document).on('click', '.btn-post-return', function () {
        const id = $(this).data('id'), no = $(this).data('no');
        Swal.fire({ title: 'Posting Retur Pembelian?', html: 'Retur <strong>' + no + '</strong> akan mengurangi stok dan membuat jurnal. Lanjutkan?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#198754', confirmButtonText: 'Ya, Posting Retur', cancelButtonText: 'Batal' })
        .then(result => {
            if (!result.isConfirmed) return;
            const form = document.createElement('form');
            form.method = 'POST'; form.action = '{{ url('/inventori/pembelian/retur') }}/' + id + '/post';
            form.innerHTML = '@csrf'; document.body.appendChild(form); form.submit();
        });
    });
});
</script>
@endsection
