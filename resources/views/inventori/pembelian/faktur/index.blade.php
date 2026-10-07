@extends('layouts.app')

@section('title', 'Faktur Pembelian')

@section('content')
<div class="container-fluid px-0 py-0">
    <div class="py-2 mb-2">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h3 class="mb-1 fw-bold text-dark"><i class="bi bi-receipt-cutoff me-2 text-primary"></i>Faktur Pembelian</h3>
                <div class="text-muted small">Daftar transaksi pembelian dan faktur supplier</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="javascript:void(0)" onclick="openModalNonPo()" class="btn btn-primary btn-sm px-3 fw-semibold"><i class="bi bi-plus-circle me-1"></i>Pembelian Langsung</a>
                <a href="javascript:void(0)" onclick="openModalPo()" class="btn btn-primary btn-sm px-3 fw-semibold"><i class="bi bi-file-earmark-plus me-1"></i>Faktur dari PO</a>
                <a href="{{ route('inventori.pembelian.export-excel', request()->all()) }}" class="btn btn-success btn-sm px-3 fw-semibold"><i class="bi bi-file-earmark-excel me-1"></i>Export</a>
            </div>
        </div>

        <div class="bg-white border rounded p-2 mt-3">
            <form id="formFilter" class="row g-2 align-items-end">
                <div class="col-md-2"><label class="form-label mb-1 small fw-semibold">Mulai Tanggal</label><input type="date" id="filter-start-date" class="form-control form-control-sm" value="{{ $startDate }}"></div>
                <div class="col-md-2"><label class="form-label mb-1 small fw-semibold">Sampai Tanggal</label><input type="date" id="filter-end-date" class="form-control form-control-sm" value="{{ $endDate }}"></div>
                <div class="col-md-3"><label class="form-label mb-1 small fw-semibold">Unit Bisnis</label><select id="filter-bu" class="form-select form-select-sm"><option value="">Semua Unit Bisnis</option>@foreach($businessUnits as $bu)<option value="{{ $bu->id }}">{{ $bu->code }} - {{ $bu->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label mb-1 small fw-semibold">Supplier</label><select id="filter-supplier" class="form-select form-select-sm"><option value="">Semua Supplier</option>@foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
                <div class="col-md-2"><button type="button" class="btn btn-sm btn-primary w-100 fw-semibold" onclick="loadData(1)"><i class="bi bi-search me-1"></i>Tampilkan</button></div>
            </form>
        </div>
    </div>

    <!-- TABEL UTAMA -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover table-striped align-middle mb-0" id="tablePurchases">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 py-2 small text-uppercase">No. Faktur</th>
                            <th class="py-2">Tanggal</th>
                            <th class="py-2 small text-uppercase">Jalur</th>
                            <th class="py-2 small text-uppercase">Supplier</th>
                            <th class="py-2">Unit Bisnis</th>
                            <th class="py-2">Cara Bayar</th>
                            <th class="py-2">Jatuh Tempo</th>
                            <th class="text-end py-2">Total Netto (Rp)</th>
                            <th class="text-center py-2">Status</th>
                            <th class="text-center py-2" style="width: 150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyPurchases">
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                Memuat data faktur pembelian...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>
</div>

<!-- ================================================================================= -->
<!-- MODAL 1: PEMBELIAN LANGSUNG (NON-PO)                                             -->
<!-- ================================================================================= -->
<div class="modal fade" id="modalNonPo" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2 px-3">
                <h6 class="modal-title fw-bold mb-0"><i class="bi bi-cart-plus-fill me-2"></i>Pembelian Langsung (Non-PO)</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formNonPo" onsubmit="saveNonPo(event)">
                <input type="hidden" id="nonpo_id" name="id">
                <div class="modal-body bg-light p-2">
                    <!-- Header Info -->
                    <div class="card border-0 shadow-sm p-2 mb-2 bg-white">
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label extra-small fw-bold text-dark mb-1">Tanggal Pembelian <span class="text-danger">*</span></label>
                                <input type="date" class="form-control form-control-sm fw-semibold" id="nonpo_purchase_date" name="purchase_date" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label extra-small fw-bold text-dark mb-1">Unit Bisnis <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm fw-semibold" id="nonpo_business_unit_id" name="business_unit_id" required>
                                    @foreach($businessUnits as $bu)
                                        <option value="{{ $bu->id }}">{{ $bu->code }} - {{ $bu->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label extra-small fw-bold text-dark mb-0">No. Faktur Supplier</label>
                                <input type="text" class="form-control form-control-sm" id="nonpo_supplier_invoice_no" name="supplier_invoice_no" placeholder="Contoh: INV-SUP-9988">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label extra-small fw-bold text-dark mb-1">Supplier <span class="text-danger"></span></label>
                                <select class="form-select form-select-sm fw-bold border-primary" id="nonpo_supplier_id" name="supplier_id" required>
                                    <option value="">-- Pilih Vendor / Supplier --</option>
                                    @foreach($suppliers as $s)
                                        <option value="{{ $s->id }}">{{ $s->code }} - {{ $s->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            
                        </div>

                        <div class="row g-2 mt-1">
                            
                        </div>
                    </div>

                    <!-- Item List -->
                    <div class="card border-0 shadow-sm p-2 mb-2 bg-white">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h6 class="fw-bold text-primary mb-0 small"><i class="bi bi-box-seam-fill me-1"></i>Rincian Barang Pembelian</h6>
                            <button type="button" class="btn btn-xs btn-success fw-bold px-2 py-1 shadow-sm" onclick="addNonPoRow()">
                                <i class="bi bi-plus-lg me-1"></i> Tambah Baris Barang
                            </button>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover table-bordered align-middle mb-0" id="tableNonPoItems">
                                <thead class="table-dark extra-small">
                                    <tr>
                                        <th class="text-center" style="width: 35px;">#</th>
                                        <th style="min-width: 280px;">Pilih Produk / Barang</th>
                                        <th class="text-center" style="width: 80px;">Satuan</th>
                                        <th style="width: 120px;">Qty</th>
                                        <th style="width: 160px;">Harga Beli / Unit (Rp)</th>
                                        <th class="text-end" style="width: 160px;">Subtotal (Rp)</th>
                                        <th class="text-center" style="width: 35px;"><i class="bi bi-trash"></i></th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyNonPoItems"></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Summary Total (Ringkas & Tanpa PPN) -->
                    <div class="row justify-content-end g-2">
                        <div class="col-md-8">
                        <label class="form-label">Memo</label>
                            <textarea id="memoInput" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm p-2 bg-white">
                                <div class="d-flex justify-content-between mb-1 small">
                                    <span class="text-muted fw-semibold">Subtotal Items:</span>
                                    <strong id="nonpo_display_subtotal">Rp 0</strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-1 small">
                                    <span class="text-muted fw-semibold">Diskon Nota (Rp):</span>
                                    <input type="number" class="form-control form-control-sm text-end w-50 fw-bold py-0" id="nonpo_document_discount" name="document_discount" value="0" min="0" oninput="calculateTotalsNonPo()">
                                </div>
                                <hr class="my-1">
                                <div class="d-flex justify-content-between text-primary fw-bold mb-0">
                                    <span>GRAND TOTAL:</span>
                                    <span id="nonpo_display_grand_total" class="fs-6">Rp 0</span>
                                </div>
                                <label class="form-label">Cara Bayar</label>
                                <select id="paymentMethod" class="form-select">
                            <option value="Tunai">Tunai</option>
                            <option value="Transfer">Transfer</option>
                            <option value="QRIS">QRIS</option>
                            <option value="Kredit / Bon">Kredit / Bon</option>
                        </select>
                                <div id="dueDate" class="col-md-6 d-none">
                        <label class="form-label">Jatuh Tempo</label>
                        <input type="date" class="form-control" disabled="">
                    </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white py-2 px-3">
                    <button type="button" class="btn btn-sm btn-secondary fw-semibold px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold px-4">
                        <i class="bi bi-save me-1"></i> Simpan Sebagai DRAFT
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================================================================================= -->
<!-- MODAL 2: PEMBELIAN BERDASARKAN PO APPROVED                                        -->
<!-- ================================================================================= -->
<div class="modal fade" id="modalPo" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white py-2 px-3">
                <h6 class="modal-title fw-bold mb-0"><i class="bi bi-file-earmark-check-fill me-2"></i>Faktur Pembelian Berdasarkan PO</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formPo" onsubmit="savePo(event)">
                <input type="hidden" id="po_purchase_id" name="id">
                <input type="hidden" id="po_business_unit_id" name="business_unit_id">
                <input type="hidden" id="po_supplier_id" name="supplier_id">

                <div class="modal-body bg-light p-2">
                    <!-- Step 1: Select PO Header -->
                    <div class="card border-0 shadow-sm p-2 mb-2 bg-white">
                        <div class="row g-2 align-items-center">
                            <div class="col-md-4">
                                <label class="form-label extra-small fw-bold text-dark mb-1">Pilih Dokumen PO Approved <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm fw-bold border-success text-success" id="po_purchase_order_id" name="purchase_order_id" onchange="onPoSelect(this.value)" required>
                                    <option value="">-- Pilih Nomor PO Approved --</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label extra-small fw-bold text-muted mb-1">Vendor / Supplier (Auto PO)</label>
                                <input type="text" class="form-control form-control-sm bg-light fw-bold" id="po_supplier_name" readonly placeholder="Terisi otomatis dari PO...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label extra-small fw-bold text-muted mb-1">Unit Bisnis</label>
                                <input type="text" class="form-control form-control-sm bg-light fw-bold text-primary" id="po_bu_warehouse_display" readonly placeholder="Terisi otomatis dari PO...">
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Invoice Info -->
                    <div class="card border-0 shadow-sm p-2 mb-2 bg-white">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label extra-small fw-bold text-dark mb-1">Tanggal Pembelian <span class="text-danger">*</span></label>
                                <input type="date" class="form-control form-control-sm fw-semibold" id="po_purchase_date" name="purchase_date" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label extra-small fw-bold text-dark mb-1">No. Faktur Supplier</label>
                                <input type="text" class="form-control form-control-sm fw-semibold" id="po_supplier_invoice_no" name="supplier_invoice_no" placeholder="Contoh: INV-SUP-9988">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label extra-small fw-bold text-muted mb-1">Status Penerimaan Fisik Barang</label>
                                <div class="alert alert-info py-1 px-2 mb-0 extra-small fw-bold text-dark border-0 bg-info-subtle">
                                    <i class="bi bi-info-circle-fill me-1 text-primary"></i> Fisik barang diterima via Penerimaan.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Table Items dari PO -->
                    <div class="card border-0 shadow-sm p-2 mb-2 bg-white">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <h6 class="fw-bold text-success mb-0 small"><i class="bi bi-list-check me-1"></i>Rincian Barang & Tagihan Faktur dari PO</h6>
                            <span class="badge bg-success-subtle text-success fw-bold border border-success-subtle px-2 py-0 extra-small">Mode Tagihan PO</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover table-bordered align-middle mb-0" id="tablePoItems">
                                <thead class="table-dark extra-small">
                                    <tr>
                                        <th class="text-center" style="width: 35px;">#</th>
                                        <th style="width: 15%;">Kode Barang</th>
                                        <th style="width: 30%;">Nama Produk</th>
                                        <th class="text-center" style="width: 80px;">Satuan</th>
                                        <th style="width: 120px;">Qty Tagihan</th>
                                        <th style="width: 160px;">Harga Beli / Unit (Rp)</th>
                                        <th class="text-end" style="width: 160px;">Subtotal (Rp)</th>
                                        <th class="text-center" style="width: 35px;"><i class="bi bi-trash"></i></th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyPoItems">
                                    <tr>
                                        <td colspan="8" class="text-center py-3 text-muted extra-small">
                                            Silakan pilih Nomor PO pada dropdown di atas.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Summary Totals (Ringkas & Tanpa PPN) -->
                    <div class="row justify-content-end g-2">
                         <div class="col-md-8">
                        <label class="form-label">Memo</label>
                            <textarea id="memoInput" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm p-2 bg-white">
                                <div class="d-flex justify-content-between mb-1 small">
                                    <span class="text-muted fw-semibold">Subtotal Items:</span>
                                    <strong id="po_display_subtotal">Rp 0</strong>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-1 small">
                                    <span class="text-muted fw-semibold">Diskon Nota (Rp):</span>
                                    <input type="number" class="form-control form-control-sm text-end w-50 fw-bold py-0" id="po_document_discount" name="document_discount" value="0" min="0" oninput="calculateTotalsPo()">
                                </div>
                                <hr class="my-1">
                                <div class="d-flex justify-content-between text-success fw-bold mb-0">
                                    <span>GRAND TOTAL:</span>
                                    <span id="po_display_grand_total" class="fs-6">Rp 0</span>
                                </div>
                                 <label class="form-label">Cara Bayar</label>
                                <select id="paymentMethod" class="form-select">
                            <option value="Tunai">Tunai</option>
                            <option value="Transfer">Transfer</option>
                            <option value="QRIS">QRIS</option>
                            <option value="Kredit / Bon">Kredit / Bon</option>
                        </select>
                                <div id="dueDate" class="col-md-6 d-none">
                        <label class="form-label">Jatuh Tempo</label>
                        <input type="date" class="form-control" disabled="">
                    </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white py-2 px-3">
                    <button type="button" class="btn btn-sm btn-secondary fw-semibold px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-success fw-bold px-4">
                        <i class="bi bi-save me-1"></i> Simpan Sebagai DRAFT
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ================================================================================= -->
<!-- MODAL RETUR PEMBELIAN (RB)                                                        -->
<!-- ================================================================================= -->
<div class="modal fade" id="modalReturn" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning py-2 px-3">
                <h6 class="modal-title fw-bold text-dark mb-0"><i class="bi bi-arrow-return-left me-2"></i>Retur Pembelian (RB)</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formReturn" onsubmit="saveReturn(event)">
                <input type="hidden" id="ret_purchase_id" name="purchase_id">
                <div class="modal-body bg-light p-2">
                    <div class="card border-0 shadow-sm p-2 mb-2 bg-white">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label extra-small fw-bold text-muted mb-0">No. Ref. Faktur (FB)</label>
                                <input type="text" class="form-control form-control-sm bg-light fw-bold text-primary" id="ret_purchase_no" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label extra-small fw-bold text-muted mb-0">Vendor / Supplier</label>
                                <input type="text" class="form-control form-control-sm bg-light fw-bold" id="ret_supplier_name" readonly>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label extra-small fw-bold text-dark mb-0">Alasan Utama Retur <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="ret_reason" name="reason" placeholder="Contoh: Barang cacat / Rusak" required>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm p-2 bg-white">
                        <h6 class="fw-bold text-dark mb-1 small">Pilih Item & Input Qty Retur</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered table-hover align-middle mb-0">
                                <thead class="table-secondary extra-small">
                                    <tr>
                                        <th>Nama Produk</th>
                                        <th class="text-center">Qty Beli</th>
                                        <th class="text-center text-danger">Sisa Max Retur</th>
                                        <th style="width: 140px;">Qty Retur Sekarang</th>
                                        <th class="text-end">Harga Beli (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyReturnItems"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white py-2 px-3">
                    <button type="button" class="btn btn-sm btn-secondary fw-semibold px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-sm btn-warning fw-bold text-dark px-4">
                        <i class="bi bi-check-circle-fill me-1"></i> Memproses Retur (RB)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.extra-small { font-size: 0.78rem; }
.btn-xs { padding: 0.15rem 0.4rem; font-size: 0.75rem; }
</style>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let modalNonPo, modalPo, modalReturn;
let allProductsList = [];

document.addEventListener("DOMContentLoaded", function() {
    modalNonPo  = new bootstrap.Modal(document.getElementById('modalNonPo'));
    modalPo     = new bootstrap.Modal(document.getElementById('modalPo'));
    modalReturn = new bootstrap.Modal(document.getElementById('modalReturn'));

    loadData(1);
    preloadProducts();
    preloadApprovedPOs();
});

function preloadProducts() {
    fetch('/inventori/pembelian/faktur/lookup/product?q=')
    .then(res => res.json())
    .then(res => { allProductsList = res.data || []; });
}

function preloadApprovedPOs() {
    fetch('/inventori/pembelian/faktur/lookup/po?q=')
    .then(res => res.json())
    .then(res => {
        const select = document.getElementById('po_purchase_order_id');
        let html = '<option value="">-- Pilih Nomor PO Approved --</option>';
        if(res.data) {
            res.data.forEach(po => {
                html += `<option value="${po.id}">${po.po_no} - ${po.supplier_name} (${po.warehouse_name || 'Gudang'})</option>`;
            });
        }
        select.innerHTML = html;
    });
}

function showToast(icon, message) {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 2500,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });
    Toast.fire({ icon: icon, title: message });
}

const purchaseTable = new DataTable('#tablePurchases', {
    processing: true,
    serverSide: true,
    pageLength: 15,
    lengthMenu: [[15, 25, 50, 100], [15, 25, 50, 100]],
    order: [[1, 'desc']],
    language: {
        lengthMenu: 'Tampilkan _MENU_ data per halaman',
        search: 'Cari:',
        info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
        infoEmpty: 'Tidak ada data',
        infoFiltered: '(disaring dari _MAX_ data)',
        zeroRecords: 'Data tidak ditemukan',
        emptyTable: 'Belum ada data',
        paginate: { first: '<<', last: '>>', next: '>', previous: '<' },
        processing: 'Memuat...'
    },
    ajax: {
        url: '{{ route('inventori.pembelian.data') }}',
        type: 'GET',
        data: function (d) {
            d.start_date = document.getElementById('filter-start-date').value;
            d.end_date = document.getElementById('filter-end-date').value;
            d.business_unit_id = document.getElementById('filter-bu').value;
            d.supplier_id = document.getElementById('filter-supplier').value;
        },
        dataSrc: 'data'
    },
    columns: [
        { data: 'purchase_no', className: 'ps-3 fw-bold text-primary' },
        { data: 'formatted_date' },
        { data: 'source_type', render: data => data === 'po' ? '<span class="badge bg-info text-dark border">PO</span>' : '<span class="badge bg-secondary">DIRECT NON-PO</span>' },
        { data: 'supplier_name', render: data => '<strong>' + (data || '-') + '</strong>' },
        { data: 'bu_code', render: data => '<span class="badge bg-light text-dark border">' + (data || 'BU') + '</span>' },
        { data: 'formatted_grand', className: 'text-end fw-bold' },
        { data: 'status', className: 'text-center', render: data => data === 'draft' ? '<span class="badge bg-warning text-dark">DRAFT</span>' : '<span class="badge bg-success">APPROVED</span>' },
        { data: null, orderable: false, searchable: false, className: 'text-center', render: (data, type, row) => {
            const draft = row.status === 'draft';
            return '<div class="dropdown">' +
                '<button class="btn btn-sm btn-light border dropdown-toggle fw-semibold py-0" type="button" data-bs-toggle="dropdown">⚙️ Aksi</button>' +
                '<ul class="dropdown-menu dropdown-menu-end shadow border-0 py-1">' +
                '<li><a class="dropdown-item py-1 small" href="javascript:void(0)" onclick="printFaktur(' + row.id + ')"><i class="bi bi-printer me-2 text-primary"></i>Cetak Faktur Standar</a></li>' +
                (draft ? '<li><a class="dropdown-item py-1 text-success fw-bold small" href="javascript:void(0)" onclick="approvePurchase(' + row.id + ')"><i class="bi bi-check2-circle me-2"></i>Approve Faktur</a></li>' : '') +
                (draft ? '<li><a class="dropdown-item py-1 small" href="javascript:void(0)" onclick="openEditModal(' + row.id + ', \'' + row.source_type + '\')"><i class="bi bi-pencil me-2 text-warning"></i>Edit Nota</a></li>' : '') +
                (!draft ? '<li><a class="dropdown-item py-1 text-warning fw-bold small" href="javascript:void(0)" onclick="openModalReturn(' + row.id + ')"><i class="bi bi-arrow-return-left me-2"></i>Retur Pembelian (RB)</a></li>' : '') +
                '<li><hr class="dropdown-divider my-1"></li>' +
                '<li><a class="dropdown-item py-1 text-danger small" href="javascript:void(0)" onclick="deletePurchase(' + row.id + ')"><i class="bi bi-trash me-2"></i>Hapus Nota</a></li>' +
                '</ul></div>';
        }}
    ]
});

function loadData(page = 1) {
    purchaseTable.ajax.reload();
}


function openModalNonPo() {
    document.getElementById('formNonPo').reset();
    document.getElementById('nonpo_id').value = '';
    document.getElementById('tbodyNonPoItems').innerHTML = '';
    addNonPoRow();
    calculateTotalsNonPo();
    modalNonPo.show();
}

function openModalPo() {
    document.getElementById('formPo').reset();
    document.getElementById('po_purchase_id').value = '';
    document.getElementById('tbodyPoItems').innerHTML = `<tr><td colspan="8" class="text-center py-3 text-muted extra-small">Silakan pilih Nomor PO pada dropdown di atas.</td></tr>`;
    preloadApprovedPOs();
    calculateTotalsPo();
    modalPo.show();
}

// 1. NON-PO: DROPDOWN TABLE BARANG
function addNonPoRow(selectedProductId = '', selectedQty = 1, selectedCost = 0, selectedUnitId = '', selectedUnitName = '') {
    const tbody = document.getElementById('tbodyNonPoItems');
    const rowId = Date.now() + Math.random().toString(36).substring(2, 6);

    const row = document.createElement('tr');
    row.id = `nonpo_row_${rowId}`;
    row.className = 'align-middle';

    let optionsHtml = '<option value="">-- Pilih Barang / Produk --</option>';
    allProductsList.forEach(p => {
        const isSel = (p.id == selectedProductId) ? 'selected' : '';
        optionsHtml += `<option value="${p.id}" ${isSel} data-code="${p.code || ''}" data-unit="${p.unit_name || 'PCS'}" data-unitid="${p.unit_id || ''}" data-cost="${p.purchase_cost || 0}">${p.code ? '['+p.code+'] ' : ''}${p.name}</option>`;
    });

    row.innerHTML = `
        <td class="text-center fw-semibold text-muted small row-num"></td>
        <td style="min-width: 280px;">
            <select class="form-select form-select-sm fw-semibold border-primary product-select py-0" name="items[${rowId}][product_id]" onchange="onProductSelect(this, '${rowId}')" required>
                ${optionsHtml}
            </select>
            <input type="hidden" name="items[${rowId}][unit_id]" id="unit_id_${rowId}" value="${selectedUnitId}">
        </td>
        <td class="text-center">
            <span class="badge bg-info text-dark fw-semibold px-2 py-1" id="unit_badge_${rowId}">${selectedUnitName || '-'}</span>
        </td>
        <td style="width: 120px;">
            <div class="input-group input-group-sm">
                <input type="number" step="0.001" class="form-control text-end fw-semibold py-0" name="items[${rowId}][qty]" value="${selectedQty}" min="0.001" oninput="calculateTotalsNonPo()" required>
                <span class="input-group-text bg-light text-muted px-1 extra-small" id="qty_unit_${rowId}">${selectedUnitName || 'PCS'}</span>
            </div>
        </td>
        <td style="width: 160px;">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light fw-bold text-muted extra-small">Rp</span>
                <input type="number" step="100" class="form-control text-end fw-bold text-primary py-0" name="items[${rowId}][unit_cost]" id="cost_${rowId}" value="${selectedCost}" min="0" oninput="calculateTotalsNonPo()" required>
            </div>
        </td>
        <td class="text-end fw-bold text-primary small" id="nonpo_subtotal_${rowId}">Rp 0</td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-outline-danger border-0 p-1" onclick="removeNonPoRow('${rowId}')"><i class="bi bi-trash-fill"></i></button>
        </td>
    `;
    tbody.appendChild(row);
    reindexNonPoNumbers();
    calculateTotalsNonPo();
}

function onProductSelect(selectElem, rowId) {
    const selectedOpt = selectElem.options[selectElem.selectedIndex];
    if(!selectedOpt || !selectedOpt.value) {
        document.getElementById(`unit_id_${rowId}`).value = '';
        document.getElementById(`unit_badge_${rowId}`).innerText = '-';
        document.getElementById(`qty_unit_${rowId}`).innerText = 'PCS';
        document.getElementById(`cost_${rowId}`).value = 0;
    } else {
        const unitId = selectedOpt.getAttribute('data-unitid') || '';
        const unitName = selectedOpt.getAttribute('data-unit') || 'PCS';
        const cost = parseFloat(selectedOpt.getAttribute('data-cost')) || 0;

        document.getElementById(`unit_id_${rowId}`).value = unitId;
        document.getElementById(`unit_badge_${rowId}`).innerText = unitName;
        document.getElementById(`qty_unit_${rowId}`).innerText = unitName;
        document.getElementById(`cost_${rowId}`).value = cost;
    }
    calculateTotalsNonPo();
}

function removeNonPoRow(rowId) {
    const row = document.getElementById(`nonpo_row_${rowId}`);
    if(row) {
        row.remove();
        reindexNonPoNumbers();
        calculateTotalsNonPo();
    }
}

function reindexNonPoNumbers() {
    const rows = document.querySelectorAll('#tbodyNonPoItems tr');
    rows.forEach((r, idx) => {
        const numCell = r.querySelector('.row-num');
        if(numCell) numCell.innerText = idx + 1;
    });
}

function calculateTotalsNonPo() {
    let subtotal = 0;
    const rows = document.querySelectorAll('#tbodyNonPoItems tr');
    rows.forEach(row => {
        const qtyInput = row.querySelector('input[name*="[qty]"]');
        const costInput = row.querySelector('input[name*="[unit_cost]"]');
        if(qtyInput && costInput) {
            const qty = parseFloat(qtyInput.value) || 0;
            const cost = parseFloat(costInput.value) || 0;
            const line = qty * cost;
            subtotal += line;

            const subCell = row.querySelector('td[id^="nonpo_subtotal_"]');
            if(subCell) subCell.innerText = `Rp ${line.toLocaleString('id-ID')}`;
        }
    });

    const discount = parseFloat(document.getElementById('nonpo_document_discount').value) || 0;
    const grand = Math.max(0, subtotal - discount);

    document.getElementById('nonpo_display_subtotal').innerText = `Rp ${subtotal.toLocaleString('id-ID')}`;
    document.getElementById('nonpo_display_grand_total').innerText = `Rp ${grand.toLocaleString('id-ID')}`;
}

// 2. PO APPROVED: DROPDOWN SELECT PO
function onPoSelect(poId) {
    if(!poId) {
        document.getElementById('po_business_unit_id').value = '';
        document.getElementById('po_supplier_id').value = '';
        document.getElementById('po_supplier_name').value = '';
        document.getElementById('po_bu_warehouse_display').value = '';
        document.getElementById('tbodyPoItems').innerHTML = `<tr><td colspan="8" class="text-center py-3 text-muted extra-small">Silakan pilih Nomor PO pada dropdown di atas.</td></tr>`;
        calculateTotalsPo();
        return;
    }

    fetch(`/inventori/pembelian/faktur/lookup/po-items/${poId}`)
    .then(res => res.json())
    .then(res => {
        if(res.status === 'success') {
            const po = res.data.po;
            const items = res.data.items;

            document.getElementById('po_business_unit_id').value = po.business_unit_id;
            document.getElementById('po_supplier_id').value = po.supplier_id;
            document.getElementById('po_supplier_name').value = po.supplier_name;
            document.getElementById('po_bu_warehouse_display').value = `${po.bu_code} - ${po.warehouse_name}`;

            let html = '';
            items.forEach((item, idx) => {
                const sub = item.qty * item.unit_cost;
                const unitName = item.unit_name || 'PCS';
                html += `
                <tr id="po_row_${idx}" class="align-middle">
                    <td class="text-center fw-semibold text-muted small">${idx + 1}</td>
                    <td><span class="badge bg-light text-dark border font-monospace px-2 py-1">${item.product_code || '-'}</span></td>
                    <td>
                        <strong class="text-dark d-block">${item.product_name}</strong>
                        <input type="hidden" name="items[${idx}][product_id]" value="${item.product_id}">
                        <input type="hidden" name="items[${idx}][unit_id]" value="${item.unit_id || ''}">
                    </td>
                    <td class="text-center"><span class="badge bg-info text-dark fw-semibold px-2 py-1">${unitName}</span></td>
                    <td style="width: 120px;">
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.001" class="form-control text-end fw-semibold py-0" name="items[${idx}][qty]" value="${item.qty}" min="0.001" oninput="calculateTotalsPo()" required>
                            <span class="input-group-text bg-light text-muted px-1 extra-small">${unitName}</span>
                        </div>
                    </td>
                    <td style="width: 160px;">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light fw-bold text-muted extra-small">Rp</span>
                            <input type="number" step="100" class="form-control text-end fw-bold text-primary py-0" name="items[${idx}][unit_cost]" value="${item.unit_cost}" min="0" oninput="calculateTotalsPo()" required>
                        </div>
                    </td>
                    <td class="text-end fw-bold text-success small" id="po_subtotal_${idx}">Rp ${sub.toLocaleString('id-ID')}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-danger border-0 p-1" onclick="removePoRow(this)"><i class="bi bi-trash-fill"></i></button>
                    </td>
                </tr>`;
            });

            document.getElementById('tbodyPoItems').innerHTML = html;
            calculateTotalsPo();
        }
    });
}

function removePoRow(btn) {
    const row = btn.closest('tr');
    if(row) {
        row.remove();
        calculateTotalsPo();
        const rows = document.querySelectorAll('#tbodyPoItems tr');
        rows.forEach((r, idx) => {
            const numCell = r.querySelector('td:first-child');
            if(numCell) numCell.innerText = idx + 1;
        });
    }
}

function calculateTotalsPo() {
    let subtotal = 0;
    const rows = document.querySelectorAll('#tbodyPoItems tr');
    rows.forEach((row, idx) => {
        const qtyInput = row.querySelector('input[name*="[qty]"]');
        const costInput = row.querySelector('input[name*="[unit_cost]"]');
        if(qtyInput && costInput) {
            const qty = parseFloat(qtyInput.value) || 0;
            const cost = parseFloat(costInput.value) || 0;
            const line = qty * cost;
            subtotal += line;

            const subCell = row.querySelector('td[id^="po_subtotal_"]');
            if(subCell) subCell.innerText = `Rp ${line.toLocaleString('id-ID')}`;
        }
    });

    const discount = parseFloat(document.getElementById('po_document_discount').value) || 0;
    const grand = Math.max(0, subtotal - discount);

    document.getElementById('po_display_subtotal').innerText = `Rp ${subtotal.toLocaleString('id-ID')}`;
    document.getElementById('po_display_grand_total').innerText = `Rp ${grand.toLocaleString('id-ID')}`;
}

// SAVE ACTIONS
function saveNonPo(e) {
    e.preventDefault();
    const rows = document.querySelectorAll('#tbodyNonPoItems tr');
    if(rows.length === 0) {
        showToast('warning', 'Pilih minimal 1 barang pembelian!');
        return;
    }

    const formData = new FormData(e.target);
    const payload = {};
    formData.forEach((value, key) => { if(!key.includes('[')) payload[key] = value; });

    payload.goods_received = document.getElementById('nonpo_goods_received').checked ? 1 : 0;

    payload.items = [];
    let valid = true;
    rows.forEach(row => {
        const prodSelect = row.querySelector('select[name*="[product_id]"]');
        if(!prodSelect || !prodSelect.value) {
            valid = false;
        } else {
            payload.items.push({
                product_id: prodSelect.value,
                unit_id: row.querySelector('input[name*="[unit_id]"]').value,
                qty: row.querySelector('input[name*="[qty]"]').value,
                unit_cost: row.querySelector('input[name*="[unit_cost]"]').value
            });
        }
    });

    if(!valid) {
        showToast('warning', 'Pilih barang pada setiap baris terlebih dahulu!');
        return;
    }

    const id = document.getElementById('nonpo_id').value;
    const url = id ? `/inventori/pembelian/faktur/${id}` : '/inventori/pembelian/faktur/store-non-po';
    const method = id ? 'PUT' : 'POST';

    fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(res => {
        if(res.status === 'success') {
            modalNonPo.hide();
            showToast('success', res.message);
            loadData(1);
        } else {
            showToast('error', res.message);
        }
    });
}

function savePo(e) {
    e.preventDefault();
    const poId = document.getElementById('po_purchase_order_id').value;
    if(!poId) {
        showToast('warning', 'Pilih dokumen PO Approved terlebih dahulu!');
        return;
    }

    const formData = new FormData(e.target);
    const payload = {};
    formData.forEach((value, key) => { if(!key.includes('[')) payload[key] = value; });

    payload.items = [];
    const rows = document.querySelectorAll('#tbodyPoItems tr');
    rows.forEach(row => {
        const prodId = row.querySelector('input[name*="[product_id]"]');
        if(prodId) {
            payload.items.push({
                product_id: prodId.value,
                unit_id: row.querySelector('input[name*="[unit_id]"]').value,
                qty: row.querySelector('input[name*="[qty]"]').value,
                unit_cost: row.querySelector('input[name*="[unit_cost]"]').value
            });
        }
    });

    if(payload.items.length === 0) {
        showToast('warning', 'Daftar barang tidak boleh kosong!');
        return;
    }

    const id = document.getElementById('po_purchase_id').value;
    const url = id ? `/inventori/pembelian/faktur/${id}` : '/inventori/pembelian/faktur/store-po';
    const method = id ? 'PUT' : 'POST';

    fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(res => {
        if(res.status === 'success') {
            modalPo.hide();
            showToast('success', res.message);
            loadData(1);
        } else {
            showToast('error', res.message);
        }
    });
}

function openEditModal(id, sourceType) {
    fetch(`/inventori/pembelian/faktur/${id}`)
    .then(res => res.json())
    .then(res => {
        if(res.status === 'success') {
            const p = res.data.purchase;
            const items = res.data.items;

            if(sourceType === 'po') {
                document.getElementById('po_purchase_id').value = p.id;
                document.getElementById('po_purchase_order_id').value = p.purchase_order_id;
                document.getElementById('po_business_unit_id').value = p.business_unit_id;
                document.getElementById('po_supplier_id').value = p.supplier_id;
                document.getElementById('po_supplier_name').value = p.supplier_name;
                document.getElementById('po_bu_warehouse_display').value = `${p.bu_code} - ${p.bu_name}`;
                document.getElementById('po_purchase_date').value = p.purchase_date ? p.purchase_date.substring(0, 10) : '';
                document.getElementById('po_supplier_invoice_no').value = p.supplier_invoice_no || '';
                document.getElementById('po_document_discount').value = p.discount || 0;

                let html = '';
                items.forEach((item, idx) => {
                    const sub = item.qty * item.unit_cost;
                    const unitName = item.unit_name || 'PCS';
                    html += `
                    <tr id="po_row_${idx}" class="align-middle">
                        <td class="text-center fw-semibold text-muted small">${idx + 1}</td>
                        <td><span class="badge bg-light text-dark border font-monospace px-2 py-1">${item.product_code || '-'}</span></td>
                        <td>
                            <strong class="text-dark d-block">${item.product_name}</strong>
                            <input type="hidden" name="items[${idx}][product_id]" value="${item.product_id}">
                            <input type="hidden" name="items[${idx}][unit_id]" value="${item.unit_id || ''}">
                        </td>
                        <td class="text-center"><span class="badge bg-info text-dark fw-semibold px-2 py-1">${unitName}</span></td>
                        <td style="width: 120px;">
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.001" class="form-control text-end fw-semibold py-0" name="items[${idx}][qty]" value="${item.qty}" min="0.001" oninput="calculateTotalsPo()" required>
                                <span class="input-group-text bg-light text-muted px-1 extra-small">${unitName}</span>
                            </div>
                        </td>
                        <td style="width: 160px;">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light fw-bold text-muted extra-small">Rp</span>
                                <input type="number" step="100" class="form-control text-end fw-bold text-primary py-0" name="items[${idx}][unit_cost]" value="${item.unit_cost}" min="0" oninput="calculateTotalsPo()" required>
                            </div>
                        </td>
                        <td class="text-end fw-bold text-success small" id="po_subtotal_${idx}">Rp ${sub.toLocaleString('id-ID')}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-danger border-0 p-1" onclick="removePoRow(this)"><i class="bi bi-trash-fill"></i></button>
                        </td>
                    </tr>`;
                });
                document.getElementById('tbodyPoItems').innerHTML = html;
                calculateTotalsPo();
                modalPo.show();
            } else {
                document.getElementById('nonpo_id').value = p.id;
                document.getElementById('nonpo_business_unit_id').value = p.business_unit_id;
                document.getElementById('nonpo_supplier_id').value = p.supplier_id;
                document.getElementById('nonpo_purchase_date').value = p.purchase_date ? p.purchase_date.substring(0, 10) : '';
                document.getElementById('nonpo_supplier_invoice_no').value = p.supplier_invoice_no || '';
                document.getElementById('nonpo_goods_received').checked = (p.goods_received == 1);
                document.getElementById('nonpo_document_discount').value = p.discount || 0;

                document.getElementById('tbodyNonPoItems').innerHTML = '';
                items.forEach(item => {
                    addNonPoRow(item.product_id, item.qty, item.unit_cost, item.unit_id, item.unit_name);
                });
                modalNonPo.show();
            }
        }
    });
}

function approvePurchase(id) {
    Swal.fire({
        title: 'Approve Faktur Pembelian?',
        text: 'Setelah di-approve, status nota menjadi APPROVED dan resmi terikat di sistem!',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Approve Now!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/inventori/pembelian/faktur/${id}/approve`, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(res => {
                if(res.status === 'success') {
                    showToast('success', res.message);
                    loadData(1);
                } else {
                    showToast('error', res.message);
                }
            });
        }
    });
}

function deletePurchase(id) {
    Swal.fire({
        title: 'Hapus Faktur Pembelian?',
        text: 'Tindakan ini memerlukan wewenang Manager/Admin!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Hapus!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(`/inventori/pembelian/faktur/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(res => {
                if(res.status === 'success') {
                    showToast('success', res.message);
                    loadData(1);
                } else {
                    showToast('error', res.message);
                }
            });
        }
    });
}

function openModalReturn(id) {
    fetch(`/inventori/pembelian/faktur/${id}`)
    .then(res => res.json())
    .then(res => {
        if(res.status === 'success') {
            const p = res.data.purchase;
            const items = res.data.items;

            document.getElementById('ret_purchase_id').value = p.id;
            document.getElementById('ret_purchase_no').value = p.purchase_no;
            document.getElementById('ret_supplier_name').value = p.supplier_name;

            let html = '';
            items.forEach(item => {
                html += `
                <tr class="align-middle">
                    <td><strong>${item.product_name}</strong></td>
                    <td class="text-center">${item.qty}</td>
                    <td class="text-center fw-bold text-danger">${item.max_returnable}</td>
                    <td>
                        <input type="number" step="0.001" class="form-control form-control-sm text-end fw-bold py-0" name="return_items[${item.id}][qty]" max="${item.max_returnable}" min="0" value="0">
                        <input type="hidden" name="return_items[${item.id}][id]" value="${item.id}">
                    </td>
                    <td class="text-end fw-semibold">Rp ${(parseFloat(item.unit_cost) || 0).toLocaleString('id-ID')}</td>
                </tr>`;
            });
            document.getElementById('tbodyReturnItems').innerHTML = html;
            modalReturn.show();
        }
    });
}

function saveReturn(e) {
    e.preventDefault();
    const purchaseId = document.getElementById('ret_purchase_id').value;
    const reason = document.getElementById('ret_reason').value;

    const returnItems = [];
    const rows = document.querySelectorAll('#tbodyReturnItems tr');
    rows.forEach(row => {
        const itemId = row.querySelector('input[name*="[id]"]')?.value;
        const qty = row.querySelector('input[name*="[qty]"]')?.value;
        if(itemId && parseFloat(qty) > 0) {
            returnItems.push({ id: itemId, qty: qty });
        }
    });

    if(returnItems.length === 0) {
        showToast('warning', 'Pilih minimal 1 item untuk diretur!');
        return;
    }

    fetch('/inventori/pembelian/faktur/store-return', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ purchase_id: purchaseId, reason: reason, return_items: returnItems })
    })
    .then(res => res.json())
    .then(res => {
        if(res.status === 'success') {
            modalReturn.hide();
            showToast('success', res.message);
            loadData(1);
        } else {
            showToast('error', res.message);
        }
    });
}

function printFaktur(id) { window.open(`/inventori/pembelian/faktur/${id}/print`, '_blank'); }
function exportExcel() {
    const startDate = document.getElementById('filter_start_date').value;
    const endDate   = document.getElementById('filter_end_date').value;
    const status    = document.getElementById('filter_status').value;
    const supplierId= document.getElementById('filter_supplier_id').value;

    window.open(`/inventori/pembelian/faktur/export-excel?start_date=${startDate}&end_date=${endDate}&status=${status}&supplier_id=${supplierId}`, '_blank');
}
</script>
@endpush
