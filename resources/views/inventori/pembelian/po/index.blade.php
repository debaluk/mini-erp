@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3"> 

    <!-- Header & Action Buttons -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold text-dark">Purchase Order (PO)</h3>
            <div class="text-secondary small">Pengelolaan Pesanan Pembelian Barang Ke Supplier</div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm px-3 fw-semibold shadow-sm" id="btn-add-po">
                <i class="bi bi-plus-circle me-1"></i> + Buat PO Baru
            </button>
            <a href="{{ route('inventori.pembelian.po.print-list', request()->all()) }}" target="_blank" class="btn btn-secondary btn-sm px-3 fw-semibold shadow-sm">
                <i class="bi bi-printer me-1"></i> Cetak List PO
            </a>
        </div>
    </div>

    <!-- Filter Card -->
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
                    <button type="button" id="btn-apply-filter" class="btn btn-primary btn-sm w-100"><i class="bi bi-search me-1"></i> Filter</button>
                    <button type="button" id="btn-reset-filter" class="btn btn-outline-secondary btn-sm">Reset</button>
                </div>
            </form>
        </div>
    </div>

    <!-- DataTables Table -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="table-po" class="table table-hover table-striped align-middle w-100" style="font-size: 0.88rem;">
                    <thead class="table-dark text-center">
                        <tr>
                            <th style="width: 85px;">Tanggal</th>
                            <th style="width: 140px;">No. PO</th>
                            <th>Supplier / Vendor</th>
                            <th style="width: 130px;">Gudang</th>
                            <th style="width: 120px;">Unit Bisnis</th>
                            <th style="width: 120px;">Total PO</th>
                            <th style="width: 100px;">Penerimaan</th>
                            <th style="width: 90px;">Status</th>
                            <th style="width: 210px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- MODAL INPUT/EDIT PO KEREN -->
<div class="modal fade" id="modal-po" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold" id="modal-po-title"><i class="bi bi-cart-plus me-2 text-primary"></i> Buat Purchase Order Baru</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-po">
                @csrf
                <input type="hidden" id="po-id" name="po_id">
                <div class="modal-body p-4">
                    
                    <!-- Form Header -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold">No. PO (Auto)</label>
                            <input type="text" id="po-no" class="form-control font-monospace fw-bold bg-light" value="{{ $autoPoNo }}" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Tanggal PO <span class="text-danger">*</span></label>
                            <input type="date" id="po-date" name="po_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Unit Bisnis <span class="text-danger">*</span></label>
                            <select id="business-unit-id" name="business_unit_id" class="form-select" required>
                                <option value="">-- Pilih Unit Bisnis --</option>
                                @foreach($businessUnits as $bu)
                                    <option value="{{ $bu->id }}">{{ $bu->code }} - {{ $bu->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Gudang Tujuan <span class="text-danger">*</span></label>
                            <select id="warehouse-id" name="warehouse_id" class="form-select" required>
                                <option value="">-- Pilih Gudang --</option>
                                @foreach($warehouses as $w)
                                    <option value="{{ $w->id }}">{{ $w->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Vendor Selection & Info Box Dynamic -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Pilih Vendor / Supplier <span class="text-danger">*</span></label>
                            <select id="supplier-id" name="supplier_id" class="form-select" required>
                                <option value="">-- Pilih Supplier --</option>
                                @foreach($suppliers as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light border-primary border-start border-4 shadow-sm p-2" id="box-supplier-info">
                                <div class="d-flex align-items-center">
                                    
                                    <div>
                                        <div class="fw-bold text-dark" id="info-supplier-name">Pilih vendor untuk melihat detail info...</div>
                                        <div class="small text-muted" id="info-supplier-address">-</div>
                                        <div class="small text-muted" id="info-supplier-phone">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div class="card border mb-3">
                        <div class="card-header bg-secondary text-white py-2 d-flex justify-content-between align-items-center">
                            <span class="fw-bold small"><i class="bi bi-box-seam me-1"></i> Rincian Item Barang Yang Dipesan</span>
                            <button type="button" class="btn btn-sm btn-light font-monospace fw-bold" id="btn-add-item-row">+ Tambah Item</button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle mb-0" id="table-po-items" style="font-size: 0.88rem;">
                                    <thead class="table-light text-center">
                                        <tr>
                                            <th>Produk / Barang</th>
                                    <th style="width: 100px;">Satuan</th>
                                            <th style="width: 110px;">Qty Order</th>
                                            <th style="width: 140px;">Harga Satuan</th>
                                            <th style="width: 120px;">Diskon</th>
                                            <th style="width: 150px;">Subtotal (Rp)</th>
                                            <th style="width: 50px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-po-items"></tbody>
                                    <tfoot class="table-light fw-bold">
                                        <tr>
                                            <td colspan="4" class="text-end">TOTAL PEMBELIAN:</td>
                                            <td colspan="2" class="text-end font-monospace fs-6 text-primary" id="footer-grand-total">Rp 0</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-bold">Catatan / Memo PO</label>
                        <input type="text" id="po-memo" name="memo" class="form-control" placeholder="Catatan khusus ke supplier (opsional)">
                    </div>

                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold" id="btn-save-po"><i class="bi bi-save me-1"></i> Simpan Purchase Order</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL VIEW DETAIL PO -->
<div class="modal fade" id="modal-detail-po" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-2">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-text me-2 text-info"></i> Detail Purchase Order</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="detail-po-body"></div>
        </div>
    </div>
</div>

<!-- SweetAlert2 & DataTables JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const table =  $('#table-po').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: "{{ route('inventori.pembelian.po.data') }}",
            data: function (d) {
                d.start_date       =  $('#filter-start-date').val();
                d.end_date         =  $('#filter-end-date').val();
                d.business_unit_id =  $('#filter-bu').val();
                d.supplier_id      =  $('#filter-supplier').val();
                d.warehouse_id     =  $('#filter-wh').val();
            }
        },
        columns: [
            { data: 'formatted_date', className: 'text-center' },
            { data: 'po_no', className: 'text-center font-monospace fw-bold text-primary' },
            { data: 'supplier_name', className: 'fw-semibold text-dark' },
            { data: 'warehouse_name' },
            { data: 'business_unit_name', className: 'text-center' },
            { data: 'formatted_total', className: 'text-end font-monospace fw-bold' },
            { data: 'receipt_progress', className: 'text-center font-monospace small' },
            {
                data: 'status',
                className: 'text-center',
                render: function (data) {
                    if (data === 'draft')     return '<span class="badge bg-secondary">DRAFT</span>';
                    if (data === 'approved')  return '<span class="badge bg-primary">APPROVED</span>';
                    if (data === 'partial')   return '<span class="badge bg-warning text-dark">PARTIAL</span>';
                    if (data === 'completed') return '<span class="badge bg-success">COMPLETED</span>';
                    if (data === 'closed')    return '<span class="badge bg-dark">CLOSED</span>';
                    return `<span class="badge bg-danger">${data.toUpperCase()}</span>`;
                }
            },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render: function (data, type, row) {
                    let actions = `<div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-info btn-view-po" data-id="${row.id}" title="Detail PO"><i class="bi bi-eye"></i></button>
                        <a href="{{ url('/inventori/pembelian/po') }}/${row.id}/print" target="_blank" class="btn btn-outline-secondary" title="Cetak Nota PO"><i class="bi bi-printer"></i></a>`;

                    // Tombol Penerimaan Barang (Trigger jika status Approved / Partial)
                    if (['approved', 'partial'].includes(row.status)) {
                        actions += `<a href="{{ url('/inventori/pembelian/penerimaan/create') }}?po_id=${row.id}" class="btn btn-success" title="Terima Barang"><i class="bi bi-box-arrow-in-down"></i> Penerimaan</a>`;
                    }

                    // Tombol Approve (Hanya Draft)
                    if (row.status === 'draft') {
                        actions += `<button type="button" class="btn btn-success btn-approve-po" data-id="${row.id}" data-no="${row.po_no}" title="Approve PO"><i class="bi bi-check-circle"></i> Approve</button>`;
                    }

                    // Tombol Edit (Hanya Draft)
                    if (row.status === 'draft') {
                        actions += `<button type="button" class="btn btn-outline-warning btn-edit-po" data-id="${row.id}" title="Edit Draft"><i class="bi bi-pencil"></i></button>`;
                        actions += `<button type="button" class="btn btn-outline-danger btn-delete-po" data-id="${row.id}" data-no="${row.po_no}" title="Hapus Draft"><i class="bi bi-trash"></i></button>`;
                    }

                    // Tombol Force Close PO (Jika belum completed/closed)
                    if (!['completed', 'closed', 'draft'].includes(row.status)) {
                        actions += `<button type="button" class="btn btn-outline-dark btn-close-po" data-id="${row.id}" data-no="${row.po_no}" title="Tutup PO"><i class="bi bi-x-circle"></i> Close</button>`;
                    }

                    actions += `</div>`;
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

    // Auto-Fetch Info Supplier saat Select Vendor Berubah
     $('#supplier-id').on('change', function () {
        const suppId =  $(this).val();
        if (suppId) {
             $.get(`{{ url('/inventori/pembelian/po/supplier-info') }}/${suppId}`, function (res) {
                if (res.success) {
                     $('#info-supplier-name').text(res.data.name);
                     $('#info-supplier-address').text('Alamat: ' + res.data.address);
                     $('#info-supplier-phone').text('Telp: ' + res.data.phone + ' | NPWP: ' + res.data.npwp);
                }
            });
        } else {
             $('#info-supplier-name').text('Pilih vendor untuk melihat detail info...');
             $('#info-supplier-address').text('-');
             $('#info-supplier-phone').text('-');
        }
    });

    // Dinamis Item Row PO
     $('#btn-add-item-row').on('click', function () {
        addItemRow();
    });

    

    async function addItemRow(prodId = '', qty = 1, price = 0, discount = 0) {
        let productOptions = '<option value="">-- Pilih Barang --</option>';

        @foreach($products as $p)
            productOptions += `<option value="{{ $p->id }}" data-unit="{{ $p->unit_code ?? '' }}"${String({{ $p->id }}) === String(prodId) ? ' selected' : ''}>{{ $p->code }} - {{ $p->name }}</option>`;
        @endforeach

        const rowId = Date.now();
        const html = `
            <tr id="row-${rowId}">
                <td>
                    <select name="products[]" class="form-select form-select-sm select-product" required>
                        ${productOptions}
                    </select>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm text-center input-unit" value="" readonly>
                </td>
                <td><input type="number" name="qty[]" class="form-control form-control-sm text-center input-qty" step="0.01" value="${qty}" required></td>
                <td><input type="number" name="unit_price[]" class="form-control form-control-sm text-end input-price" step="0.01" value="${price}" required></td>
                <td><input type="number" name="discount[]" class="form-control form-control-sm text-end input-discount" step="0.01" value="${discount}"></td>
                <td class="text-end font-monospace fw-bold cell-subtotal">Rp 0</td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row"><i class="bi bi-x"></i></button></td>
            </tr>
        `;

        $('#tbody-po-items').append(html);

        const row = $('#row-' + rowId);
        const selected = row.find('.select-product option:selected');
        row.find('.input-unit').val(selected.data('unit') || '');

        calcSubtotal(row);
    }

     $(document).on('click', '.btn-remove-row', function () {
         $(this).closest('tr').remove();
        calcGrandTotal();
    });

     $(document).on('change', '.select-product', function () {
        const row = $(this).closest('tr');
        const selected = $(this).find(':selected');

        row.find('.input-unit').val(selected.data('unit') || '');
        calcSubtotal(row);
    });

     $(document).on('input', '.input-qty, .input-price, .input-discount', function () {
        calcSubtotal( $(this).closest('tr'));
    });

    function calcSubtotal(row) {
        const qty   = parseFloat(row.find('.input-qty').val()) || 0;
        const price = parseFloat(row.find('.input-price').val()) || 0;
        const disc  = parseFloat(row.find('.input-discount').val()) || 0;
        const sub   = (qty * price) - disc;

        row.find('.cell-subtotal').text('Rp ' + sub.toLocaleString('id-ID'));
        calcGrandTotal();
    }

    function calcGrandTotal() {
        let grand = 0;
         $('#tbody-po-items tr').each(function () {
            const qty   = parseFloat( $(this).find('.input-qty').val()) || 0;
            const price = parseFloat( $(this).find('.input-price').val()) || 0;
            const disc  = parseFloat( $(this).find('.input-discount').val()) || 0;
            grand += (qty * price) - disc;
        });
         $('#footer-grand-total').text('Rp ' + grand.toLocaleString('id-ID'));
    }

    // Modal Add PO Trigger
     $('#btn-add-po').on('click', function () {
         $('#form-po')[0].reset();
         $('#po-id').val('');
         $('#po-no').val('{{  $autoPoNo }}');
         $('#tbody-po-items').empty();
        addItemRow();
         $('#modal-po-title').html('<i class="bi bi-cart-plus me-2 text-primary"></i> Buat Purchase Order Baru');
         $('#modal-po').modal('show');
    });

    // Form Submit (Store / Update) AJAX
     $('#form-po').on('submit', function (e) {
        e.preventDefault();
        const poId =  $('#po-id').val();
        const url  = poId ? `{{ url('/inventori/pembelian/po') }}/${poId}` : `{{ route('inventori.pembelian.po.store') }}`;
        const type = poId ? 'PUT' : 'POST';

         $.ajax({
            url: url,
            type: type,
            data:  $(this).serialize(),
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

    // Action Approve PO
    $(document).on('click', '.btn-approve-po', function () {
        const id = $(this).data('id');
        const no = $(this).data('no');

        Swal.fire({
            title: 'Approve PO?',
            text: `PO [${no}] akan di-approve dan tidak lagi berstatus draft.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Ya, Approve',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post(`{{ url('/inventori/pembelian/po') }}/${id}/approve`, {
                    _token: '{{ csrf_token() }}'
                }, function (res) {
                    if (res.success) {
                        table.ajax.reload();
                        Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.message, timer: 1500, showConfirmButton: false });
                    }
                }).fail(function (xhr) {
                    Swal.fire({ icon: 'error', title: 'Gagal!', text: xhr.responseJSON?.message || 'PO gagal di-approve' });
                });
            }
        });
    });

    // Action Edit PO Modal Populating
     $(document).on('click', '.btn-edit-po', function () {
        const id =  $(this).data('id');
         $.get(`{{ url('/inventori/pembelian/po') }}/${id}/edit-data`, function (res) {
            if (res.success) {
                 $('#po-id').val(res.po.id);
                 $('#po-no').val(res.po.po_no);
                 $('#po-date').val(res.po.po_date.split(' ')[0]);
                 $('#business-unit-id').val(res.po.business_unit_id);
                 $('#warehouse-id').val(res.po.warehouse_id);
                 $('#supplier-id').val(res.po.supplier_id).trigger('change');
                 $('#po-memo').val(res.po.memo);

                 $('#tbody-po-items').empty();
                res.items.forEach(i => {
                    addItemRow(i.product_id, i.qty, i.unit_price, i.discount);
                });

                 $('#modal-po-title').html('<i class="bi bi-pencil-square me-2 text-warning"></i> Edit Draft Purchase Order');
                 $('#modal-po').modal('show');
            }
        });
    });

    // Action Force Close PO (SweetAlert2)
     $(document).on('click', '.btn-close-po', function () {
        const id =  $(this).data('id');
        const no =  $(this).data('no');

        Swal.fire({
            title: 'Tutup / Close PO?',
            text: `Apakah Anda yakin ingin menutup PO [${no}] secara permanen?`,
            icon: 'warning',
            input: 'text',
            inputPlaceholder: 'Alasan penutupan PO (opsional)',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            confirmButtonText: 'Ya, Close PO!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                 $.post(`{{ url('/inventori/pembelian/po') }}/${id}/close`, {
                    _token: '{{ csrf_token() }}',
                    reason: result.value
                }, function (res) {
                    if (res.success) {
                        table.ajax.reload();
                        Swal.fire({ icon: 'success', title: 'Berhasil!', text: res.message, timer: 2000, showConfirmButton: false });
                    }
                });
            }
        });
    });

    // Action Delete Draft PO
     $(document).on('click', '.btn-delete-po', function () {
        const id =  $(this).data('id');
        const no =  $(this).data('no');

        Swal.fire({
            title: 'Hapus Draft PO?',
            text: `Draft PO [${no}] akan dihapus!`,
            icon: 'error',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Ya, Hapus!',
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
                            Swal.fire({ icon: 'success', title: 'Terhapus!', text: res.message, timer: 2000, showConfirmButton: false });
                        }
                    }
                });
            }
        });
    });

    // Modal Detail PO View
     $(document).on('click', '.btn-view-po', function () {
        const id =  $(this).data('id');
         $.get(`{{ url('/inventori/pembelian/po') }}/${id}`, function (res) {
            if (res.success) {
                let html = `
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="small text-muted">No. PO</div><div class="fw-bold font-monospace text-primary">${res.po.po_no}</div>
                            <div class="small text-muted mt-2">Supplier</div><div class="fw-bold">${res.po.supplier_name}</div>
                            <div class="small text-muted">${res.po.supplier_address}</div>
                        </div>
                        <div class="col-6">
                            <div class="small text-muted">Gudang Tujuan</div><div class="fw-bold">${res.po.warehouse_name}</div>
                            <div class="small text-muted mt-2">Unit Bisnis</div><div class="fw-bold">${res.po.business_unit_name || '-'}</div>
                            <div class="small text-muted mt-2">Status</div><div><span class="badge bg-primary">${res.po.status.toUpperCase()}</span></div>
                        </div>
                    </div>
                    <table class="table table-sm table-bordered align-middle">
                        <thead class="table-light text-center">
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
                html += `</tbody><tfoot class="table-light fw-bold"><tr><td colspan="4" class="text-end">TOTAL:</td><td class="text-end font-monospace">Rp ${total.toLocaleString('id-ID')}</td></tr></tfoot></table>`;

                 $('#detail-po-body').html(html);
                 $('#modal-detail-po').modal('show');
            }
        });
    });

});
</script>
@endsection