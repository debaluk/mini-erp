@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold text-dark">Buku Bantu Piutang (AR Sub-Ledger)</h3>
            <div class="text-secondary small">Detail Tagihan, Umur Piutang & Kartu Piutang Pelanggan</div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-success btn-sm px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#modal-setup-saldo-awal">
                <i class="bi bi-plus-circle me-1"></i> + Setup Saldo Awal
            </button>

            <button type="button" id="btn-export-excel-list" class="btn btn-success btn-sm px-3 fw-semibold">
                <i class="bi bi-file-earmark-excel me-1"></i> Excel List Piutang
            </button>
        </div>
    </div>

    <!-- Alert Notifications -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="p-3 bg-white rounded border shadow-sm">
                <div class="text-muted small">Total Piutang Usaha</div>
                <div class="fw-bold fs-5 text-primary">Rp {{ number_format($totalArAmount, 2, ',', '.') }}</div>
                <div class="small text-muted">Sisa Tagihan Belum Lunas</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="p-3 bg-white rounded border shadow-sm">
                <div class="text-muted small">Piutang Lancar (Aman)</div>
                <div class="fw-bold fs-5 text-success">Rp {{ number_format($totalCurrentAr, 2, ',', '.') }}</div>
                <div class="small text-muted">Belum Jatuh Tempo</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="p-3 bg-white rounded border shadow-sm">
                <div class="text-muted small">Piutang Overdue (Menunggak)</div>
                <div class="fw-bold fs-5 text-danger">Rp {{ number_format($totalOverdueAr, 2, ',', '.') }}</div>
                <div class="small text-muted">Lewat Tanggal Jatuh Tempo</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="p-3 bg-white rounded border shadow-sm bg-light">
                <div class="text-muted small">Pelunasan Periode Ini</div>
                <div class="fw-bold fs-5 text-dark">Rp {{ number_format($totalPaidThisPeriod, 2, ',', '.') }}</div>
                <div class="small text-muted">Total Pembayaran Masuk</div>
            </div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <form id="form-filter-buku-piutang" method="GET" action="{{ route('akuntansi.buku-piutang') }}" class="row g-2 align-items-end">

                <div class="col-md-3">
                    <label class="form-label mb-1 small fw-bold">Unit Bisnis</label>
                    <select name="business_unit_id" class="form-select form-select-sm">
                        <option value="">Semua Unit Bisnis</option>
                        @foreach($businessUnits as $bu)
                            <option value="{{ $bu->id }}" {{ (string) $businessUnitId === (string) $bu->id ? 'selected' : '' }}>
                                {{ $bu->code }} - {{ $bu->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label mb-1 small fw-bold">Pelanggan / Customer</label>
                    <select name="customer_id" class="form-select form-select-sm">
                        <option value="">Semua Pelanggan</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" {{ (string) $customerId === (string) $c->id ? 'selected' : '' }}>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label mb-1 small fw-bold">Status Tagihan</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">Semua Status</option>
                        <option value="unpaid" {{ $statusFilter === 'unpaid' ? 'selected' : '' }}>Belum Bayar (Unpaid)</option>
                        <option value="partial" {{ $statusFilter === 'partial' ? 'selected' : '' }}>Cicilan (Partial)</option>
                        <option value="overdue" {{ $statusFilter === 'overdue' ? 'selected' : '' }}>Overdue (Menunggak)</option>
                        <option value="paid" {{ $statusFilter === 'paid' ? 'selected' : '' }}>Lunas (Paid)</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label mb-1 small fw-bold">Mulai Tanggal</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
                </div>

                <div class="col-md-2">
                    <label class="form-label mb-1 small fw-bold">Sampai Tanggal</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
                </div>

                <div class="col-md-8 mt-2">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari no. faktur / pelanggan..." value="{{ $search }}">
                </div>

                <div class="col-md-4 d-flex justify-content-end gap-2 mt-2">
                    <a href="{{ route('akuntansi.buku-piutang') }}" class="btn btn-outline-secondary btn-sm px-3">Reset</a>
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-search me-1"></i> Tampilkan
                    </button>
                </div>

            </form>
        </div>
    </div>

    <!-- Nav Tabs -->
    <ul class="nav nav-tabs mb-3" id="arTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-semibold" id="tab-list-inv" data-bs-toggle="tab" data-bs-target="#content-list-inv" type="button" role="tab">
                <i class="bi bi-list-columns-reverse me-1"></i> Daftar Faktur Piutang & Aging
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold" id="tab-card-cust" data-bs-toggle="tab" data-bs-target="#content-card-cust" type="button" role="tab">
                <i class="bi bi-card-checklist me-1"></i> Kartu Piutang per Pelanggan
            </button>
        </li>
    </ul>

    <div class="tab-content" id="arTabsContent">

        <!-- TAB 1: DAFTAR FAKTUR PIUTANG & AGING -->
        <div class="tab-pane fade show active" id="content-list-inv" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.88rem;">
                            <thead class="table-dark">
                                <tr>
                                    <th class="text-center" style="width: 100px;">Tgl. Faktur</th>
                                    <th class="text-center" style="width: 140px;">No. Faktur</th>
                                    <th>Pelanggan / Customer</th>
                                    <th style="width: 130px;">Unit Bisnis</th>
                                    <th class="text-center" style="width: 100px;">Jatuh Tempo</th>
                                    <th class="text-center" style="width: 100px;">Status / Umur</th>
                                    <th class="text-end" style="width: 130px;">Total Faktur (Rp)</th>
                                    <th class="text-end" style="width: 120px;">Terbayar (Rp)</th>
                                    <th class="text-end" style="width: 130px;">Sisa Piutang (Rp)</th>
                                    <th class="text-center" style="width: 80px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($filteredList as $inv)
                                    <tr>
                                        <td class="text-center">{{ \Carbon\Carbon::parse($inv->sale_date)->format('d/m/Y') }}</td>
                                        <td class="text-center font-monospace fw-semibold text-primary">{{ $inv->invoice_no }}</td>
                                        <td>
                                            <div class="fw-semibold text-dark">{{ $inv->customer_name }}</div>
                                            <div class="text-muted small" style="font-size: 0.75rem;">{{ $inv->customer_phone ?? '-' }}</div>
                                        </td>
                                        <td>{{ $inv->business_unit_name ?? '-' }}</td>
                                        <td class="text-center">
                                            {{ $inv->due_date ? \Carbon\Carbon::parse($inv->due_date)->format('d/m/Y') : '-' }}
                                        </td>
                                        <td class="text-center">
                                            @if($inv->ar_status === 'paid')
                                                <span class="badge bg-success">LUNAS</span>
                                            @elseif($inv->ar_status === 'overdue')
                                                <span class="badge bg-danger">OVERDUE ({{ $inv->overdue_days }} HR)</span>
                                            @elseif($inv->ar_status === 'partial')
                                                <span class="badge bg-warning text-dark">CICILAN</span>
                                            @else
                                                <span class="badge bg-secondary">BELUM BAYAR</span>
                                            @endif
                                        </td>
                                        <td class="text-end fw-semibold">Rp {{ number_format($inv->total, 2, ',', '.') }}</td>
                                        <td class="text-end text-success">Rp {{ number_format($inv->paid_amount, 2, ',', '.') }}</td>
                                        <td class="text-end fw-bold text-danger">Rp {{ number_format($inv->remaining_amount, 2, ',', '.') }}</td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-outline-warning btn-sm py-0 px-2 btn-edit-piutang"
                                                    data-id="{{ $inv->id }}"
                                                    data-bu="{{ $inv->business_unit_id }}"
                                                    data-cust="{{ $inv->customer_id }}"
                                                    data-inv="{{ $inv->invoice_no }}"
                                                    data-date="{{ $inv->sale_date ? \Carbon\Carbon::parse($inv->sale_date)->format('Y-m-d') : '' }}"
                                                    data-due="{{ $inv->due_date }}"
                                                    data-amount="{{ $inv->total }}"
                                                    data-memo="{{ $inv->memo }}">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                                            Tidak ada data faktur piutang ditemukan.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: KARTU PIUTANG PER PELANGGAN -->
        <div class="tab-pane fade" id="content-card-cust" role="tabpanel">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-body p-3">
                    <div class="row g-2 align-items-center justify-content-between">
                        <div class="col-md-5">
                            <label class="form-label mb-1 small fw-bold">Pilih Pelanggan untuk Kartu Piutang:</label>
                            <select id="select-card-customer" class="form-select form-select-sm">
                                <option value="">-- Pilih Customer --</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 text-end d-none" id="wrapper-btn-export-card">
                            <button type="button" id="btn-export-excel-card" class="btn btn-success btn-sm px-3 fw-semibold mt-3">
                                <i class="bi bi-file-earmark-excel me-1"></i> Cetak Kartu Piutang Excel
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 d-none" id="wrapper-customer-card">
                <div class="card-header bg-primary text-white py-2 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold" id="card-customer-title"><i class="bi bi-person-lines-fill me-1"></i> Kartu Piutang</h6>
                    <div id="card-customer-balance" class="fw-bold">Saldo Akhir: Rp 0,00</div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.88rem;">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center" style="width: 130px;">Tanggal</th>
                                    <th style="width: 160px;">No. Referensi</th>
                                    <th>Keterangan Transaksi</th>
                                    <th class="text-end" style="width: 140px;">Debit / Penjualan (Rp)</th>
                                    <th class="text-end" style="width: 140px;">Kredit / Pelunasan (Rp)</th>
                                    <th class="text-end" style="width: 150px;">Saldo Akhir (Rp)</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-customer-ledger">
                                <!-- Diisi via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>



    </div>

</div>

<!-- Modal 1: Form Setup Saldo Awal Piutang Usaha -->
<div class="modal fade" id="modal-setup-saldo-awal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-plus-circle me-1"></i> Form Setup Saldo Awal Piutang Usaha</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('akuntansi.buku-piutang.store-initial') }}" method="POST">
                @csrf
                <div class="modal-body p-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Business Unit <span class="text-danger">*</span></label>
                            <select name="business_unit_id" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Business Unit --</option>
                                @foreach($businessUnits as $bu)
                                    <option value="{{ $bu->id }}">{{ $bu->code }} - {{ $bu->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Pelanggan / Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Customer --</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">No. Faktur Pembuka / Lama <span class="text-danger">*</span></label>
                            <input type="text" name="invoice_no" class="form-control form-control-sm font-monospace" placeholder="INV-LAMA-001" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Tgl. Faktur <span class="text-danger">*</span></label>
                            <input type="date" name="sale_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Jatuh Tempo <span class="text-danger">*</span></label>
                            <input type="date" name="due_date" class="form-control form-control-sm" value="{{ date('Y-m-d', strtotime('+30 days')) }}" required>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Nominal Piutang Awal (Rp) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" step="0.01" min="1" class="form-control form-control-sm fw-bold text-danger fs-6" placeholder="0.00" required>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Keterangan / Memo <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="Saldo awal piutang sebelum migrasi..." required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success btn-sm px-3">
                        <i class="bi bi-save me-1"></i> Simpan Saldo Awal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Form Edit Piutang Usaha -->
<div class="modal fade" id="modal-edit-piutang" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning text-dark py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-pencil-square me-1"></i> Form Edit Faktur Piutang Usaha</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="form-update-piutang" method="POST">
                @csrf
                @method('PUT')
                <div class="modal-body p-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Business Unit <span class="text-danger">*</span></label>
                            <select name="business_unit_id" id="edit-bu" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Business Unit --</option>
                                @foreach($businessUnits as $bu)
                                    <option value="{{ $bu->id }}">{{ $bu->code }} - {{ $bu->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Pelanggan / Customer <span class="text-danger">*</span></label>
                            <select name="customer_id" id="edit-cust" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Customer --</option>
                                @foreach($customers as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">No. Faktur <span class="text-danger">*</span></label>
                            <input type="text" name="invoice_no" id="edit-inv" class="form-control form-control-sm font-monospace" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Tgl. Faktur <span class="text-danger">*</span></label>
                            <input type="date" name="sale_date" id="edit-date" class="form-control form-control-sm" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label small fw-bold">Jatuh Tempo <span class="text-danger">*</span></label>
                            <input type="date" name="due_date" id="edit-due" class="form-control form-control-sm" required>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Nominal Piutang (Rp) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" id="edit-amount" step="0.01" min="1" class="form-control form-control-sm fw-bold text-danger fs-6" required>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Keterangan / Memo <span class="text-danger">*</span></label>
                            <textarea name="description" id="edit-memo" class="form-control form-control-sm" rows="2" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning btn-sm px-3 fw-bold">
                        <i class="bi bi-save me-1"></i> Perbarui Faktur Piutang
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript Integration -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    // 1. Export Excel List Piutang
    document.getElementById('btn-export-excel-list')?.addEventListener('click', function () {
        const form     = document.getElementById('form-filter-buku-piutang');
        const formData = new FormData(form);
        const params   = new URLSearchParams(formData).toString();

        window.location.href = "{{ route('akuntansi.buku-piutang.export-list') }}?" + params;
    });

    // 2. Export Excel Kartu Piutang
    document.getElementById('btn-export-excel-card')?.addEventListener('click', function () {
        const customerId = document.getElementById('select-card-customer').value;
        if (!customerId) return;

        const startDate = "{{ $startDate }}";
        const endDate   = "{{ $endDate }}";
        const buId      = "{{ $businessUnitId }}";

        window.location.href = `{{ url('/akuntansi/buku-piutang/export-customer-ledger') }}/${customerId}?start_date=${startDate}&end_date=${endDate}&business_unit_id=${buId}`;
    });

    // 3. Populate Edit Modal
    document.querySelectorAll('.btn-edit-piutang').forEach(btn => {
        btn.addEventListener('click', function () {
            const id     = this.dataset.id;
            const bu     = this.dataset.bu;
            const cust   = this.dataset.cust;
            const inv    = this.dataset.inv;
            const date   = this.dataset.date;
            const due    = this.dataset.due;
            const amount = this.dataset.amount;
            const memo   = this.dataset.memo;

            document.getElementById('form-update-piutang').action = `{{ url('/akuntansi/buku-piutang/update-initial') }}/${id}`;
            document.getElementById('edit-bu').value     = bu;
            document.getElementById('edit-cust').value   = cust;
            document.getElementById('edit-inv').value    = inv;
            document.getElementById('edit-date').value   = date;
            document.getElementById('edit-due').value    = due;
            document.getElementById('edit-amount').value = amount;
            document.getElementById('edit-memo').value   = memo;

            const modal = new bootstrap.Modal(document.getElementById('modal-edit-piutang'));
            modal.show();
        });
    });

    // 4. Fetch Customer Ledger Card (Tab 2)
    document.getElementById('select-card-customer')?.addEventListener('change', function () {
        const customerId = this.value;
        const wrapper    = document.getElementById('wrapper-customer-card');
        const btnExport  = document.getElementById('wrapper-btn-export-card');
        const tbody      = document.getElementById('tbody-customer-ledger');
        const title      = document.getElementById('card-customer-title');
        const balLabel   = document.getElementById('card-customer-balance');

        tbody.innerHTML = '';
        if (!customerId) {
            wrapper.classList.add('d-none');
            btnExport.classList.add('d-none');
            return;
        }

        const startDate = "{{ $startDate }}";
        const endDate   = "{{ $endDate }}";
        const buId      = "{{ $businessUnitId }}";

        fetch(`{{ url('/akuntansi/buku-piutang/customer-ledger') }}/${customerId}?start_date=${startDate}&end_date=${endDate}&business_unit_id=${buId}`)
            .then(res => res.json())
            .then(data => {
                wrapper.classList.remove('d-none');
                btnExport.classList.remove('d-none');

                if (data.success) {
                    title.innerHTML = `<i class="bi bi-person-lines-fill me-1"></i> Kartu Piutang: ${data.customer.name}`;
                    balLabel.innerText = `Saldo Akhir: Rp ${parseFloat(data.ending_balance).toLocaleString('id-ID')}`;

                    let rowsHtml = `
                        <tr class="table-secondary fw-semibold">
                            <td class="text-center">${startDate}</td>
                            <td>-</td>
                            <td>SALDO AWAL PIUTANG (OPENING BALANCE)</td>
                            <td class="text-end">-</td>
                            <td class="text-end">-</td>
                            <td class="text-end fw-bold">Rp ${parseFloat(data.opening_balance).toLocaleString('id-ID')}</td>
                        </tr>
                    `;

                    if (data.ledger.length > 0) {
                        data.ledger.forEach(row => {
                            rowsHtml += `
                                <tr>
                                    <td class="text-center">${row.date}</td>
                                    <td class="font-monospace text-primary">${row.ref_no}</td>
                                    <td>${row.description}</td>
                                    <td class="text-end text-danger">${row.debit > 0 ? 'Rp ' + parseFloat(row.debit).toLocaleString('id-ID') : '-'}</td>
                                    <td class="text-end text-success">${row.credit > 0 ? 'Rp ' + parseFloat(row.credit).toLocaleString('id-ID') : '-'}</td>
                                    <td class="text-end fw-bold">Rp ${parseFloat(row.balance).toLocaleString('id-ID')}</td>
                                </tr>
                            `;
                        });
                    } else {
                        rowsHtml += `<tr><td colspan="6" class="text-center text-muted small py-3">Tidak ada mutasi piutang pada periode terpilih.</td></tr>`;
                    }

                    tbody.innerHTML = rowsHtml;
                }
            })
            .catch(err => console.error(err));
    });

});
</script>
@endsection