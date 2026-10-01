@extends('layouts.app')
@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold text-dark">Penerimaan Barang</h3>
            <div class="text-secondary small">Daftar penerimaan barang dari Purchase Order</div>
        </div>
        <a href="{{ route('inventori.penerimaan.export', request()->all()) }}" class="btn btn-success btn-sm px-3 fw-semibold shadow-sm">
            <i class="bi bi-file-earmark-excel me-1"></i> Export List Penerimaan
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form id="form-filter" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Tanggal Mulai</label>
                    <input type="date" id="filter-start-date" class="form-control form-control-sm" value="{{ $startDate }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Tanggal Akhir</label>
                    <input type="date" id="filter-end-date" class="form-control form-control-sm" value="{{ $endDate }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Unit Bisnis</label>
                    <select id="filter-bu" class="form-select form-select-sm">
                        <option value="">Semua Unit Bisnis</option>
                        @foreach($businessUnits as $bu)
                            <option value="{{ $bu->id }}">{{ $bu->code }} - {{ $bu->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Supplier</label>
                    <select id="filter-supplier" class="form-select form-select-sm">
                        <option value="">Semua Supplier</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}">{{ $s->code }} - {{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-semibold">Gudang</label>
                    <select id="filter-wh" class="form-select form-select-sm">
                        <option value="">Semua Gudang</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}">{{ $w->code }} - {{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-1">
                    <label class="form-label small fw-semibold">Status</label>
                    <select id="filter-status" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        <option value="posted">Posted</option>
                        <option value="draft">Draft</option>
                    </select>
                </div>
                <div class="col-12 d-flex gap-2 mt-2">
                    <button type="button" id="btn-apply-filter" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-funnel me-1"></i> Filter
                    </button>
                    <button type="button" id="btn-reset-filter" class="btn btn-outline-secondary btn-sm px-3">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="table-receipt" class="table table-hover table-striped align-middle w-100 mb-0" style="font-size:.88rem">
                    <thead class="table-dark text-center">
                        <tr>
                            <th>No. Penerimaan</th>
                            <th>Tanggal</th>
                            <th>No. PO</th>
                            <th>Supplier</th>
                            <th>Gudang</th>
                            <th>Unit Bisnis</th>
                            <th>Total Item</th>
                            <th>Status</th>
                            <th style="width:150px">Aksi</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
        <div class="card-footer small text-secondary">Penerimaan berasal dari proses pembelian dan menjadi dasar pergerakan stok.</div>
    </div>
</div>

<div class="modal fade" id="modal-detail-receipt" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bi bi-box-arrow-in-down me-2"></i>Detail Penerimaan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="receipt-detail-body">
                <div class="text-center py-5 text-secondary"><div class="spinner-border spinner-border-sm me-2"></div> Memuat...</div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    const table = $('#table-receipt').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ route('inventori.penerimaan.data') }}",
            data: function (d) {
                d.start_date = $('#filter-start-date').val();
                d.end_date = $('#filter-end-date').val();
                d.business_unit_id = $('#filter-bu').val();
                d.supplier_id = $('#filter-supplier').val();
                d.warehouse_id = $('#filter-wh').val();
                d.status = $('#filter-status').val();
            }
        },
        order: [[1, 'desc']],
        pageLength: 25,
        columns: [
            { data: 'receipt_no', className: 'fw-semibold' },
            { data: 'formatted_date', className: 'text-center' },
            { data: 'po_no', defaultContent: '-' },
            { data: 'supplier_name', defaultContent: '-' },
            { data: 'warehouse_name', defaultContent: '-' },
            { data: 'business_unit_name', defaultContent: '-' },
            { data: 'total_items', className: 'text-center' },
            {
                data: 'status',
                className: 'text-center',
                render: function (data) {
                    const cls = data === 'posted' ? 'text-bg-success' : 'text-bg-secondary';
                    return '<span class="badge ' + cls + '">' + String(data || '-').toUpperCase() + '</span>';
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function (row) {
                    return '<div class="btn-group btn-group-sm" role="group">' +
                        '<button type="button" class="btn btn-outline-primary btn-detail-receipt" data-id="' + row.id + '"><i class="bi bi-eye me-1"></i>Detil</button>' +
                        '<a target="_blank" href="' + "{{ url('/inventori/penerimaan') }}" + '/' + row.id + '/print" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</a>' +
                        '</div>';
                }
            }
        ],
        language: {
            processing: '<div class="spinner-border spinner-border-sm text-primary"></div>',
            emptyTable: 'Belum ada data penerimaan.',
            zeroRecords: 'Data penerimaan tidak ditemukan.',
            search: 'Cari:',
            lengthMenu: 'Tampilkan _MENU_',
            info: 'Menampilkan _START_ - _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            paginate: { previous: '‹', next: '›' }
        }
    });

    $('#btn-apply-filter').on('click', function () { table.ajax.reload(); });

    $('#btn-reset-filter').on('click', function () {
        $('#form-filter')[0].reset();
        $('#filter-start-date').val('{{ $startDate }}');
        $('#filter-end-date').val('{{ $endDate }}');
        table.ajax.reload();
    });

    $(document).on('click', '.btn-detail-receipt', function () {
        const id = $(this).data('id');
        $('#receipt-detail-body').html('<div class="text-center py-5 text-secondary"><div class="spinner-border spinner-border-sm me-2"></div> Memuat...</div>');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-detail-receipt')).show();

        $.get("{{ url('/inventori/penerimaan') }}/" + id, function (res) {
            if (!res.success) throw new Error(res.message || 'Gagal memuat detail');
            const r = res.receipt;
            let rows = '';
            res.items.forEach(function (item, i) {
                rows += '<tr>' +
                    '<td class="text-center">' + (i + 1) + '</td>' +
                    '<td>' + item.product_code + ' - ' + item.product_name + '</td>' +
                    '<td class="text-end">' + item.qty + '</td>' +
                    '<td>' + (item.unit_name || '-') + '</td>' +
                    '<td class="text-end">Rp ' + Number(item.base_unit_cost || 0).toLocaleString('id-ID') + '</td>' +
                    '<td class="text-end">Rp ' + Number(item.line_value || 0).toLocaleString('id-ID') + '</td>' +
                    '</tr>';
            });
            $('#receipt-detail-body').html(
                '<div class="row g-3 mb-3">' +
                    '<div class="col-md-3"><div class="small text-secondary">No. Penerimaan</div><div class="fw-bold">' + r.receipt_no + '</div></div>' +
                    '<div class="col-md-3"><div class="small text-secondary">Tanggal</div><div class="fw-semibold">' + r.formatted_date + '</div></div>' +
                    '<div class="col-md-3"><div class="small text-secondary">No. PO</div><div class="fw-semibold">' + (r.po_no || '-') + '</div></div>' +
                    '<div class="col-md-3"><div class="small text-secondary">Status</div><span class="badge text-bg-success">' + String(r.status || '-').toUpperCase() + '</span></div>' +
                '</div>' +
                '<div class="row g-3 mb-3">' +
                    '<div class="col-md-4"><div class="small text-secondary">Supplier</div><div class="fw-semibold">' + (r.supplier_name || '-') + '</div></div>' +
                    '<div class="col-md-4"><div class="small text-secondary">Gudang</div><div class="fw-semibold">' + (r.warehouse_name || '-') + '</div></div>' +
                    '<div class="col-md-4"><div class="small text-secondary">Unit Bisnis</div><div class="fw-semibold">' + (r.business_unit_name || '-') + '</div></div>' +
                '</div>' +
                '<div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-0">' +
                    '<thead class="table-light"><tr><th class="text-center">#</th><th>Barang</th><th class="text-end">Qty</th><th>Satuan</th><th class="text-end">HPP</th><th class="text-end">Nilai</th></tr></thead>' +
                    '<tbody>' + rows + '</tbody>' +
                '</table></div>'
            );
        }).fail(function (xhr) {
            $('#receipt-detail-body').html('<div class="alert alert-danger mb-0">' + (xhr.responseJSON?.message || 'Gagal memuat detail penerimaan.') + '</div>');
        });
    });
});
</script>
@endpush