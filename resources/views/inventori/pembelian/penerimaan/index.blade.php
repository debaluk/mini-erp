@extends('layouts.app')
@section('content')
<div class="container-fluid px-0 py-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold text-dark"><i class="bi bi-receipt me-2 text-primary"></i>Penerimaan Barang</h3>
            <div class="text-secondary small">Daftar Penerimaan Barang</div>
        </div>
        <a href="{{ route('inventori.penerimaan.export', request()->all()) }}" class="btn btn-success btn-sm px-3 fw-semibold shadow-sm">
            <i class="bi bi-file-earmark-excel me-1"></i> Export
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
                        <option value="posted">Terposting</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="button" id="btn-apply-filter" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-funnel me-1"></i> Tampilkan
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

<div class="modal fade" id="modal-cancel-receipt" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning-subtle py-2 px-3">
                <h6 class="modal-title fw-bold text-dark"><i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i>Perhatian !!!</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-3">
                <div class="fw-semibold">Proses pembatalan akan membatalkan penerimaan barang.</div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger btn-sm" id="btn-confirm-cancel-receipt">Ya, Batalkan</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-detail-receipt" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2 px-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-box-arrow-in-down me-2"></i>Detail Penerimaan</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3" id="receipt-detail-body">
                <div class="text-center py-5 text-secondary"><div class="spinner-border spinner-border-sm me-2"></div> Memuat...</div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modal-return-receipt" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2 px-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-arrow-return-left me-2"></i>Retur Pembelian dari Penerimaan</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-return-receipt">
                <div class="modal-body p-3" id="return-receipt-body">
                    <div class="text-center py-4 text-secondary"><div class="spinner-border spinner-border-sm me-2"></div>Memuat data penerimaan...</div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btn-save-return-receipt"><i class="bi bi-save me-1"></i>Simpan Draft Retur</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(function () {
    function showNotice(title, message, onClose, type = 'info') {
        const modalElement = document.getElementById('erpMessageModal');
        if (!modalElement || typeof bootstrap === 'undefined') {
            window.alert(title + (message ? '\n\n' + message : ''));
            if (typeof onClose === 'function') onClose();
            return;
        }

        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        const header = modalElement.querySelector('.modal-header');
        const titleElement = document.getElementById('erpMessageTitle');
        const bodyElement = document.getElementById('erpMessageBody');
        const okButton = modalElement.querySelector('.modal-footer button');
        header.classList.remove('bg-primary', 'bg-danger', 'bg-warning', 'text-white');
        if (type === 'success') header.classList.add('bg-primary', 'text-white');
        else if (type === 'danger') header.classList.add('bg-danger', 'text-white');
        else if (type === 'warning') header.classList.add('bg-warning');
        titleElement.textContent = title;
        bodyElement.textContent = message || '';
        okButton.className = 'btn btn-primary btn-sm';
        okButton.textContent = 'OK';

        if (typeof onClose === 'function') {
            $(modalElement).one('hidden.bs.modal', function () {
                if (modalElement.dataset.redirectAfterNotice === '1') {
                    delete modalElement.dataset.redirectAfterNotice;
                    onClose();
                }
            });
            modalElement.dataset.redirectAfterNotice = '1';
        }
        modal.show();
    }

    const table = $('#table-receipt').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ route('inventori.penerimaan.data') }}",
            data: function (d) {
                d.start_date = $('#filter-start-date').val();
                d.end_date = $('#filter-end-date').val();
                d.business_unit_id = $('#filter-bu').val();
                d.warehouse_id = $('#filter-wh').val();
                d.status = $('#filter-status').val();
            }
        },
        order: [[1, 'desc']],
        pageLength: 15,
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
                    const cls = data === 'posted' ? 'text-bg-success' : (data === 'cancelled' ? 'text-bg-danger' : 'text-bg-secondary');
                    const label = data === 'posted' ? 'TERPOSTING' : (data === 'cancelled' ? 'DIBATALKAN' : String(data || '-').toUpperCase());
                    return '<span class="badge ' + cls + '">' + label + '</span>';
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function (row) {
                    return '<div class="btn-group btn-group-sm" role="group">' +
                        '<button type="button" class="btn btn-outline-primary btn-detail-receipt" title="Detil" data-id="' + row.id + '"><i class="bi bi-eye"></i></button>' +
                        '<a target="_blank" href="' + "{{ url('/inventori/penerimaan') }}" + '/' + row.id + '/print" class="btn btn-outline-secondary" title="Print"><i class="bi bi-printer"></i></a>' +
                        (row.status === 'posted' ? '<button type="button" class="btn btn-outline-warning btn-return-receipt" title="Retur dari Penerimaan Ini" data-id="' + row.id + '" data-receipt="' + row.receipt_no + '"><i class="bi bi-arrow-return-left"></i></button>' : '') +
                        (row.status === 'posted' && row.purchase_order_id !== null && row.purchase_order_id !== undefined ? '<button type="button" class="btn btn-outline-danger btn-cancel-receipt" title="Batal Penerimaan" data-id="' + row.id + '" data-bs-toggle="modal" data-bs-target="#modal-cancel-receipt"><i class="bi bi-x-circle"></i></button>' : '') +
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

    let cancelReceiptId = null;

    $('#modal-cancel-receipt').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        cancelReceiptId = button.data('id') || null;
    });

    $('#modal-cancel-receipt').on('hidden.bs.modal', function () {
        cancelReceiptId = null;
        $('#btn-confirm-cancel-receipt').prop('disabled', false);
    });

    $('#btn-confirm-cancel-receipt').on('click', function () {
        if (!cancelReceiptId) return;

        const receiptId = cancelReceiptId;
        const modalElement = document.getElementById('modal-cancel-receipt');
        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        const confirmButton = $(this);

        confirmButton.prop('disabled', true);

        $.ajax({
            url: "{{ route('inventori.penerimaan.cancel', ['id' => '__ID__']) }}".replace('__ID__', receiptId),
            method: "POST",
            headers: {
                'X-CSRF-TOKEN': "{{ csrf_token() }}",
                'Accept': 'application/json'
            },
            success: function (response) {
                $(modalElement).one('hidden.bs.modal', function () {
                    table.ajax.reload(null, false);

                    showNotice('Berhasil', response.message || 'Proses batal berhasil.');
                });

                modal.hide();
            },
            error: function (xhr) {
                confirmButton.prop('disabled', false);

                const message = xhr.responseJSON?.message || 'Proses batal gagal.';
                showNotice('Gagal', message);
            }
        });
    });


    let returnReceiptId = null;
    const returnModalElement = document.getElementById('modal-return-receipt');
    const returnModal = bootstrap.Modal.getOrCreateInstance(returnModalElement);

    $(document).on('click', '.btn-return-receipt', function () {
        returnReceiptId = $(this).data('id');
        $('#return-receipt-body').html('<div class="text-center py-4 text-secondary"><div class="spinner-border spinner-border-sm me-2"></div>Memuat data penerimaan...</div>');
        $('#btn-save-return-receipt').prop('disabled', true);
        returnModal.show();

        $.ajax({
            url: "{{ url('/inventori/pembelian/retur/from-receipt') }}/" + returnReceiptId,
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
            success: function (html) {
                $('#return-receipt-body').html(html);
                const hasReturnableItems = $('#return-receipt-body input[name$="[qty]"]').length > 0;
                $('#btn-save-return-receipt').prop('disabled', !hasReturnableItems);
            },
            error: function (xhr) {
                returnModal.hide();
                showNotice('Retur Tidak Dapat Dimuat', xhr.responseJSON?.message || 'Data retur tidak dapat dimuat.');
            }
        });
    });

    $('#modal-return-receipt').on('hidden.bs.modal', function () {
        returnReceiptId = null;
        $('#form-return-receipt')[0].reset();
        $('#return-receipt-body').empty();
        $('#btn-save-return-receipt').prop('disabled', false);
    });

    $(document).on('input', '#return-receipt-body input[type="number"]', function () {
        const max = Number($(this).attr('max') || 0);
        const value = Number($(this).val() || 0);
        if (value > max) $(this).val(max);
        if (value < 0) $(this).val('');
    });

    $('#form-return-receipt').on('submit', function (event) {
        event.preventDefault();
        if (!returnReceiptId) return;

        const form = $(this);
        const button = $('#btn-save-return-receipt');
        const originalText = button.html();
        button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Menyimpan...');

        $.ajax({
            url: "{{ url('/inventori/pembelian/retur/from-receipt') }}/" + returnReceiptId,
            method: 'POST',
            data: form.serialize(),
            headers: { 'X-CSRF-TOKEN': "{{ csrf_token() }}", 'Accept': 'application/json' },
            success: function (response) {
                returnModal.hide();
                showNotice(
                    'Retur Berhasil Disimpan',
                    'Draft retur berhasil dibuat. Belum ada perubahan stok maupun jurnal. Lanjutkan proses melalui daftar Retur Pembelian dan lakukan posting saat sudah siap.',
                    function () {
                        window.location.href = "{{ route('inventori.pembelian.retur') }}";
                    },
                    'success'
                );
            },
            error: function (xhr) {
                const response = xhr.responseJSON || {};
                let message = response.message || 'Draft retur tidak berhasil disimpan.';
                if (response.errors) {
                    const details = Object.values(response.errors).flat().filter(Boolean);
                    if (details.length) message = details.join(' ');
                } else if (xhr.status === 419) {
                    message = 'Sesi berakhir. Muat ulang halaman, lalu ulangi proses retur.';
                } else if (xhr.status === 422 && !response.message) {
                    message = 'Data retur tidak valid atau qty melebihi sisa yang dapat diretur.';
                } else if (xhr.status >= 500) {
                    message = 'Terjadi kesalahan pada server. Draft retur belum dapat disimpan.';
                }

                showNotice('Retur Gagal Diproses', message, null, 'danger');
            },
            complete: function () {
                button.prop('disabled', false).html(originalText);
            }
        });
    });

    $(document).on('click', '.btn-detail-receipt', function () {
        const id = $(this).data('id');
        $('#receipt-detail-body').html('<div class="text-center py-5 text-secondary"><div class="spinner-border spinner-border-sm me-2"></div> Memuat...</div>');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modal-detail-receipt')).show();

        $.get("{{ url('/inventori/penerimaan') }}/" + id, function (res) {
            if (!res.success) throw new Error(res.message || 'Gagal memuat detail');
            const r = res.receipt;
            const formatQty = function (value) {
                const number = Number(value || 0);
                return number.toLocaleString('id-ID', { maximumFractionDigits: 3, useGrouping: false });
            };
            const formatRupiah = function (value) {
                const number = Math.round(Number(value || 0));
                return 'Rp ' + number.toLocaleString('id-ID');
            };
            const statusClass = r.status === 'posted' ? 'text-bg-success' : (r.status === 'cancelled' ? 'text-bg-danger' : 'text-bg-secondary');
            const statusLabel = r.status === 'posted' ? 'TERPOSTING' : (r.status === 'cancelled' ? 'DIBATALKAN' : String(r.status || '-').toUpperCase());
            let rows = '';
            res.items.forEach(function (item, i) {
                rows += '<tr>' +
                    '<td class="text-center">' + (i + 1) + '</td>' +
                    '<td>' + item.product_code + ' - ' + item.product_name + '</td>' +
                    '<td class="text-end">' + formatQty(item.qty) + '</td>' +
                    '<td>' + (item.unit_name || '-') + '</td>' +
                    '<td class="text-end">' + formatRupiah(item.base_unit_cost) + '</td>' +
                    '<td class="text-end">' + formatRupiah(item.line_value) + '</td>' +
                    '</tr>';
            });
            $('#receipt-detail-body').html(
                '<div class="row g-3 mb-3">' +
                    '<div class="col-md-3"><div class="small text-secondary">No. Penerimaan</div><div class="fw-bold">' + r.receipt_no + '</div></div>' +
                    '<div class="col-md-3"><div class="small text-secondary">Tanggal</div><div class="fw-semibold">' + r.formatted_date + '</div></div>' +
                    '<div class="col-md-3"><div class="small text-secondary">No. PO</div><div class="fw-semibold">' + (r.po_no || '-') + '</div></div>' +
                    '<div class="col-md-3"><div class="small text-secondary">Status</div><span class="badge ' + statusClass + '">' + statusLabel + '</span></div>' +
                '</div>' +
                '<div class="row g-3 mb-3">' +
                    '<div class="col-md-4"><div class="small text-secondary">Supplier</div><div class="fw-semibold">' + (r.supplier_name || '-') + '</div></div>' +
                    '<div class="col-md-4"><div class="small text-secondary">Gudang</div><div class="fw-semibold">' + (r.warehouse_name || '-') + '</div></div>' +
                    '<div class="col-md-4"><div class="small text-secondary">Unit Bisnis</div><div class="fw-semibold">' + (r.business_unit_name || '-') + '</div></div>' +
                '</div>' +
                '<div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-0">' +
                    '<thead class="table-light"><tr><th class="text-center">#</th><th>Barang</th><th class="text-end">Qty</th><th>Satuan</th><th class="text-end">Harga Pokok Pembelian</th><th class="text-end">Nilai</th></tr></thead>' +
                    '<tbody>' + rows + '</tbody>' +
                '</table></div>'
            );
        }).fail(function (xhr) {
            showNotice('Gagal Memuat Detail', xhr.responseJSON?.message || 'Gagal memuat detail Penerimaan Barang.');
        });
    });
});
</script>
@endpush