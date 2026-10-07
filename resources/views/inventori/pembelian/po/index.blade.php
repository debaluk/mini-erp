@extends('layouts.app')

@section('content')
<div class="container-fluid px-0 py-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold text-dark"><i class="bi bi-receipt me-2 text-primary"></i>Purchase Order</h3>
            <div class="text-secondary small">Pengelolaan Pesanan Pembelian Barang Ke Supplier</div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm px-3 fw-semibold shadow-sm" id="btn-add-po">
                <i class="bi bi-plus-circle me-1"></i> + Buat PO Baru
            </button>
            <a href="{{ route('inventori.pembelian.po.export-excel') }}" id="btn-export-po" class="btn btn-success btn-sm px-3 fw-semibold shadow-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-2">
        <div class="card-body p-3">
            <form id="form-filter" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label mb-1 small fw-bold">Mulai Tanggal</label>
                    <input type="date" id="filter-start-date" class="form-control form-control-sm" value="{{ $startDate }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1 small fw-bold">Sampai Tanggal</label>
                    <input type="date" id="filter-end-date" class="form-control form-control-sm" value="{{ $endDate }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1 small fw-bold">Unit Bisnis</label>
                    <select id="filter-bu" class="form-select form-select-sm">
                        <option value="">Semua Unit Bisnis</option>
                        @foreach($businessUnits as $bu)
                            <option value="{{ $bu->id }}">{{ $bu->code }} - {{ $bu->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1 small fw-bold">Supplier / Vendor</label>
                    <select id="filter-supplier" class="form-select form-select-sm">
                        <option value="">Semua Supplier</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1 small fw-bold">Gudang Tujuan</label>
                    <select id="filter-wh" class="form-select form-select-sm">
                        <option value="">Semua Gudang</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}">{{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="button" id="btn-apply-filter" class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i>Tampilkan</button>
                    <button type="button" id="btn-reset-filter" class="btn btn-outline-secondary btn-sm">Reset</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="table-po" class="table table-hover align-middle mb-0 w-100" style="font-size:.88rem">
                    <thead class="bg-light text-muted extra-small text-uppercase">
                        <tr>
                            <th class="ps-3" style="width: 90px;">Tanggal</th>
                            <th style="width: 140px;">No. PO</th>
                            <th>Supplier</th>
                            <th style="width: 130px;">Gudang</th>
                            <th style="width: 120px;">Unit Bisnis</th>
                            <th style="width: 120px;">Total PO</th>
                            <th style="width: 90px;">Status</th>
                            <th class="text-center pe-3" style="width: 180px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- MODAL INPUT / EDIT PO --}}
<div class="modal fade" id="modal-po" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2 px-3">
                <h6 class="modal-title fw-bold" id="modal-po-title">
                    <i class="bi bi-cart-plus me-2"></i>Buat Purchase Order Baru
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="form-po">
                @csrf
                <input type="hidden" id="po-id" name="po_id">

                <div class="modal-body p-3">
                    {{-- HEADER PO --}}
                    <div class="row g-2 mb-2 bg-light p-2 rounded border">
                        <div class="col-md-3">
                            <label class="form-label extra-small fw-bold text-muted mb-1">Tanggal PO *</label>
                            <x-date-input-id name="po_date" id="po-date" :value="date('Y-m-d')" required />
                        </div>
                        <div class="col-md-3">
                            <label class="form-label extra-small fw-bold text-muted mb-1">Unit Bisnis *</label>
                            <select id="business-unit-id" name="business_unit_id" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Unit Bisnis --</option>
                                @foreach($businessUnits as $bu)
                                    <option value="{{ $bu->id }}">{{ $bu->code }} - {{ $bu->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label extra-small fw-bold text-muted mb-1">Gudang Tujuan *</label>
                            <select id="warehouse-id" name="warehouse_id" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Gudang --</option>
                                @foreach($warehouses as $w)
                                    <option value="{{ $w->id }}">{{ $w->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label extra-small fw-bold text-muted mb-1">Supplier / Vendor *</label>
                            <select id="supplier-id" name="supplier_id" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Supplier --</option>
                                @foreach($suppliers as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- INFO VENDOR --}}
                    <div class="row g-2 mb-3">
                        <div class="col-12">
                            <div class="border rounded bg-light px-3 py-2" id="box-supplier-info">
                                <div class="fw-bold small text-dark" id="info-supplier-name">Pilih vendor untuk melihat detail info...</div>
                                <div class="small text-muted" id="info-supplier-address">-</div>
                                <div class="small text-muted" id="info-supplier-phone">-</div>
                            </div>
                        </div>
                    </div>

                    {{-- ITEM --}}
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold extra-small text-uppercase text-secondary">
                                <i class="bi bi-box-seam me-1"></i>Rincian Item Barang Yang Dipesan
                            </span>
                            <button type="button" class="btn btn-primary btn-sm fw-semibold" id="btn-add-item-row">
                                <i class="bi bi-plus-lg me-1"></i>Tambah Item
                            </button>
                        </div>

                        <div class="table-responsive border rounded">
                            <table class="table table-sm table-bordered align-middle mb-0" id="table-po-items">
                                <thead class="bg-light text-muted extra-small">
                                    <tr>
                                        <th>Produk / Barang</th>
                                        <th style="width: 90px;" class="text-center">Satuan</th>
                                        <th style="width: 100px;" class="text-center">Qty Order</th>
                                        <th style="width: 130px;" class="text-end">Harga Satuan</th>
                                        <th style="width: 110px;" class="text-end">Diskon</th>
                                        <th style="width: 140px;" class="text-end">Subtotal (Rp)</th>
                                        <th style="width: 45px;" class="text-center">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-po-items"></tbody>
                                <tfoot class="bg-light fw-bold">
                                    <tr>
                                        <td colspan="5" class="text-end small">TOTAL PEMBELIAN:</td>
                                        <td class="text-end font-monospace text-primary" id="footer-grand-total">Rp 0</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    {{-- CATATAN --}}
                    <div>
                        <label class="form-label extra-small fw-bold text-muted mb-1">Catatan / Memo PO</label>
                        <input type="text" id="po-memo" name="memo" class="form-control form-control-sm" placeholder="Catatan khusus ke supplier (opsional)">
                    </div>
                </div>

                <div class="modal-footer py-2 px-3 bg-light">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold px-3" id="btn-save-po">
                        <i class="bi bi-check-circle me-1"></i>Simpan Purchase Order
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL VIEW DETAIL PO --}}
<div class="modal fade" id="modal-detail-po" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2 px-3">
                <h6 class="modal-title fw-bold"><i class="bi bi-file-earmark-text me-2"></i>Detail Purchase Order</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3" id="detail-po-body"></div>
            <div class="modal-footer py-2 px-3 bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = $('#table-po').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ route('inventori.pembelian.po.data') }}",
            data: function (d) {
                d.start_date = $('#filter-start-date').val();
                d.end_date = $('#filter-end-date').val();
                d.business_unit_id = $('#filter-bu').val();
                d.supplier_id = $('#filter-supplier').val();
                d.warehouse_id = $('#filter-wh').val();
            }
        },
        columns: [
            { data: 'formatted_date', className: 'text-center' },
            { data: 'po_no', className: 'text-center font-monospace fw-bold text-primary' },
            { data: 'supplier_name', className: 'fw-semibold text-dark' },
            { data: 'warehouse_name' },
            { data: 'business_unit_name', className: 'text-center' },
            { data: 'formatted_total', className: 'text-end font-monospace fw-bold' },
            {
                data: 'status',
                className: 'text-center',
                render: function (data) {
                    if (data === 'draft') return '<span class="badge bg-secondary">DRAFT</span>';
                    if (data === 'approved') return '<span class="badge bg-primary">APPROVED</span>';
                    if (data === 'partial') return '<span class="badge bg-warning text-dark">PARTIAL</span>';
                    if (data === 'completed') return '<span class="badge bg-success">COMPLETED</span>';
                    if (data === 'closed') return '<span class="badge bg-dark">CLOSED</span>';
                    if (data === 'canceled') return '<span class="badge bg-danger">CANCELED</span>';
                    return `<span class="badge bg-danger">${data.toUpperCase()}</span>`;
                }
            },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render: function (data, type, row) {
                    let actions = `<div class="btn-group btn-group-sm">`;
                    if (row.status === 'canceled') {
                        actions += `<button type="button" class="btn btn-outline-info btn-view-po" data-id="${row.id}" title="Detail PO"><i class="bi bi-eye"></i></button>`;
                        actions += '</div>';
                        return actions;
                    }
                    if (row.status !== 'draft') {
                        actions += `<button type="button" class="btn btn-outline-info btn-view-po" data-id="${row.id}" title="Detail PO"><i class="bi bi-eye"></i></button>`;
                    }
                    actions += `<a href="{{ url('/inventori/pembelian/po') }}/${row.id}/print" target="_blank" class="btn btn-outline-secondary" title="Cetak Nota PO"><i class="bi bi-printer"></i></a>`;
                    if (row.status === 'draft') {
                        actions += `<button type="button" class="btn btn-outline-success btn-approve-po" data-id="${row.id}" data-no="${row.po_no}" title="Approve PO"><i class="bi bi-check-circle"></i></button>`;
                        actions += `<button type="button" class="btn btn-outline-warning btn-edit-po" data-id="${row.id}" title="Edit Draft"><i class="bi bi-pencil"></i></button>`;
                        actions += `<button type="button" class="btn btn-outline-danger btn-delete-po" data-id="${row.id}" data-no="${row.po_no}" title="Batalkan PO"><i class="bi bi-trash"></i></button>`;
                    }
                    if (!['completed', 'closed', 'draft'].includes(row.status)) {
                        actions += `<button type="button" class="btn btn-outline-dark btn-close-po" data-id="${row.id}" data-no="${row.po_no}" title="Tutup PO"><i class="bi bi-x-circle"></i> Close</button>`;
                    }
                    actions += '</div>';
                    return actions;
                }
            }
        ]
    });

    $('#btn-apply-filter').on('click', function () { table.ajax.reload(); });
    $('#btn-reset-filter').on('click', function () {
        $('#form-filter')[0].reset();
        table.ajax.reload();
    });

    $('#btn-export-po').on('click', function (e) {
        e.preventDefault();
        const params = new URLSearchParams({
            start_date: $('#filter-start-date').val() || '',
            end_date: $('#filter-end-date').val() || '',
            business_unit_id: $('#filter-bu').val() || '',
            supplier_id: $('#filter-supplier').val() || '',
            warehouse_id: $('#filter-wh').val() || ''
        });
        window.location.href = `{{ route('inventori.pembelian.po.export-excel') }}?${params.toString()}`;
    });

    $('#supplier-id').on('change', function () {
        const suppId = $(this).val();
        if (suppId) {
            $.get(`{{ url('/inventori/pembelian/po/supplier-info') }}/${suppId}`, function (res) {
                if (res.success) {
                    $('#info-supplier-name').text(res.data.name);
                    $('#info-supplier-address').text('Alamat: ' + res.data.address);
                    $('#info-supplier-phone').text('Telp: ' + res.data.phone);
                }
            });
        } else {
            $('#info-supplier-name').text('Pilih vendor untuk melihat detail info...');
            $('#info-supplier-address').text('-');
            $('#info-supplier-phone').text('-');
        }
    });

    // Mapping Gudang sudah mengikuti Unit Bisnis (1 BU : 1 Gudang).
    $('#business-unit-id').on('change', function () {
        const businessUnitId = $(this).val();
        const warehouseSelect = $('#warehouse-id');

        warehouseSelect.val('');

        if (businessUnitId) {
            warehouseSelect.val(businessUnitId);
        }
    });

    $('#btn-add-item-row').on('click', function () { addItemRow(); });

    async function addItemRow(prodId = '', qty = 1, price = 0, discount = 0) {
        let productOptions = '<option value="">-- Pilih Barang --</option>';
        @foreach($products as $p)
            productOptions += `<option value="{{ $p->id }}" data-unit="{{ $p->unit_code ?? '' }}" ${String({{ $p->id }}) === String(prodId) ? 'selected' : ''}>{{ $p->code }} - {{ $p->name }}</option>`;
        @endforeach

        const rowId = Date.now();
        const html = `
            <tr id="row-${rowId}">
                <td><select name="products[]" class="form-select form-select-sm select-product" required>${productOptions}</select></td>
                <td><input type="text" class="form-control form-control-sm text-center input-unit" value="" readonly></td>
                <td>
                    <input type="text" class="form-control form-control-sm text-center input-qty-display" inputmode="decimal" autocomplete="off" required>
                    <input type="hidden" name="qty[]" class="input-qty">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm text-end input-price-display" inputmode="numeric" autocomplete="off" required>
                    <input type="hidden" name="unit_price[]" class="input-price">
                </td>
                <td><input type="number" name="discount[]" class="form-control form-control-sm text-end input-discount" step="0.01" value="${discount}"></td>
                <td class="text-end font-monospace fw-bold cell-subtotal">Rp 0</td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row" title="Hapus item"><i class="bi bi-x"></i></button></td>
            </tr>`;
        $('#tbody-po-items').append(html);
        const row = $('#row-' + rowId);
        row.find('.input-unit').val(row.find('.select-product option:selected').data('unit') || '');
        row.find('.input-qty').val(qty);
        row.find('.input-price').val(price);
        row.find('.input-qty-display').val(formatQty(qty));
        row.find('.input-price-display').val(formatPrice(price));
        calcSubtotal(row);
    }

    $(document).on('click', '.btn-remove-row', function () {
        $(this).closest('tr').remove();
        calcGrandTotal();
    });

    $(document).on('change', '.select-product', function () {
        const row = $(this).closest('tr');
        row.find('.input-unit').val($(this).find(':selected').data('unit') || '');
        calcSubtotal(row);
    });

    function formatQty(value) {
        const number = Number(value) || 0;
        return new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2
        }).format(number);
    }

    function formatPrice(value) {
        const number = Number(value) || 0;
        return new Intl.NumberFormat('id-ID', {
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(number);
    }

    function setFormattedNumber(input, hidden, formatter) {
        const raw = String(input.val() || '').replace(/[^0-9,.-]/g, '').replace(',', '.');
        const number = parseFloat(raw) || 0;
        hidden.val(number);
        input.val(formatter(number));
    }

    $(document).on('input', '.input-qty-display', function () {
        const row = $(this).closest('tr');
        const raw = $(this).val().replace(/[^0-9,.-]/g, '').replace(',', '.');
        row.find('.input-qty').val(parseFloat(raw) || 0);
        calcSubtotal(row);
    });

    $(document).on('blur', '.input-qty-display', function () {
        const row = $(this).closest('tr');
        $(this).val(formatQty(row.find('.input-qty').val()));
    });

    $(document).on('input', '.input-price-display', function () {
        const row = $(this).closest('tr');
        const raw = $(this).val().replace(/[^0-9]/g, '');
        row.find('.input-price').val(parseFloat(raw) || 0);
        calcSubtotal(row);
    });

    $(document).on('blur', '.input-price-display', function () {
        const row = $(this).closest('tr');
        $(this).val(formatPrice(row.find('.input-price').val()));
    });

    $(document).on('input', '.input-discount', function () {
        calcSubtotal($(this).closest('tr'));
    });

    function calcSubtotal(row) {
        const qty = parseFloat(row.find('.input-qty').val()) || 0;
        const price = parseFloat(row.find('.input-price').val()) || 0;
        const disc = parseFloat(row.find('.input-discount').val()) || 0;
        row.find('.cell-subtotal').text('Rp ' + ((qty * price) - disc).toLocaleString('id-ID'));
        calcGrandTotal();
    }

    function calcGrandTotal() {
        let grand = 0;
        $('#tbody-po-items tr').each(function () {
            grand += (parseFloat($(this).find('.input-qty').val()) || 0) * (parseFloat($(this).find('.input-price').val()) || 0) - (parseFloat($(this).find('.input-discount').val()) || 0);
        });
        $('#footer-grand-total').text('Rp ' + grand.toLocaleString('id-ID'));
    }

    $('#btn-add-po').on('click', function () {
        $('#form-po')[0].reset();
        $('#po-id').val('');
        $('#po-no').val('{{ $autoPoNo }}');
        $('#tbody-po-items').empty();
        addItemRow();
        $('#modal-po-title').html('<i class="bi bi-cart-plus me-2"></i>Buat Purchase Order Baru');
        $('#modal-po').modal('show');
    });

    $('#form-po').on('submit', function (e) {
        e.preventDefault();
        const poId = $('#po-id').val();
        const url = poId ? `{{ url('/inventori/pembelian/po') }}/${poId}` : `{{ route('inventori.pembelian.po.store') }}`;
        const type = poId ? 'PUT' : 'POST';
        $.ajax({
            url: url,
            type: type,
            data: $(this).serialize(),
            success: function (res) {
                if (res.success) {
                    $('#modal-po').modal('hide');
                    table.ajax.reload();
                    Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.message, timer: 2000, showConfirmButton: false });
                }
            },
            error: function (xhr) {
                Swal.fire({ icon: 'error', title: 'Gagal!', text: xhr.responseJSON?.message || 'Terjadi kesalahan sistem' });
            }
        });
    });

    $(document).on('click', '.btn-approve-po', function () {
        const id = $(this).data('id');
        const no = $(this).data('no');
        Swal.fire({
            title: 'Approve Purchase Order?',
            text: `PO [${no}] akan di-approve untuk proses pembelian selanjutnya.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Approve',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `{{ url('/inventori/pembelian/po') }}/${id}/approve`,
                    type: 'POST',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function (res) {
                        if (res.success) {
                            table.ajax.reload(null, false);
                            Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.message, timer: 1800, showConfirmButton: false });
                        }
                    },
                    error: function (xhr) {
                        Swal.fire({ icon: 'error', title: 'Gagal!', text: xhr.responseJSON?.message || 'PO gagal di-approve' });
                    }
                });
            }
        });
    });

    $(document).on('click', '.btn-edit-po', function () {
        const id = $(this).data('id');

        $.get(`{{ url('/inventori/pembelian/po') }}/${id}/edit-data`, function (res) {
            if (!res.success) {
                Swal.fire({ icon: 'error', title: 'Gagal!', text: res.message || 'Data PO tidak dapat dimuat.' });
                return;
            }

            $('#form-po')[0].reset();
            $('#po-id').val(res.po.id);
            const poDate = String(res.po.po_date || '').split(' ')[0];
            $('#po-date').val(poDate);
            if (poDate) {
                const parts = poDate.split('-');
                $('#po-date_display').val(parts.length === 3 ? parts[2] + '/' + parts[1] + '/' + parts[0] : '');
            } else {
                $('#po-date_display').val('');
            }
            $('#business-unit-id').val(res.po.business_unit_id).trigger('change');
            $('#warehouse-id').val(res.po.warehouse_id);
            $('#supplier-id').val(res.po.supplier_id).trigger('change');
            $('#po-memo').val(res.po.memo || '');
            $('#tbody-po-items').empty();

            if (!res.items || !res.items.length) {
                addItemRow();
            } else {
                res.items.forEach(function (item) {
                    addItemRow(item.product_id, item.qty, item.unit_price, item.discount);
                });
            }

            $('#modal-po-title').html('<i class="bi bi-pencil-square me-2"></i>Edit Purchase Order');
            $('#modal-po').modal('show');
        }).fail(function (xhr) {
            Swal.fire({ icon: 'error', title: 'Gagal!', text: xhr.responseJSON?.message || 'Data PO tidak dapat dimuat.' });
        });
    });

    $(document).on('click', '.btn-delete-po', function () {
        const id = $(this).data('id');
        const no = $(this).data('no');
        Swal.fire({
            title: 'Batalkan Purchase Order?',
            text: `PO [${no}] akan dibatalkan dan statusnya menjadi CANCELED.`,
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Ya, Batalkan!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `{{ url('/inventori/pembelian/po') }}/${id}`,
                    type: 'DELETE',
                    data: { _token: '{{ csrf_token() }}' },
                    success: function (res) {
                        if (res.success) {
                            table.ajax.reload();
                            Swal.fire({ icon: 'success', title: 'Dibatalkan!', text: res.message, timer: 2000, showConfirmButton: false });
                        }
                    }
                });
            }
        });
    });

    $(document).on('click', '.btn-view-po', function () {
        const id = $(this).data('id');
        $.get(`{{ url('/inventori/pembelian/po') }}/${id}`, function (res) {
            if (res.success) {
                let html = `
                    <div class="row g-2 mb-3 bg-light p-2 rounded border">
                        <div class="col-6">
                            <div class="extra-small text-muted">No. PO</div><div class="fw-bold font-monospace text-primary">${res.po.po_no}</div>
                            <div class="extra-small text-muted mt-2">Supplier</div><div class="fw-bold">${res.po.supplier_name}</div>
                            <div class="extra-small text-muted">${res.po.supplier_address || '-'}</div>
                        </div>
                        <div class="col-6">
                            <div class="extra-small text-muted">Gudang Tujuan</div><div class="fw-bold">${res.po.warehouse_name}</div>
                            <div class="extra-small text-muted mt-2">Unit Bisnis</div><div class="fw-bold">${res.po.business_unit_name || '-'}</div>
                            <div class="extra-small text-muted mt-2">Status</div><div><span class="badge bg-primary">${res.po.status.toUpperCase()}</span></div>
                        </div>
                    </div>
                    <div class="table-responsive border rounded">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="bg-light text-muted extra-small text-uppercase text-center">
                                <tr><th>Kode</th><th>Barang</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr>
                            </thead>
                            <tbody>`;
                let total = 0;
                res.items.forEach(i => {
                    total += parseFloat(i.total);
                    html += `<tr>
                        <td class="font-monospace text-center">${i.product_code}</td>
                        <td>${i.product_name}</td>
                        <td class="text-center">${i.qty} ${i.unit_name || ''}</td>
                        <td class="text-end font-monospace">Rp ${parseFloat(i.unit_price).toLocaleString('id-ID')}</td>
                        <td class="text-end font-monospace fw-bold">Rp ${parseFloat(i.total).toLocaleString('id-ID')}</td>
                    </tr>`;
                });
                html += `</tbody><tfoot class="bg-light fw-bold"><tr><td colspan="4" class="text-end">TOTAL:</td><td class="text-end font-monospace">Rp ${total.toLocaleString('id-ID')}</td></tr></tfoot></table></div>`;
                $('#detail-po-body').html(html);
                $('#modal-detail-po').modal('show');
            }
        });
    });
});
</script>
@endsection