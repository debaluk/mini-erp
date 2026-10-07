@extends('layouts.app')

@section('title')

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- HEADER HALAMAN & BREADCRUMB --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-box-arrow-in-left text-danger me-2"></i>Retur Penjualan</h4>
            <p class="text-muted small mb-0">Pencatatan Pengembalian Barang</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-success btn-sm fw-semibold" id="btnExportExcel">
                <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
            </button>
            <button type="button" class="btn btn-danger btn-sm fw-semibold" id="btnCreateReturn">
                <i class="bi bi-plus-lg me-1"></i> Buat Retur Baru
            </button>
        </div>
    </div>

   

    {{-- PANEL FILTER DATA --}}
    <div class="card border-0 shadow-sm mb-2">
        <div class="card-body py-3">
            <form id="filterForm" class="row g-2 align-items-center">
                <div class="col-md-2">
                    <label class="form-label extra-small text-muted fw-bold mb-1">Mulai Tanggal</label>
                    <input type="date" class="form-control form-control-sm" id="filterStartDate" value="{{ date('Y-m-01') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label extra-small text-muted fw-bold mb-1">Sampai Tanggal</label>
                    <input type="date" class="form-control form-control-sm" id="filterEndDate" value="{{ date('Y-m-t') }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label extra-small text-muted fw-bold mb-1">Unit Bisnis</label>
                    <select class="form-select form-select-sm" id="filterBusinessUnit">
                        <option value="">-- Semua BU --</option>
                        @foreach($businessUnits ?? [] as $bu)
                            <option value="{{ $bu->id }}">{{ $bu->code }} - {{ $bu->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label extra-small text-muted fw-bold mb-1">Gudang Penerima</label>
                    <select class="form-select form-select-sm" id="filterWarehouse">
                        <option value="">-- Semua Gudang --</option>
                        @foreach($warehouses ?? [] as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1 align-self-end">
                    <button type="button" class="btn btn-primary btn-sm w-100" id="btnApplyFilter">
                        <i class="bi bi-search me-1"></i> Filter
                    </button>
                    <button type="button" class="btn btn-light btn-sm border" id="btnResetFilter" title="Reset Filter">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- TABEL DATATABLE AJAX --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 w-100" id="salesReturnTable">
                    <thead class="bg-light text-muted extra-small text-uppercase">
                        <tr>
                            <th class="ps-3">No. Retur</th>
                            <th>Tanggal</th>
                            <th>No. Invoice Asal</th>
                            <th>Pelanggan</th>
                            <th>Gudang</th>
                            <th class="text-end">Total Value (Rp)</th>
                            <th>Status</th>
                            <th class="text-center pe-3" style="width: 120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

</div>

{{-- ========================================================================= --}}
{{-- MODAL 1: FORM TAMBAH / EDIT RETUR PENJUALAN --}}
{{-- ========================================================================= --}}
<div class="modal fade" id="modalReturnForm" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white py-2 px-3">
                <h6 class="modal-title fw-bold" id="modalReturnFormTitle">
                    <i class="bi bi-arrow-return-left me-2"></i>Form Retur Penjualan (SAL-02)
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <form id="returnForm">
                    <input type="hidden" id="returnId" name="return_id">
                    <input type="hidden" id="entityId" name="entity_id" value="{{ auth()->user()->entity_id ?? 1 }}">
                    <input type="hidden" id="businessUnitId" name="business_unit_id">
                    <input type="hidden" id="saleId" name="sale_id">
                    <input type="hidden" id="customerId" name="customer_id">

                    {{-- INFORMASI KOREKSI PADA MODE EDIT --}}
                    <div class="alert alert-warning py-2 px-3 mb-3 d-none" id="editReversalAlert">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle-fill fs-5 me-2 text-warning"></i>
                            <div class="small">
                                <strong>Mode Koreksi / Edit Retur Posted:</strong>
                                Perubahan data retur akan memperbarui mutasi stok dan isi jurnal pada dokumen retur yang sama. Tidak dibuat jurnal reversal.
                            </div>
                        </div>
                    </div>

                    {{-- HEADER SELECTION --}}
                    <div class="row g-2 mb-3 bg-light p-2 rounded border">
                        <div class="col-md-3">
                            <label class="form-label extra-small fw-bold text-muted mb-1">Cari Invoice Penjualan Asal *</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control fw-bold bg-white" id="invoiceNoDisplay" placeholder="Klik tombol cari..." readonly required>
                                <button class="btn btn-danger" type="button" id="btnOpenSearchInvoice">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label extra-small fw-bold text-muted mb-1">Pelanggan</label>
                            <input type="text" class="form-control form-control-sm bg-white" id="customerNameDisplay" placeholder="-" readonly>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label extra-small fw-bold text-muted mb-1">Tanggal Retur *</label>
                            <input type="datetime-local" class="form-control form-control-sm" id="returnDate" name="return_date" required value="{{ date('Y-m-d\TH:i') }}">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label extra-small fw-bold text-muted mb-1">Gudang Retur *</label>
                            <select class="form-select form-select-sm" id="warehouseId" name="warehouse_id" required disabled>
                                <option value="">-- Pilih Gudang --</option>
                                @foreach($warehouses ?? [] as $wh)
                                    <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label extra-small fw-bold text-muted mb-1">No. Retur</label>
                            <input type="text" class="form-control form-control-sm bg-white" id="returnNo" name="return_no" placeholder="[Auto Generated]" readonly>
                        </div>
                    </div>

                    {{-- TABEL LINE ITEMS --}}
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold extra-small text-uppercase text-secondary">Rincian Barang yang Diretur</span>
                            <span class="badge bg-secondary extra-small" id="itemCountBadge">0 Item</span>
                        </div>
                        <div class="table-responsive border rounded">
                            <table class="table table-sm table-bordered align-middle mb-0" id="returnItemsTable">
                                <thead class="bg-light text-muted extra-small">
                                    <tr>
                                        <th style="width: 250px;">Nama Produk</th>
                                        <th style="width: 90px;" class="text-center">Qty Jual</th>
                                        <th style="width: 90px;" class="text-center">Sisa Retur</th>
                                        <th style="width: 100px;" class="text-center">Qty Retur *</th>
                                        <th style="width: 100px;">Satuan</th>
                                        <th style="width: 130px;" class="text-end">Harga Jual</th>
                                        <th style="width: 130px;" class="text-end">Total Refund</th>
                                        <th style="width: 140px;">Kondisi Barang *</th>
                                    </tr>
                                </thead>
                                <tbody id="returnItemsBody">
                                    <tr>
                                        <td colspan="8" class="text-center py-3 text-muted small">
                                            Silakan cari dan pilih Invoice Penjualan Asal terlebih dahulu.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- FOOTER / CATATAN & TOTAL --}}
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label extra-small fw-bold text-muted mb-1">Alasan Retur / Catatan *</label>
                            <textarea class="form-control form-control-sm" id="returnReason" name="reason" rows="2" placeholder="Contoh: Barang rusak saat pengiriman / Kuantitas semen kurang..." required></textarea>
                        </div>
                        <div class="col-md-5">
                            <div class="card bg-light border-0">
                                <div class="card-body p-2">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="small text-muted">Total Nilai Retur:</span>
                                        <span class="fw-bold text-danger h5 mb-0" id="displayTotalReturn">Rp 0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer py-2 px-3 bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-danger btn-sm fw-semibold" id="btnSaveReturn">
                    <i class="bi bi-check-circle me-1"></i> Simpan &amp; Post Retur
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ========================================================================= --}}
{{-- MODAL 2: CARI & LOOKUP NOTA PENJUALAN --}}
{{-- ========================================================================= --}}
<div class="modal fade" id="modalSearchInvoice" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-secondary text-white py-2 px-3">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-search me-2"></i>Pilih Invoice Penjualan Asal
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-2 mb-3">
                    <div class="col-md-8">
                        <input type="text" class="form-control form-control-sm" id="searchInvoiceKeyword" placeholder="Cari No Invoice / Nama Pelanggan...">
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-primary btn-sm w-100" id="btnDoSearchInvoice">
                            <i class="bi bi-search me-1"></i> Cari Invoice
                        </button>
                    </div>
                </div>
                <div class="table-responsive border rounded">
                    <table class="table table-sm table-hover align-middle mb-0" id="lookupInvoiceTable">
                        <thead class="bg-light extra-small text-muted">
                            <tr>
                                <th>No. Invoice</th>
                                <th>Tanggal</th>
                                <th>Pelanggan</th>
                                <th class="text-end">Total Invoice</th>
                                <th class="text-center" style="width: 80px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="lookupInvoiceBody">
                            <tr><td colspan="5" class="text-center py-3 text-muted">Ketik kata kunci untuk mencari invoice...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ========================================================================= --}}
{{-- MODAL 3: CETAK NOTA RETUR / CREDIT NOTE (PRINTABLE VIEW) --}}
{{-- ========================================================================= --}}
<div class="modal fade" id="modalPrintReturn" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header py-2 px-3 bg-dark text-white">
                <h6 class="modal-title fw-bold"><i class="bi bi-printer me-2"></i>Cetak Nota Retur Penjualan</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="printableArea">
                {{-- STYLING HANYA UNTUK CETAK --}}
                <style>
                    @media print {
                        body * { visibility: hidden; }
                        #printableArea, #printableArea * { visibility: visible; }
                        #printableArea { position: absolute; left: 0; top: 0; width: 100%; }
                        .no-print { display: none !important; }
                    }
                </style>
                <div class="text-center mb-3 pb-2 border-bottom">
                    <h5 class="fw-bold mb-0 text-uppercase" id="printCompanyName">{{ auth()->user()->entity->name ?? 'Entitas' }}</h5>
                    <p class="small text-muted mb-0" id="printCompanyAddress">{{ auth()->user()->entity->address ?? '' }}{{ !empty(auth()->user()->entity->phone) ? ' | Telp: ' . auth()->user()->entity->phone : '' }}</p>
                    <h6 class="fw-bold mt-2 text-decoration-underline">NOTA KREDIT / RETUR PENJUALAN</h6>
                    <span class="badge bg-outline-dark text-dark border extra-small" id="printReturnNo">RET-XXXXXX</span>
                </div>

                <div class="row extra-small mb-3">
                    <div class="col-6">
                        <table class="table table-borderless table-sm mb-0">
                            <tr><td class="text-muted p-0" style="width: 100px;">Pelanggan</td><td class="fw-bold p-0" id="printCustomerName">-</td></tr>
                            <tr><td class="text-muted p-0">No. Ref Invoice</td><td class="fw-bold p-0" id="printInvoiceNo">-</td></tr>
                        </table>
                    </div>
                    <div class="col-6 text-end">
                        <table class="table table-borderless table-sm mb-0">
                            <tr><td class="text-muted p-0">Tanggal Retur</td><td class="fw-bold p-0" id="printReturnDate">-</td></tr>
                            <tr><td class="text-muted p-0">Gudang Penerima</td><td class="fw-bold p-0" id="printWarehouseName">-</td></tr>
                        </table>
                    </div>
                </div>

                <table class="table table-sm table-bordered extra-small mb-3">
                    <thead class="bg-light">
                        <tr>
                            <th>Nama Produk</th>
                            <th class="text-center">Qty</th>
                            <th class="text-center">Kondisi</th>
                            <th class="text-end">Harga Satuan</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="printItemsBody"></tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-end fw-bold">GRAND TOTAL RETUR:</td>
                            <td class="text-end fw-bold text-danger" id="printGrandTotal">Rp 0</td>
                        </tr>
                    </tfoot>
                </table>

                <div class="extra-small mb-4">
                    <strong>Alasan Retur:</strong> <span id="printReason">-</span>
                </div>

                <div class="row text-center extra-small mt-4 pt-3">
                    <div class="col-4">
                        <p class="mb-4">Pelanggan,</p>
                        <br><p class="fw-bold mb-0">( .......................... )</p>
                    </div>
                    <div class="col-4">
                        <p class="mb-4">Petugas Gudang,</p>
                        <br><p class="fw-bold mb-0">( .......................... )</p>
                    </div>
                    <div class="col-4">
                        <p class="mb-4">Kasir / Finance,</p>
                        <br><p class="fw-bold mb-0" id="printOperatorName">( .......................... )</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2 px-3 bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
                <button type="button" class="btn btn-primary btn-sm fw-semibold" onclick="window.print()">
                    <i class="bi bi-printer me-1"></i> Cetak Sekarang
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    // -----------------------------------------------------------------------
    // 1. INITIALIZE DATATABLE AJAX
    // -----------------------------------------------------------------------
    let returnTable = $('#salesReturnTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ url('/inventori/penjualan/retur/data') }}",
            data: function(d) {
                d.start_date = $('#filterStartDate').val();
                d.end_date = $('#filterEndDate').val();
                d.business_unit_id = $('#filterBusinessUnit').val();
                d.warehouse_id = $('#filterWarehouse').val();
            }
        },
        columns: [
            { data: 'return_no', name: 'return_no', className: 'fw-bold ps-3' },
            { data: 'return_date_formatted', name: 'return_date' },
            { data: 'invoice_no', name: 'sale.invoice_no' },
            { data: 'customer_name', name: 'customer.name' },
            { data: 'warehouse_name', name: 'warehouse.name' },
            { data: 'total_formatted', name: 'total', className: 'text-end fw-bold text-danger' },
            { 
                data: 'status', 
                name: 'status',
                render: function(data) {
                    if (data === 'posted') return '<span class="badge bg-success-subtle text-success border border-success">POSTED</span>';
                    if (data === 'draft') return '<span class="badge bg-warning-subtle text-warning border border-warning">DRAFT</span>';
                    return '<span class="badge bg-secondary">CANCELLED</span>';
                }
            },
            { data: 'actions', name: 'actions', orderable: false, searchable: false, className: 'text-center pe-3' }
        ],
        order: [[1, 'desc']],
        language: {
            search: "Cari Retur:",
            lengthMenu: "_MENU_",
            zeroRecords: "Tidak ada data retur penjualan ditemukan",
            processing: "Memuat data retur..."
        }
    });

    $('#btnApplyFilter').click(function() { returnTable.ajax.reload(); });
    $('#btnResetFilter').click(function() {
        $('#filterForm')[0].reset();
        returnTable.ajax.reload();
    });

    // -----------------------------------------------------------------------
    // 2. EXPORT EXCEL & CSV
    // -----------------------------------------------------------------------
    $('#btnExportExcel').click(function() {
        let params = $.param({
            start_date: $('#filterStartDate').val(),
            end_date: $('#filterEndDate').val(),
            business_unit_id: $('#filterBusinessUnit').val(),
            warehouse_id: $('#filterWarehouse').val()
        });
        window.location.href = "{{ url('/inventori/penjualan/retur/export') }}?" + params;
    });

    // -----------------------------------------------------------------------
    // 3. SEARCH & LOOKUP INVOICE ASAL
    // -----------------------------------------------------------------------
    $('#btnOpenSearchInvoice').click(function() {
        $('#modalSearchInvoice').modal('show');
    });

    $('#btnDoSearchInvoice').click(function() {
        let q = $('#searchInvoiceKeyword').val();
        $('#lookupInvoiceBody').html('<tr><td colspan="5" class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div> Memuat invoice...</td></tr>');
        
        $.get("{{ url('/inventori/penjualan/retur/lookup-invoices') }}", { keyword: q }, function(res) {
            let html = '';
            if (res.data.length === 0) {
                html = '<tr><td colspan="5" class="text-center py-3 text-muted">Invoice tidak ditemukan.</td></tr>';
            } else {
                res.data.forEach(function(inv) {
                    html += `<tr>
                        <td class="fw-bold">${inv.invoice_no}</td>
                        <td>${inv.sale_date_formatted}</td>
                        <td>${inv.customer_name}</td>
                        <td class="text-end fw-bold">Rp ${formatRupiah(inv.total)}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-xs btn-primary btn-select-invoice" 
                                data-id="${inv.id}" data-no="${inv.invoice_no}" data-customer="${inv.customer_name}" data-customer-id="${inv.customer_id}" data-bu-id="${inv.business_unit_id}">
                                Pilih
                            </button>
                        </td>
                    </tr>`;
                });
            }
            $('#lookupInvoiceBody').html(html);
        });
    });

    $(document).on('click', '.btn-select-invoice', function() {
        let saleId = $(this).data('id');
        let invoiceNo = $(this).data('no');
        let customerName = $(this).data('customer');
        let customerId = $(this).data('customer-id');
        let buId = $(this).data('bu-id');

        $('#saleId').val(saleId);
        $('#invoiceNoDisplay').val(invoiceNo);
        $('#customerNameDisplay').val(customerName);
        $('#customerId').val(customerId);
        $('#businessUnitId').val(buId);

        $('#modalSearchInvoice').modal('hide');
        loadSaleItemsForReturn(saleId);
    });

    function loadSaleItemsForReturn(saleId, existingReturnItems = []) {
        $('#returnItemsBody').html('<tr><td colspan="8" class="text-center py-3"><div class="spinner-border spinner-border-sm text-danger"></div> Memuat barang invoice...</td></tr>');

        $.get("{{ url('/inventori/penjualan/retur/sale-items') }}/" + saleId, {
            return_id: $('#returnId').val() || ''
        }, function(res) {
            if (res.sale && res.sale.warehouse_id) {
                $('#warehouseId').val(res.sale.warehouse_id).prop('disabled', true);
            } else {
                $('#warehouseId').val('').prop('disabled', true);
                alert('Gudang asal invoice tidak ditemukan. Retur tidak dapat diproses.');
            }

            let html = '';
            if (!res.items || res.items.length === 0) {
                html = '<tr><td colspan="8" class="text-center py-3 text-muted">Tidak ada item barang pada invoice ini.</td></tr>';
            } else {
                res.items.forEach(function(item, idx) {
                    let maxRet = item.qty_remaining; 
                    let existing = existingReturnItems.find(x => x.sale_item_id === item.id);
                    let qtyRetVal = existing ? existing.qty : 0;
                    let conditionVal = existing ? existing.condition : 'good';

                    html += `<tr class="item-row" data-sale-item-id="${item.id}" data-product-id="${item.product_id}" data-unit-id="${item.unit_id}" data-hpp-unit="${item.hpp_unit}" data-price="${item.unit_price}">
                        <td class="fw-semibold">${item.product_name}</td>
                        <td class="text-center">${item.qty_sale}</td>
                        <td class="text-center fw-bold text-success">${item.qty_remaining}</td>
                        <td>
                            <input type="number" step="0.001" min="0" max="${maxRet}" class="form-control form-control-sm text-center input-qty-retur fw-bold" value="${qtyRetVal}">
                        </td>
                        <td class="small">${item.unit_name}</td>
                        <td class="text-end">Rp ${formatRupiah(item.unit_price)}</td>
                        <td class="text-end fw-bold text-danger cell-subtotal">Rp 0</td>
                        <td>
                            <select class="form-select form-select-sm select-condition">
                                <option value="good" ${conditionVal === 'good' ? 'selected' : ''}>🟢 Bagus (Restock)</option>
                                <option value="damaged" ${conditionVal === 'damaged' ? 'selected' : ''}>🔴 Rusak (Afkir)</option>
                            </select>
                        </td>
                    </tr>`;
                });
            }
            $('#returnItemsBody').html(html);
            calculateGrandTotal();
        });
    }

    // -----------------------------------------------------------------------
    // 4. AUTO CALCULATE FORM RETUR
    // -----------------------------------------------------------------------
    $(document).on('keyup change input', '.input-qty-retur', function() {
        calculateGrandTotal();
    });

    function calculateGrandTotal() {
        let totalRefund = 0;
        let count = 0;

        $('.item-row').each(function() {
            let qtyRet = parseFloat($(this).find('.input-qty-retur').val()) || 0;
            let unitPrice = parseFloat($(this).data('price')) || 0;
            let hppUnit = parseFloat($(this).data('hpp-unit')) || 0;

            let subtotal = qtyRet * unitPrice;
            let subtotalHpp = qtyRet * hppUnit;

            $(this).find('.cell-subtotal').text('Rp ' + formatRupiah(subtotal));

            if (qtyRet > 0) {
                totalRefund += subtotal;
                count++;
            }
        });

        $('#displayTotalReturn').text('Rp ' + formatRupiah(totalRefund));
        $('#itemCountBadge').text(count + ' Item Diretur');
    }

    // -----------------------------------------------------------------------
    // 5. TAMBAH & EDIT MODAL HANDLERS
    // -----------------------------------------------------------------------
    $('#btnCreateReturn').click(function() {
        $('#returnForm')[0].reset();
        $('#returnId').val('');
        $('#modalReturnFormTitle').html('<i class="bi bi-arrow-return-left me-2"></i>Buat Retur Penjualan Baru');
        $('#editReversalAlert').addClass('d-none');
        $('#returnItemsBody').html('<tr><td colspan="8" class="text-center py-3 text-muted">Silakan cari dan pilih Invoice Penjualan Asal terlebih dahulu.</td></tr>');
        $('#displayTotalReturn').text('Rp 0');
        $('#modalReturnForm').modal('show');
    });

    $(document).on('click', '.btn-edit-return', function() {
        let id = $(this).data('id');
        $('#modalReturnFormTitle').html('<i class="bi bi-pencil-square me-2"></i>Koreksi / Edit Retur Penjualan');
        $('#editReversalAlert').removeClass('d-none');

        $.get("{{ url('/inventori/penjualan/retur') }}/" + id + "/edit", function(res) {
            let data = res.data;
            $('#returnId').val(data.id);
            $('#saleId').val(data.sale_id);
            $('#invoiceNoDisplay').val(data.sale.invoice_no);
            $('#customerNameDisplay').val(data.customer ? data.customer.name : 'Pelanggan Umum');
            $('#customerId').val(data.customer_id);
            $('#businessUnitId').val(data.business_unit_id);
            $('#returnDate').val(data.return_date_iso);
            $('#warehouseId').val(data.warehouse_id);
            $('#returnNo').val(data.return_no);
            $('#returnReason').val(data.reason);

            loadSaleItemsForReturn(data.sale_id, data.items);
            $('#modalReturnForm').modal('show');
        });
    });

    // -----------------------------------------------------------------------
    // 6. SIMPAN RETUR (CREATE / UPDATE - TANPA JURNAL REVERSAL)
    // -----------------------------------------------------------------------
    $('#btnSaveReturn').click(function() {
        let items = [];
        $('.item-row').each(function() {
            let qty = parseFloat($(this).find('.input-qty-retur').val()) || 0;
            if (qty > 0) {
                items.push({
                    sale_item_id: $(this).data('sale-item-id'),
                    product_id: $(this).data('product-id'),
                    unit_id: $(this).data('unit-id'),
                    qty: qty,
                    unit_price: parseFloat($(this).data('price')),
                    hpp_unit: parseFloat($(this).data('hpp-unit')),
                    condition: $(this).find('.select-condition').val()
                });
            }
        });

        if (items.length === 0) {
            alert('Peringatan: Minimal harus melengkapi 1 item barang yang diretur!');
            return;
        }

        const isEdit = Boolean($('#returnId').val());

        let payload = {
            return_id: $('#returnId').val(),
            entity_id: $('#entityId').val(),
            business_unit_id: $('#businessUnitId').val(),
            sale_id: $('#saleId').val(),
            customer_id: $('#customerId').val(),
            warehouse_id: $('#warehouseId').val(),
            return_date: $('#returnDate').val(),
            reason: $('#returnReason').val(),
            items: items,
            _token: "{{ csrf_token() }}"
        };

        let btn = $(this);
        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...');

        $.ajax({
            url: "{{ url('/inventori/penjualan/retur/store') }}",
            type: "POST",
            data: JSON.stringify(payload),
            contentType: "application/json",
            success: function(res) {
                btn.prop('disabled', false).html('<i class="bi bi-check-circle me-1"></i> Simpan & Post Retur');
                if (res.success) {
                    $('#modalReturnForm').modal('hide');
                    returnTable.ajax.reload();
                    showReturnSuccessPopup(
                        isEdit ? 'Retur Penjualan berhasil diupdate' : 'Retur Penjualan berhasil disimpan',
                        2000
                    );
                } else {
                    alert('Gagal: ' + res.message);
                }
            },
            error: function(err) {
                btn.prop('disabled', false).html('<i class="bi bi-check-circle me-1"></i> Simpan & Post Retur');
                alert('Terjadi kesalahan sistem saat menyimpan retur.');
            }
        });
    });

    // -----------------------------------------------------------------------
    // 7. HAPUS RETUR
    // -----------------------------------------------------------------------
    $(document).on('click', '.btn-delete-return', function() {
        const id = $(this).data('id');

        if (!confirm('Hapus retur ini? Jurnal dan efek stok retur juga akan dihapus.')) {
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true);

        $.ajax({
            url: "{{ url('/inventori/penjualan/retur') }}/" + id,
            type: "DELETE",
            data: { _token: "{{ csrf_token() }}" },
            success: function(res) {
                if (res.success) {
                    returnTable.ajax.reload(null, false);
                    showReturnSuccessPopup('Retur Penjualan berhasil dihapus', 1800);
                } else {
                    alert(res.message || 'Retur belum berhasil dihapus.');
                }
            },
            error: function(xhr) {
                alert(xhr.responseJSON?.message || 'Retur tidak dapat dihapus. Periksa apakah periodenya sudah ditutup.');
            },
            complete: function() {
                btn.prop('disabled', false);
            }
        });
    });

    // -----------------------------------------------------------------------
    // 7. LIHAT DETAIL RETUR
    // -----------------------------------------------------------------------
    $(document).on('click', '.btn-view-return', function() {
        let id = $(this).data('id');
        $.get("{{ url('/inventori/penjualan/retur') }}/" + id + "/print-data", function(res) {
            if (!res.success || !res.data) { alert('Data retur tidak ditemukan.'); return; }
            let data = res.data;
            $('#printReturnNo').text(data.return_no || '-');
            $('#printCustomerName').text(data.customer_name || '-');
            $('#printInvoiceNo').text(data.invoice_no || '-');
            $('#printReturnDate').text(data.return_date_formatted || '-');
            $('#printWarehouseName').text(data.warehouse_name || '-');
            $('#printReason').text(data.reason || '-');
            $('#printGrandTotal').text('Rp ' + formatRupiah(data.total));
            let html = '';
            (data.items || []).forEach(function(item) {
                html += '<tr>' +
                    '<td>' + (item.product_name || '-') + '</td>' +
                    '<td class="text-center">' + (item.qty || 0) + ' ' + (item.unit_name || '') + '</td>' +
                    '<td class="text-center">' + (item.condition === 'good' ? '🟢 BAGUS' : '🔴 RUSAK') + '</td>' +
                    '<td class="text-end">Rp ' + formatRupiah(item.unit_price) + '</td>' +
                    '<td class="text-end">Rp ' + formatRupiah(item.return_value) + '</td>' +
                    '</tr>';
            });
            $('#printItemsBody').html(html || '<tr><td colspan="5" class="text-center text-muted">Tidak ada rincian item.</td></tr>');
            $('#modalPrintReturn .modal-title').html('<i class="bi bi-eye me-2"></i>Detail Retur Penjualan');
            $('#modalPrintReturn').modal('show');
        }).fail(function() { alert('Gagal memuat detail retur.'); });
    });
    // -----------------------------------------------------------------------
    // 7. CETAK NOTA KREDIT / RETUR
    // -----------------------------------------------------------------------
    $(document).on('click', '.btn-print-return', function() {
        let id = $(this).data('id');
        $.get("{{ url('/inventori/penjualan/retur') }}/" + id + "/print-data", function(res) {
            let data = res.data;
            $('#printReturnNo').text(data.return_no);
            $('#printCustomerName').text(data.customer_name);
            $('#printInvoiceNo').text(data.invoice_no);
            $('#printReturnDate').text(data.return_date_formatted);
            $('#printWarehouseName').text(data.warehouse_name);
            $('#printReason').text(data.reason || '-');
            $('#printGrandTotal').text('Rp ' + formatRupiah(data.total));

            let html = '';
            data.items.forEach(function(item) {
                html += `<tr>
                    <td>${item.product_name}</td>
                    <td class="text-center">${item.qty} ${item.unit_name}</td>
                    <td class="text-center">${item.condition === 'good' ? '🟢 BAGUS' : '🔴 RUSAK'}</td>
                    <td class="text-end">Rp ${formatRupiah(item.unit_price)}</td>
                    <td class="text-end">Rp ${formatRupiah(item.return_value)}</td>
                </tr>`;
            });
            $('#printItemsBody').html(html);
            $('#modalPrintReturn').modal('show');
        });
    });

    function showReturnSuccessPopup(message, duration) {
        $('#returnSuccessPopup').remove();

        const popup = $(`
            <div id="returnSuccessPopup" style="
                position: fixed;
                inset: 0;
                z-index: 20000;
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgba(0,0,0,.35);
            ">
                <div style="
                    background: #fff;
                    border-radius: 12px;
                    padding: 24px 30px;
                    min-width: 280px;
                    max-width: calc(100% - 32px);
                    text-align: center;
                    box-shadow: 0 8px 30px rgba(0,0,0,.2);
                ">
                    <div class="text-success mb-2"><i class="bi bi-check-circle-fill" style="font-size: 42px;"></i></div>
                    <div class="fw-bold">${message}</div>
                </div>
            </div>
        `);

        $('body').append(popup);

        window.setTimeout(function() {
            popup.stop(true, true).fadeOut(200, function() {
                popup.remove();
            });
        }, duration);
    }

    // HELPER UTILITY FORMAT RUPIAH
    function formatRupiah(num) {
        return parseFloat(num || 0).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }
});
</script>
@endpush
