@extends('layouts.app')

@section('title', 'Daftar Purchase Order (PO)')

@section('content')
<div class="container-fluid py-4">
    <!-- Header Page & Action Buttons -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1 text-dark">Daftar Purchase Order (PO)</h4>
            <p class="text-muted small mb-0">Kelola pemesanan barang ke supplier</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTambahPO">
                <i class="bi bi-plus-circle me-1"></i> + PO Baru
            </button>
            <a href="{{ route('purchase_orders.export_excel') }}" id="btnExportExcel" class="btn btn-outline-success fw-bold shadow-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> 📊 Cetak Excel
            </a>
        </div>
    </div>

    <!-- 1. Bar Filter Periode, Unit Bisnis, Supplier & Status -->
    <div class="card border-0 shadow-sm mb-4 bg-white rounded-3">
        <div class="card-body p-3">
            <form id="filterForm" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-secondary mb-1">Mulai Tanggal</label>
                    <input type="date" id="filter_start_date" class="form-control form-control-sm" value="{{ date('Y-m-01') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-secondary mb-1">Sampai Tanggal</label>
                    <input type="date" id="filter_end_date" class="form-control form-control-sm" value="{{ date('Y-m-t') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary mb-1">Unit Bisnis</label>
                    <select id="filter_business_unit_id" class="form-select form-select-sm">
                        <option value="">-- Semua Unit Bisnis --</option>
                        @foreach($businessUnits as $bu)
                            <option value="{{ $bu->id }}">{{ $bu->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary mb-1">Supplier</label>
                    <select id="filter_supplier_id" class="form-select form-select-sm">
                        <option value="">-- Semua Supplier --</option>
                        @foreach($suppliers as $sup)
                            <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-secondary mb-1">Status PO</label>
                    <select id="filter_status" class="form-select form-select-sm">
                        <option value="">-- Semua Status --</option>
                        <option value="draft">Draft</option>
                        <option value="pending_approval">Pending Approval</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. Tabel DataTables AJAX Server-Side -->
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body p-3">
            <div class="table-responsive">
                <table id="tablePO" class="table table-hover align-middle w-100 table-striped border-top">
                    <thead class="table-light">
                        <tr class="text-secondary small text-uppercase">
                            <th>No. PO</th>
                            <th>Tanggal PO</th>
                            <th>Unit Bisnis</th>
                            <th>Supplier</th>
                            <th>Gudang Tujuan</th>
                            <th class="text-end">Total Nominal</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width: 160px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================= -->
<!-- MODAL 1: FORM TAMBAH PURCHASE ORDER (PO) POPUP            -->
<!-- ========================================================= -->
<div class="modal fade" id="modalTambahPO" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <form id="formTambahPO" action="{{ route('purchase_orders.store') }}" method="POST">
                @csrf
                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-plus me-2"></i>Form Tambah Purchase Order Baru</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- Form Header Input -->
                    <div class="row g-3 mb-4 bg-light p-3 rounded border">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Unit Bisnis <span class="text-danger">*</span></label>
                            <select name="business_unit_id" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Unit Bisnis --</option>
                                @foreach($businessUnits as $bu)
                                    <option value="{{ $bu->id }}">{{ $bu->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Supplier <span class="text-danger">*</span></label>
                            <select name="supplier_id" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Supplier --</option>
                                @foreach($suppliers as $sup)
                                    <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Gudang Penerimaan <span class="text-danger">*</span></label>
                            <select name="warehouse_id" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Gudang Tujuan --</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Tanggal PO <span class="text-danger">*</span></label>
                            <input type="date" name="po_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                        </div>
                    </div>

                    <!-- Items Detail Dynamic Grid -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-box-seam me-1"></i>Rincian Barang Pemesanan</h6>
                        <button type="button" id="btnAddRowPO" class="btn btn-sm btn-outline-primary fw-bold">
                            + Tambah Baris Produk
                        </button>
                    </div>

                    <div class="table-responsive mb-3">
                        <table class="table table-bordered table-sm align-middle" id="tableItemPO">
                            <thead class="table-light text-secondary small">
                                <tr>
                                    <th style="width: 38%;">Produk / Barang <span class="text-danger">*</span></th>
                                    <th style="width: 12%;">Qty</th>
                                    <th style="width: 20%;">Harga Satuan (Rp)</th>
                                    <th style="width: 15%;">Diskon (Rp)</th>
                                    <th style="width: 20%;">Subtotal (Rp)</th>
                                    <th style="width: 5%;" class="text-center">#</th>
                                </tr>
                            </thead>
                            <tbody id="poItemBody">
                                <!-- Dynamic Rows via JavaScript -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Footer Notes & Summary -->
                    <div class="row pt-2">
                        <div class="col-md-7">
                            <label class="form-label small fw-bold">Catatan / Memo PO</label>
                            <textarea name="memo" class="form-control form-control-sm" rows="3" placeholder="Tuliskan catatan khusus pengiriman, ketentuan garansi, atau instruksi supplier..."></textarea>
                        </div>
                        <div class="col-md-5">
                            <div class="card bg-primary-subtle border-0 p-3 rounded">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-uppercase text-secondary small">Total Nilai PO:</span>
                                    <h3 class="fw-bold text-primary mb-0" id="lblGrandTotalPO">Rp 0</h3>
                                    <input type="hidden" name="total_amount" id="inputGrandTotalPO" value="0">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold"><i class="bi bi-check-circle me-1"></i>Simpan & Ajukan PO</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================= -->
<!-- MODAL 2: APPROVAL PURCHASE ORDER                           -->
<!-- ========================================================= -->
<div class="modal fade" id="modalApprovalPO" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="formApprovalPO" method="POST">
                @csrf
                <div class="modal-header bg-dark text-white py-3">
                    <h5 class="modal-title fw-bold"><i class="bi bi-shield-check me-2"></i>Approval PO: <span id="apprPoNo"></span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-light border mb-3">
                        <p class="mb-1 text-secondary small">Supplier: <strong id="apprSupplier" class="text-dark"></strong></p>
                        <p class="mb-0 text-secondary small">Total Nominal: <strong id="apprTotal" class="text-primary fs-6"></strong></p>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Keputusan Approval <span class="text-danger">*</span></label>
                        <select name="status" id="apprStatusSelect" class="form-select form-select-sm" required>
                            <option value="approved">✅ Approve (Setujui PO)</option>
                            <option value="rejected">❌ Reject (Tolak PO)</option>
                        </select>
                    </div>

                    <div class="mb-3" id="rejectReasonBox" style="display: none;">
                        <label class="form-label small fw-bold">Alasan Penolakan <span class="text-danger">*</span></label>
                        <textarea name="cancellation_reason" class="form-control form-control-sm" rows="3" placeholder="Tuliskan alasan penolakan PO..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-success fw-bold">Proses Keputusan</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // Update Export Excel URL secara dinamis sesuai filter
    function updateExportExcelUrl() {
        let params = $.param({
            start_date: $('#filter_start_date').val(),
            end_date: $('#filter_end_date').val(),
            business_unit_id: $('#filter_business_unit_id').val(),
            supplier_id: $('#filter_supplier_id').val(),
            status: $('#filter_status').val()
        });
        $('#btnExportExcel').attr('href', "{{ route('purchase_orders.export_excel') }}?" + params);
    }
    updateExportExcelUrl();

    // 1. DataTables Server-Side AJAX Handler
    let tablePO = $('#tablePO').DataTable({
        processing: true,
        serverSide: true,
        ordering: true,
        pageLength: 10,
        ajax: {
            url: "{{ route('purchase_orders.data') }}",
            type: "GET",
            data: function (d) {
                d.start_date = $('#filter_start_date').val();
                d.end_date = $('#filter_end_date').val();
                d.business_unit_id = $('#filter_business_unit_id').val();
                d.supplier_id = $('#filter_supplier_id').val();
                d.status = $('#filter_status').val();
            }
        },
        columns: [
            { data: 'po_number', name: 'po_number', className: 'fw-bold text-primary' },
            { data: 'po_date', name: 'po_date' },
            { data: 'business_unit_name', name: 'business_unit_name' },
            { data: 'supplier_name', name: 'supplier_name' },
            { data: 'warehouse_name', name: 'warehouse_name' },
            { data: 'total_amount_formatted', name: 'total_amount', className: 'text-end fw-bold' },
            { data: 'status_badge', name: 'status', className: 'text-center' },
            { data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-center' }
        ],
        language: {
            processing: '<div class="spinner-border spinner-border-sm text-primary" role="status"></div> Memuat data PO...',
            search: "Cari PO:",
            lengthMenu: "Tampilkan _MENU_ data",
            info: "Menampilkan _START_ sampai _END_ dari _TOTAL_ PO",
            infoEmpty: "Tidak ada data PO",
            zeroRecords: "Data PO tidak ditemukan",
            paginate: {
                first: "Awal",
                last: "Akhir",
                next: "→",
                previous: "←"
            }
        }
    });

    // Event reload DataTables & update Excel Link saat filter berubah
    $('#filter_start_date, #filter_end_date, #filter_business_unit_id, #filter_supplier_id, #filter_status').on('change', function() {
        tablePO.draw();
        updateExportExcelUrl();
    });

    // 2. Dynamic Rows untuk Modal Tambah PO
    let productOptionsHTML = `@foreach($products as $prod)
        <option value="{{ $prod->id }}" data-price="{{ $prod->cost_price ?? 0 }}">{{ $prod->code }} - {{ $prod->name }}</option>
    @endforeach`;

    function addPORow() {
        let idx = $('#poItemBody tr').length;
        let row = `
            <tr>
                <td>
                    <select name="items[${idx}][product_id]" class="form-select form-select-sm sel-product" required>
                        <option value="">-- Pilih Produk --</option>
                        ${productOptionsHTML}
                    </select>
                </td>
                <td>
                    <input type="number" name="items[${idx}][quantity]" class="form-control form-control-sm inp-qty text-center" value="1" min="0.01" step="any" required>
                </td>
                <td>
                    <input type="number" name="items[${idx}][unit_price]" class="form-control form-control-sm inp-price text-end" value="0" min="0" step="any" required>
                </td>
                <td>
                    <input type="number" name="items[${idx}][discount_amount]" class="form-control form-control-sm inp-disc text-end" value="0" min="0" step="any">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm inp-subtotal fw-bold text-end bg-light" value="0" readonly>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger btnDelRow">×</button>
                </td>
            </tr>
        `;
        $('#poItemBody').append(row);
    }

    $('#btnAddRowPO').on('click', function() { addPORow(); });
    addPORow(); // Inisialisasi 1 baris awal

    $(document).on('click', '.btnDelRow', function() {
        if($('#poItemBody tr').length > 1) {
            $(this).closest('tr').remove();
            calculatePOTotals();
        }
    });

    $(document).on('change', '.sel-product', function() {
        let price = $(this).find(':selected').data('price') || 0;
        let tr = $(this).closest('tr');
        tr.find('.inp-price').val(price);
        calculateRowSubtotal(tr);
    });

    $(document).on('input', '.inp-qty, .inp-price, .inp-disc', function() {
        calculateRowSubtotal($(this).closest('tr'));
    });

    function calculateRowSubtotal(tr) {
        let qty = parseFloat(tr.find('.inp-qty').val()) || 0;
        let price = parseFloat(tr.find('.inp-price').val()) || 0;
        let disc = parseFloat(tr.find('.inp-disc').val()) || 0;
        let subtotal = Math.max(0, (qty * price) - disc);
        tr.find('.inp-subtotal').val(subtotal.toLocaleString('id-ID'));
        calculatePOTotals();
    }

    function calculatePOTotals() {
        let grandTotal = 0;
        $('#poItemBody tr').each(function() {
            let qty = parseFloat($(this).find('.inp-qty').val()) || 0;
            let price = parseFloat($(this).find('.inp-price').val()) || 0;
            let disc = parseFloat($(this).find('.inp-disc').val()) || 0;
            grandTotal += Math.max(0, (qty * price) - disc);
        });
        $('#lblGrandTotalPO').text('Rp ' + grandTotal.toLocaleString('id-ID'));
        $('#inputGrandTotalPO').val(grandTotal);
    }

    // 3. Trigger Modal Approval PO
    $(document).on('click', '.btnApprovePO', function() {
        let poId = $(this).data('id');
        let poNo = $(this).data('pono');
        let supplier = $(this).data('supplier');
        let total = $(this).data('total');

        $('#apprPoNo').text(poNo);
        $('#apprSupplier').text(supplier);
        $('#apprTotal').text(total);
        $('#formApprovalPO').attr('action', `/purchase-orders/${poId}/approval`);

        $('#modalApprovalPO').modal('show');
    });

    $('#apprStatusSelect').on('change', function() {
        if ($(this).val() === 'rejected') {
            $('#rejectReasonBox').slideDown();
        } else {
            $('#rejectReasonBox').slideUp();
        }
    });
});
</script>
@endpush
