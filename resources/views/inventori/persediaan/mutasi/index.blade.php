@extends('layouts.app')

@section('content')
<div class="container-fluid px-0 py-0">

    <!-- Header & Action Buttons -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold text-dark">Mutasi Antar Gudang</h3>
            <div class="text-secondary small">Transfer Stok Antar Gudang (Gudang Pengirim & Penerima)</div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm px-3 fw-semibold shadow-sm" data-bs-toggle="modal" data-bs-target="#modal-mutasi" id="btn-tambah-mutasi">
                <i class="bi bi-plus-circle me-1"></i> + Buat Baru
            </button>
            <button type="button" id="btn-export-excel" class="btn btn-success btn-sm px-3 fw-semibold shadow-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> Export
            </button>
        </div>
    </div>

    <!-- Notifikasi: inline agar tidak menumpuk modal atau perlu klik OK dua kali -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show py-2 mb-3" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>Berhasil.</strong> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show py-2 mb-3" role="alert">
            <i class="bi bi-exclamation-circle-fill me-2"></i>
            <strong>Gagal.</strong> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    @endif

    <!-- Filter Form -->
    <div class="card shadow-sm border-0 mb-2">
        <div class="card-body p-2">
            <form id="form-filter-mutasi" method="GET" action="{{ route('inventori.transfer.index') }}" class="d-flex flex-wrap gap-2 align-items-end">

                <div style="width: 145px;">
                    <label class="form-label mb-1 small fw-bold">Mulai Tanggal</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
                </div>

                <div style="width: 145px;">
                    <label class="form-label mb-1 small fw-bold">Sampai Tanggal</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
                </div>

                <div style="width: 180px;">
                    <label class="form-label mb-1 small fw-bold">Gudang Pengirim</label>
                    <select name="from_warehouse_id" class="form-select form-select-sm">
                        <option value="">Semua Gudang</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ (string) $fromWhId === (string) $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="width: 180px;">
                    <label class="form-label mb-1 small fw-bold">Gudang Penerima</label>
                    <select name="to_warehouse_id" class="form-select form-select-sm">
                        <option value="">Semua Gudang</option>
                        @foreach($warehouses as $w)
                            <option value="{{ $w->id }}" {{ (string) $toWhId === (string) $w->id ? 'selected' : '' }}>{{ $w->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="width: 180px;">
                    <label class="form-label mb-1 small fw-bold">Status Approval</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Semua Status</option>
                        <option value="draft" {{ $statusFilter === 'draft' ? 'selected' : '' }}>Pengajuan (Draft)</option>
                        <option value="shipped" {{ $statusFilter === 'shipped' ? 'selected' : '' }}>Dikirim (Shipped)</option>
                        <option value="completed" {{ $statusFilter === 'completed' ? 'selected' : '' }}>Diterima (Completed)</option>
                    </select>
                </div>

                <div class="d-flex gap-1">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-search me-1"></i> Filter
                    </button>
                    <a href="{{ route('inventori.transfer.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>

            </form>
        </div>
    </div>

    <!-- Table List Mutasi -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="table-transfer" class="table table-hover table-striped align-middle mb-0" style="font-size: 0.88rem;">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center" style="width: 100px;">Tanggal</th>
                            <th class="text-center" style="width: 150px;">No. Mutasi</th>
                            <th>Gudang Pengirim</th>
                            <th>Gudang Penerima</th>
                            <th style="width: 140px;">BU Pengirim</th>
                            <th style="width: 140px;">BU Tujuan</th>
                            <th class="text-center" style="width: 130px;">Status Approval</th>
                            <th class="text-center" style="width: 220px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transfers as $t)
                            <tr>
                                <td class="text-center">{{Carbon\Carbon::parse($t->transfer_date)->format('d/m/Y') }}</td>
                                <td class="text-center font-monospace fw-bold text-primary">{{$t->transfer_no }}</td>
                                <td>
                                    <div class="fw-semibold text-dark"><i class="bi bi-box-arrow-up-right me-1 text-danger"></i> {{$t->from_warehouse_name }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><i class="bi bi-box-arrow-in-down-left me-1 text-success"></i> {{$t->to_warehouse_name }}</div>
                                </td>
                                <td>{{$t->business_unit_name ?? '-' }}</td>
                                <td>{{$t->destination_business_unit_name ?? $t->business_unit_name ?? 'Belum ditentukan' }}</td>
                                <td class="text-center">
                                    @if($t->status === 'draft')
                                        <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i> PENGAJUAN</span>
                                    @elseif($t->status === 'shipped')
                                        <span class="badge bg-info text-dark"><i class="bi bi-truck me-1"></i> DIKIRIM</span>
                                    @elseif($t->status === 'completed')
                                        <span class="badge bg-success"><i class="bi bi-check-all me-1"></i> SELESAI</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <!-- Detail Modal Button -->
                                        <button type="button" class="btn btn-outline-info btn-view-detail" data-id="{{$t->id }}">
                                            <i class="bi bi-eye"></i>
                                        </button>

                                        <!-- Cetak Bukti Mutasi -->
                                        <a href="{{ route('inventori.transfer.print-proof',$t->id) }}" target="_blank" class="btn btn-outline-secondary" title="Cetak Bukti">
                                            <i class="bi bi-printer"></i>
                                        </a>

                                        @if($t->status === 'draft')
                                            <!-- Edit (Hanya jika Draft) -->
                                            <button type="button" class="btn btn-outline-warning btn-edit-mutasi" data-id="{{$t->id }}">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>

                                            <!-- Hapus (Hanya jika Draft) -->
                                            <form action="{{ route('inventori.transfer.delete',$t->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="Hapus Mutasi">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>

                                            <!-- Approval Step 1: Pengirim -->
                                            <form action="{{ route('inventori.transfer.approve-sender',$t->id) }}" method="POST" class="d-inline form-approve-sender">
                                                @csrf
                                                <button type="submit" class="btn btn-success" title="Setujui Pengiriman">
                                                    <i class="bi bi-box-arrow-right"></i> Kirim
                                                </button>
                                            </form>
                                        @elseif($t->status === 'shipped')
                                            <!-- Approval Step 2: Penerima -->
                                            <button type="button"
                                                class="btn btn-primary btn-receive-mutasi"
                                                data-id="{{ $t->id }}"
                                                data-to-warehouse-id="{{ $t->to_warehouse_id }}"
                                                data-transfer-no="{{ $t->transfer_no }}"
                                                title="Konfirmasi gudang penerima">
                                                <i class="bi bi-box-arrow-in-down"></i> Terima
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-3 d-block mb-1"></i> Tidak ada data mutasi antar gudang ditemukan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Modal 1: Form Tambah / Edit Mutasi Antar Gudang -->
<div class="modal fade" id="modal-mutasi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title fw-bold" id="modal-mutasi-title"><i class="bi bi-arrow-left-right me-1"></i> Form Mutasi Antar Gudang</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-mutasi" method="POST" action="{{ route('inventori.transfer.store') }}">
                @csrf
                <input type="hidden" name="_method" id="form-method" value="POST">

                <div class="modal-body p-3">
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold">No. Mutasi (Otomatis)</label>
                            <input type="text" id="input-transfer-no" class="form-control form-control-sm font-monospace fw-bold bg-light" value="{{$autoCode }}" readonly>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold">BU Pengirim / Penanggung Jawab <span class="text-danger">*</span></label>
                            <select name="business_unit_id" id="input-bu" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Unit Bisnis --</option>
                                @foreach($businessUnits as $bu)
                                    <option value="{{ $bu->id }}">{{ $bu->code }} - {{ $bu->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold">Tgl. Mutasi <span class="text-danger">*</span></label>
                            <input type="date" name="transfer_date" id="input-date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-danger"><i class="bi bi-box-arrow-up-right me-1"></i> Gudang Pengirim (Asal) <span class="text-danger">*</span></label>
                            <select name="from_warehouse_id" id="input-from-wh" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Gudang Pengirim --</option>
                                @foreach($warehouses as $w)
                                    <option value="{{ $w->id }}" data-bu-ids="{{ $warehouseBusinessUnitMap->where('warehouse_id', $w->id)->pluck('business_unit_id')->implode(',') }}">{{ $w->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-success"><i class="bi bi-box-arrow-in-down-left me-1"></i> Gudang Penerima (Tujuan) <span class="text-danger">*</span></label>
                            <select name="to_warehouse_id" id="input-to-wh" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Gudang Penerima --</option>
                                @foreach($warehouses as $w)
                                    <option value="{{ $w->id }}" data-bu-ids="{{ $warehouseBusinessUnitMap->where('warehouse_id', $w->id)->pluck('business_unit_id')->implode(',') }}">{{ $w->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-light border py-2 mb-0 small">
                                <strong>BU Tujuan Otomatis:</strong>
                                <span id="destination-bu-label" class="text-primary">Pilih gudang tujuan untuk menentukan BU tujuan.</span>
                            </div>
                        </div>
                    </div>

                    <!-- Table Dynamic Items -->
                    <div class="card border mb-2">
                        <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                            <span class="fw-bold small"><i class="bi bi-box-seam me-1"></i> Daftar Barang Yang Dimutasi</span>
                            <button type="button" class="btn btn-sm btn-outline-success py-0 px-2" id="btn-add-item-row">+ Tambah Barang</button>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-sm align-middle mb-0" id="table-mutation-items">
                                <thead class="table-light">
                                    <tr>
                                        <th>Pilih Produk / Barang</th>
                                        <th style="width: 100px;" class="text-center">Satuan</th>
                                        <th style="width: 150px;" class="text-center">Jumlah (Qty)</th>
                                        <th style="width: 50px;" class="text-center">Hapus</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-items">
                                    <!-- Dynamic Rows Javascript -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mb-1">
                        <label class="form-label small fw-bold">Keterangan / Alasan Mutasi</label>
                        <textarea name="memo" id="input-memo" class="form-control form-control-sm" rows="2" placeholder="Contoh: Penyeimbangan stok cabang toko..."></textarea>
                    </div>
                </div>

                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3 fw-bold">
                        <i class="bi bi-save me-1"></i> Simpan Pengajuan Mutasi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: View Detail & Track Approval Status -->
<div class="modal fade" id="modal-detail" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-dark py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-info-circle me-1"></i> Detail Mutasi Antar Gudang</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-2 mb-3 border-bottom pb-2">
                    <div class="col-md-4">
                        <div class="text-muted small">No. Mutasi:</div>
                        <div class="fw-bold font-monospace fs-6 text-primary" id="det-no">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Tanggal:</div>
                        <div class="fw-bold" id="det-date">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">BU Pengirim:</div>
                        <div class="fw-bold" id="det-bu">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">BU Tujuan:</div>
                        <div class="fw-bold text-primary" id="det-to-bu">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Gudang Pengirim (Asal):</div>
                        <div class="fw-bold text-danger" id="det-from">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted small">Gudang Penerima (Tujuan):</div>
                        <div class="fw-bold text-success" id="det-to">-</div>
                    </div>
                </div>

                <!-- Approval Tracking Box -->
                <div class="row g-2 mb-3 bg-light p-2 rounded border">
                    <div class="col-md-6">
                        <div class="small fw-semibold text-muted">Approval Gudang Pengirim:</div>
                        <div class="small" id="det-sender-app">-</div>
                    </div>
                    <div class="col-md-6">
                        <div class="small fw-semibold text-muted">Approval Gudang Penerima:</div>
                        <div class="small" id="det-receiver-app">-</div>
                    </div>
                </div>

                <div class="table-responsive mb-2">
                    <table class="table table-sm table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Kode Barang</th>
                                <th>Nama Produk</th>
                                <th class="text-center" style="width: 120px;">Qty Mutasi</th>
                                <th class="text-center" style="width: 100px;">Satuan</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-detail-items">
                            <!-- Diisi JS -->
                        </tbody>
                    </table>
                </div>

                <div class="small text-muted"><strong>Keterangan:</strong> <span id="det-memo">-</span></div>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal 3: Konfirmasi Gudang Penerima Mutasi -->
<div class="modal fade" id="modal-terima-mutasi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-box-arrow-in-down me-1"></i> Konfirmasi Penerimaan Mutasi</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-terima-mutasi" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">No. Mutasi</label>
                        <input type="text" id="terima-transfer-no" class="form-control form-control-sm bg-light" readonly>
                    </div>
                    <div class="mb-2">
                        <label for="terima-warehouse-id" class="form-label small fw-bold">Gudang Penerima Aktual <span class="text-danger">*</span></label>
                        <select id="terima-warehouse-id" name="receiver_warehouse_id" class="form-select form-select-sm" required>
                            <option value="">-- Pilih Gudang Penerima --</option>
                            @foreach($warehouses as $w)
                                <option value="{{ $w->id }}">{{ $w->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Penerimaan hanya diizinkan pada gudang tujuan yang tercantum di dokumen mutasi. Gudang lain akan ditolak.</div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check2-circle me-1"></i> Konfirmasi Terima</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript Integration -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    const productsData = @json($products);

const businessUnitSelect = document.getElementById('input-bu');
const fromWarehouse = document.getElementById('input-from-wh');
const toWarehouse = document.getElementById('input-to-wh');
const destinationBuLabel = document.getElementById('destination-bu-label');

function filterWarehouseOptions() {
    const sourceBusinessUnitId = businessUnitSelect.value;
    [fromWarehouse, toWarehouse].forEach((select, index) => {
        Array.from(select.options).forEach(option => {
            if (!option.value) return;
            const mappedBusinessUnits = (option.dataset.buIds || '')
                .split(',')
                .map(value => value.trim())
                .filter(Boolean);
            const isMapped = mappedBusinessUnits.length > 0;
            const isUniqueDestinationMapping = index !== 1 || mappedBusinessUnits.length === 1;
            const matchesSourceBU = index !== 0 || (sourceBusinessUnitId !== '' && mappedBusinessUnits.includes(String(sourceBusinessUnitId)));
            const visible = isMapped && isUniqueDestinationMapping && matchesSourceBU;
            option.hidden = !visible;
            option.disabled = !visible;
        });
        if (select.selectedOptions.length && (select.selectedOptions[0].hidden || select.selectedOptions[0].disabled)) {
            select.value = '';
        }
    });
}

function clearProductRows() {
    productsData.length = 0;
    document.querySelectorAll('#tbody-items .item-product').forEach(select => {
        select.innerHTML = '<option value="">-- Pilih Barang --</option>';
        select.value = '';
    });
    document.querySelectorAll('#tbody-items .item-unit').forEach(unit => unit.textContent = '-');
}

async function loadTransferProducts(preserveItems = []) {
    const sourceBusinessUnitId = businessUnitSelect.value;
    const sourceWarehouseId = fromWarehouse.value;
    const destinationWarehouseId = toWarehouse.value;

    clearProductRows();
    if (destinationBuLabel) destinationBuLabel.textContent = 'Pilih gudang tujuan untuk menentukan BU tujuan.';

    if (!sourceBusinessUnitId || !sourceWarehouseId || !destinationWarehouseId) return;

    if (sourceWarehouseId === destinationWarehouseId) {
        toWarehouse.value = '';
        alert('Gudang pengirim dan gudang penerima tidak boleh sama.');
        return;
    }

    try {
        const url = `{{ url('/inventori/transfer/warehouse-products') }}/${sourceWarehouseId}?business_unit_id=${encodeURIComponent(sourceBusinessUnitId)}&to_warehouse_id=${encodeURIComponent(destinationWarehouseId)}`;
        const response = await fetch(url);
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'Mapping gudang atau item tidak valid.');

        productsData.push(...(result.products || []));
        if (destinationBuLabel) {
            destinationBuLabel.textContent = result.destination_business_unit_name || 'BU tujuan tidak ditemukan.';
        }

        // Saat edit, tetap tampilkan item lama agar bisa diperiksa/dihapus.
        preserveItems.forEach(item => {
            if (!productsData.some(product => String(product.id) === String(item.product_id ?? item.id))) {
                productsData.push({
                    id: item.product_id ?? item.id,
                    code: item.product_code ?? item.code ?? '',
                    name: item.product_name ?? item.name ?? '',
                    unit_name: item.unit_name || '-',
                    stock_qty: 0
                });
            }
        });

        document.querySelectorAll('#tbody-items tr').forEach(row => {
            const select = row.querySelector('.item-product');
            const unit = row.querySelector('.item-unit');
            if (!select) return;

            const currentValue = select.value;
            select.innerHTML = '<option value="">-- Pilih Barang --</option>';
            productsData.forEach(product => {
                select.innerHTML += `<option value="${product.id}">${product.code} - ${product.name} (${product.unit_name || '-'})</option>`;
            });
            select.value = preserveItems.length ? (preserveItems.find(item => String(item.product_id) === String(currentValue))?.product_id ?? currentValue) : '';
            const selected = productsData.find(product => String(product.id) === String(select.value));
            if (unit) unit.textContent = selected?.unit_name || '-';
        });
    } catch (error) {
        console.error(error);
        if (destinationBuLabel) destinationBuLabel.textContent = 'Mapping BU tujuan tidak valid.';
        alert(error.message || 'Gagal mengambil daftar barang untuk kedua BU.');
    }
}

businessUnitSelect.addEventListener('change', function () {
    fromWarehouse.value = '';
    toWarehouse.value = '';
    filterWarehouseOptions();
    clearProductRows();
    if (destinationBuLabel) destinationBuLabel.textContent = 'Pilih gudang tujuan untuk menentukan BU tujuan.';
});

fromWarehouse.addEventListener('change', function () {
    if (toWarehouse.value === this.value && this.value !== '') {
        toWarehouse.value = '';
    }
    loadTransferProducts();
});

toWarehouse.addEventListener('change', function () {
    if (this.value !== '' && this.value === fromWarehouse.value) {
        this.value = '';
        alert('Gudang pengirim dan gudang penerima tidak boleh sama.');
    }
    loadTransferProducts();
});

filterWarehouseOptions();

    // 1. Export Excel
    document.getElementById('btn-export-excel')?.addEventListener('click', function () {
        const form = document.getElementById('form-filter-mutasi');
        const params = new URLSearchParams(new FormData(form)).toString();
        window.location.href = "{{ route('inventori.transfer.export') }}?" + params;
    });

    // 2. Add Dynamic Item Row
    function addItemRow(prodId = '', qty = 1) {
        const tbody = document.getElementById('tbody-items');
        const tr = document.createElement('tr');

        let options = '<option value="">-- Pilih Barang --</option>';
        productsData.forEach(p => {
            const selected = (p.id == prodId) ? 'selected' : '';
            options += `<option value="${p.id}" ${selected}>${p.code} - ${p.name} (${p.unit_name})</option>`;
        });

        tr.innerHTML = `
            <td>
                <select name="products[]" class="form-select form-select-sm item-product" required>
                    ${options}
                </select>
            </td>
            <td class="text-center">
                <span class="item-unit text-muted">-</span>
            </td>
            <td>
                <input type="number" name="quantities[]" class="form-control form-control-sm text-center" step="0.01" min="0.01" value="${qty}" required>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 btn-remove-row"><i class="bi bi-trash"></i></button>
            </td>
        `;

        const productSelect = tr.querySelector('.item-product');
        const unitDisplay = tr.querySelector('.item-unit');

        function updateUnit() {
            const product = productsData.find(p => String(p.id) === String(productSelect.value));
            unitDisplay.textContent = product?.unit_name || '-';
        }

        productSelect.addEventListener('change', updateUnit);
        updateUnit();

        tbody.appendChild(tr);

        tr.querySelector('.btn-remove-row').addEventListener('click', function () {
            if (tbody.querySelectorAll('tr').length > 1) {
                tr.remove();
            } else {
                alert('Minimal satu barang harus disertakan!');
            }
        });
    }

    document.getElementById('btn-add-item-row')?.addEventListener('click', () => addItemRow());

    // Reset Form Modal saat Klik Tambah
    document.getElementById('btn-tambah-mutasi')?.addEventListener('click', function () {
        document.getElementById('form-mutasi').action = "{{ route('inventori.transfer.store') }}";
        document.getElementById('form-method').value = "POST";
        document.getElementById('modal-mutasi-title').innerHTML = '<i class="bi bi-arrow-left-right me-1"></i> Form Mutasi Antar Gudang';
        document.getElementById('input-bu').value = '';
        document.getElementById('input-from-wh').value = '';
        document.getElementById('input-to-wh').value = '';
        filterWarehouseOptions();
        document.getElementById('input-date').value = "{{ date('Y-m-d') }}";
        document.getElementById('input-memo').value = '';
        productsData.length = 0;
        if (destinationBuLabel) destinationBuLabel.textContent = 'Pilih gudang tujuan untuk menentukan BU tujuan.';
        document.getElementById('tbody-items').innerHTML = '';
        addItemRow(); // default 1 row
    });

    // 3. Edit Mutasi (Modal)
    document.querySelectorAll('.btn-edit-mutasi').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            fetch(`{{ url('/inventori/transfer/detail') }}/${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const t = data.transfer;
                        document.getElementById('form-mutasi').action = `{{ url('/inventori/transfer/update') }}/${id}`;
                        document.getElementById('form-method').value = "PUT";
                        document.getElementById('modal-mutasi-title').innerHTML = `<i class="bi bi-pencil-square me-1"></i> Edit Mutasi [${t.transfer_no}]`;

                        document.getElementById('input-transfer-no').value = t.transfer_no;
                        document.getElementById('input-bu').value = t.business_unit_id;
                        filterWarehouseOptions();
                        document.getElementById('input-date').value = t.transfer_date;
                        document.getElementById('input-from-wh').value = t.from_warehouse_id;
                        document.getElementById('input-to-wh').value = t.to_warehouse_id;
                        document.getElementById('input-memo').value = t.memo ?? '';

                        const tbody = document.getElementById('tbody-items');
                        tbody.innerHTML = '';

                        loadTransferProducts(data.items).then(() => {
                            data.items.forEach(item => addItemRow(item.product_id, item.quantity));
                            new bootstrap.Modal(document.getElementById('modal-mutasi')).show();
                        })
                            .catch(error => {
                                console.error(error);
                                alert('Gagal mengambil barang dari gudang pengirim.');
                            });
                    }
                });
        });
    });

    // 4. Konfirmasi Gudang Penerima. Server tetap memvalidasi kecocokan dengan tujuan dokumen.
    document.querySelectorAll('.btn-receive-mutasi').forEach(btn => {
        btn.addEventListener('click', function () {
            const transferId = this.dataset.id;
            const destinationWarehouseId = this.dataset.toWarehouseId;
            const form = document.getElementById('form-terima-mutasi');

            form.action = `{{ url('/inventori/transfer/approve-receiver') }}/${transferId}`;
            document.getElementById('terima-transfer-no').value = this.dataset.transferNo;
            document.getElementById('terima-warehouse-id').value = destinationWarehouseId;

            new bootstrap.Modal(document.getElementById('modal-terima-mutasi')).show();
        });
    });

    // 4. View Detail Modal
    document.querySelectorAll('.btn-view-detail').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.dataset.id;
            fetch(`{{ url('/inventori/transfer/detail') }}/${id}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const t = data.transfer;
                        document.getElementById('det-no').innerText   = t.transfer_no;
                        document.getElementById('det-date').innerText = t.transfer_date;
                        document.getElementById('det-bu').innerText   = t.business_unit_name ?? '-';
                        document.getElementById('det-to-bu').innerText = t.destination_business_unit_name ?? t.business_unit_name ?? 'Belum ditentukan';
                        document.getElementById('det-from').innerText = t.from_warehouse_name;
                        document.getElementById('det-to').innerText   = t.to_warehouse_name;
                        document.getElementById('det-memo').innerText = t.memo ?? '-';

                        document.getElementById('det-sender-app').innerHTML = t.sender_approver_name
                            ? `<span class="text-success"><i class="bi bi-check-circle me-1"></i> Disetujui oleh ${t.sender_approver_name} (${t.approved_sender_at})</span>`
                            : '<span class="text-warning"><i class="bi bi-clock me-1"></i> Menunggu Persetujuan</span>';

                        document.getElementById('det-receiver-app').innerHTML = t.receiver_approver_name
                            ? `<span class="text-success"><i class="bi bi-check-circle me-1"></i> Disetujui oleh ${t.receiver_approver_name} (${t.approved_receiver_at})</span>`
                            : '<span class="text-warning"><i class="bi bi-clock me-1"></i> Menunggu Persetujuan</span>';

                        let rows = '';
                        data.items.forEach(i => {
                            rows += `
                                <tr>
                                    <td class="font-monospace">${i.product_code}</td>
                                    <td>${i.product_name}</td>
                                    <td class="text-center fw-bold">${parseFloat(i.quantity).toLocaleString('id-ID')}</td>
                                    <td class="text-center">${i.unit_name}</td>
                                </tr>
                            `;
                        });
                        document.getElementById('tbody-detail-items').innerHTML = rows;

                        new bootstrap.Modal(document.getElementById('modal-detail')).show();
                    }
                });
        });
    });

});
</script>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const transferTable = document.querySelector('#table-transfer');
    const emptyTransferRow = transferTable?.querySelector('tbody td[colspan="8"]');

    if (transferTable && !emptyTransferRow) {
        new DataTable('#table-transfer', {
            pageLength: 10,
            lengthMenu: [10, 25, 50, 100],
            order: [[0, 'desc']],
            language: {
                search: 'Cari:',
                lengthMenu: 'Tampilkan _MENU_ data',
                info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
                infoEmpty: 'Tidak ada data',
                zeroRecords: 'Data tidak ditemukan',
                paginate: {
                    first: 'Awal',
                    last: 'Akhir',
                    next: '›',
                    previous: '‹'
                }
            }
        });
    }
});
</script>
@endpush
