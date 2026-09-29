@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Page Header (Judul & 2 Tombol Modal) -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold text-dark">Pengeluaran Kas & Bank</h3>
            <div class="text-secondary small">Daftar & Monitoring Seluruh Arus Uang Keluar</div>
        </div>
        <div class="d-flex gap-2">
            <!-- Tombol 1: Pembayaran Hutang -->
            <button type="button" class="btn btn-outline-danger btn-sm px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#modal-pembayaran-hutang">
                <i class="bi bi-wallet2 me-1"></i> + Pembayaran Hutang
            </button>

            <!-- Tombol 2: Pengeluaran Kas/Bank Umum -->
            <button type="button" class="btn btn-danger btn-sm px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#modal-pengeluaran-lain">
                <i class="bi bi-dash-circle me-1"></i> + Pengeluaran Kas / Bank
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
        <div class="col-md-4">
            <div class="p-3 bg-white rounded border shadow-sm">
                <div class="text-muted small">Pembayaran Hutang Vendor (AP)</div>
                <div class="fw-bold fs-5 text-danger">Rp {{ number_format($totalApPayment, 2, ',', '.') }}</div>
                <div class="small text-muted">Pelunasan Faktur Pembelian</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3 bg-white rounded border shadow-sm">
                <div class="text-muted small">Pengeluaran Umum / Beban</div>
                <div class="fw-bold fs-5 text-warning">Rp {{ number_format($totalOtherDisbursement, 2, ',', '.') }}</div>
                <div class="small text-muted">Beban Operasional & Non-Faktur</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3 bg-white rounded border shadow-sm">
                <div class="text-muted small">Total Seluruh Pengeluaran</div>
                <div class="fw-bold fs-5 text-dark">Rp {{ number_format($totalDisbursementAmount, 2, ',', '.') }}</div>
                <div class="small text-muted">Periode Filter Terpilih</div>
            </div>
        </div>
    </div>

    <!-- Card Filter List (Periode | Unit Bisnis | Jenis | Tipe Kas/Bank) -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <form id="form-filter-kas-keluar" method="GET" action="{{ route('akuntansi.kas-keluar') }}" class="row g-2 align-items-end">

                <div class="col-md-3">
                    <label class="form-label mb-1 small fw-bold">Unit Bisnis</label>
                    <select name="business_unit_id" class="form-select form-select-sm">
                        <option value="">Semua Unit Bisnis</option>
                        @foreach($businessUnits as $bu)
                            <option value="{{ $bu->id }}" {{ (string) $businessUnitId === (string) $bu->id ? "selected" : "" }}>
                                {{ $bu->code }} - {{ $bu->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label mb-1 small fw-bold">Tipe (Akun Kas/Bank)</label>
                    <select name="cash_account_id" class="form-select form-select-sm">
                        <option value="">Semua Kas & Bank</option>
                       @foreach($cashAccounts as $acc)
                            <option value="{{ $acc->id }}" {{ (string) $cashAccountId === (string) $acc->id ? "selected" : "" }}>
                                [{{ $acc->code }}] {{ $acc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label mb-1 small fw-bold">Jenis (Kategori)</label>
                    <select name="disbursement_type" class="form-select form-select-sm">
                        <option value="">Semua Kategori</option>
                        <option value="AP_PAYMENT" {{ $disbursementType === 'AP_PAYMENT' ? 'selected' : '' }}>Pembayaran Hutang</option>
                        <option value="CASH_OUT" {{ $disbursementType === 'CASH_OUT' ? 'selected' : '' }}>Pengeluaran Umum</option>
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
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari no. transaksi / keterangan..." value="{{ $search }}">
                </div>

                <div class="col-md-4 d-flex justify-content-end gap-2 mt-2">
                    <a href="{{ route('akuntansi.kas-keluar') }}" class="btn btn-outline-secondary btn-sm px-3">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                    </a>
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-search me-1"></i> Tampilkan
                    </button>
                    <button type="button" id="btn-export-excel" class="btn btn-success btn-sm px-3">
                        <i class="bi bi-file-earmark-excel me-1"></i> Excel
                    </button>
                </div>

            </form>
        </div>
    </div>

    <!-- Table Data Pengeluaran Kas/Bank -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.9rem;">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center" style="width: 100px;">Tanggal</th>
                            <th class="text-center" style="width: 150px;">No. Transaksi</th>
                            <th style="width: 140px;">Business Unit</th>
                            <th class="text-center" style="width: 130px;">Kategori</th>
                            <th style="width: 180px;">Akun Kas/Bank (Sumber)</th>
                            <th style="width: 180px;">Akun Tujuan / Beban</th>
                            <th>Keterangan / Memo</th>
                            <th class="text-end" style="width: 160px;">Nominal (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($disbursements as $journal)
                            @php
                                $cashEntry = $journal->entries->where('credit', '>', 0)->first();
                                $counterpartEntry = $journal->entries->where('debit', '>', 0)->first();
                            @endphp
                            <tr>
                                <td class="text-center">{{ $journal->journal_date }}</td>
                                <td class="text-center font-monospace fw-semibold">{{ $journal->journal_no }}</td>
                                <td>{{ $journal->businessUnit?->name ?? '-' }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $journal->source_type === 'AP_PAYMENT' ? 'bg-danger' : 'bg-warning text-dark' }}">
                                        {{ $journal->source_type === 'AP_PAYMENT' ? 'BAYAR AP' : 'KAS KELUAR' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-danger">
                                        [{{ $cashEntry?->account?->code }}] {{ $cashEntry?->account?->name }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-dark">
                                        [{{ $counterpartEntry?->account?->code }}] {{ $counterpartEntry?->account?->name }}
                                    </span>
                                </td>
                                <td>{{ $journal->description }}</td>
                                <td class="text-end fw-bold text-danger">
                                    Rp {{ number_format($cashEntry?->credit ?? 0, 2, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                                    Tidak ada data pengeluaran kas/bank pada periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Server-Side Pagination Links Footer -->
        <div class="card-footer bg-light d-flex justify-content-between align-items-center py-2 px-3">
            <div class="text-muted small">
                Menampilkan <strong>{{ $disbursements->firstItem() ?? 0 }}</strong> - <strong>{{ $disbursements->lastItem() ?? 0 }}</strong> dari total <strong>{{ $disbursements->total() }}</strong> transaksi
            </div>
            <div>
                {{ $disbursements->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>

</div>

<!-- Modal 1: Pembayaran Hutang Vendor (Pilih Supplier -> Tampil Faktur Unpaid + Input Nominal Bayar) -->
<div class="modal fade" id="modal-pembayaran-hutang" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-wallet2 me-1"></i> Form Pembayaran Hutang Vendor (AP)</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('akuntansi.kas-keluar.store-ap') }}" method="POST">
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
                            <label class="form-label small fw-bold">Supplier / Vendor <span class="text-danger">*</span></label>
                            <select name="supplier_id" id="select-supplier-ap" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Supplier --</option>
                                @foreach($suppliers as $s)
                                    <option value="{{ $s->id }}">{{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Dynamic Table Faktur Pembelian Belum Lunas -->
                        <div class="col-md-12 d-none" id="wrapper-unpaid-bills">
                            <label class="form-label small fw-bold text-danger mb-1">
                                <i class="bi bi-list-check me-1"></i> Pilih Faktur Pembelian Belum Lunas yang Dibayar:
                            </label>
                            <div class="table-responsive border rounded bg-light" style="max-height: 220px; overflow-y: auto;">
                                <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.85rem;">
                                    <thead class="table-secondary sticky-top">
                                        <tr>
                                            <th class="text-center" style="width: 40px;">#</th>
                                            <th>No. Faktur Beli</th>
                                            <th>Tgl. Faktur</th>
                                            <th class="text-end">Sisa Hutang (Rp)</th>
                                            <th class="text-end" style="width: 170px;">Jumlah Bayar (Rp)</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-unpaid-bills">
                                        <!-- Diisi via AJAX -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Tanggal Pembayaran <span class="text-danger">*</span></label>
                            <input type="date" name="journal_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Akun Sumber (Kas/Bank) <span class="text-danger">*</span></label>
                            <select name="cash_account_id" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Kas/Bank Pembayar --</option>
                                @foreach($cashAccounts as $acc)
                                    <option value="{{ $acc->id }}">[{{ $acc->code }}] {{ $acc->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Total Pembayaran (Rp) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" id="input-amount-ap" step="0.01" min="1" class="form-control form-control-sm fw-bold text-danger fs-6" placeholder="0.00" required readonly>
                            <span class="text-muted" style="font-size: 0.75rem;">*Terhitung otomatis dari jumlah bayar yang dicentang.</span>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Keterangan / Memo <span class="text-danger">*</span></label>
                            <textarea name="description" id="input-description-ap" class="form-control form-control-sm" rows="2" placeholder="Pembayaran hutang..." required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm px-3">
                        <i class="bi bi-save me-1"></i> Simpan Pembayaran AP
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Pengeluaran Kas / Bank Umum (Dengan COA Hierarki Level 2 & 3) -->
<div class="modal fade" id="modal-pengeluaran-lain" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-dash-circle me-1"></i> Form Pengeluaran Kas / Bank Umum</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('akuntansi.kas-keluar.store-other') }}" method="POST">
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
                            <label class="form-label small fw-bold">Tanggal Transaksi <span class="text-danger">*</span></label>
                            <input type="date" name="journal_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Akun Sumber (Kas/Bank - Kredit) <span class="text-danger">*</span></label>
                            <select name="cash_account_id" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Akun Kas/Bank --</option>
                                @foreach($cashAccounts as $acc)
                                    <option value="{{ $acc->id }}">[{{ $acc->code }}] {{ $acc->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Dropdown Akun Beban/Tujuan dengan Hierarki Level 2 (Optgroup) dan Level 3 (Option) -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Akun Tujuan / Beban (Hierarki COA Level 2 & 3 - Debit) <span class="text-danger">*</span></label>
                            <select name="counterpart_account_id" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Akun Tujuan (Level 3) --</option>
                                @foreach($coaLevel2 as $headerL2)
                                    @if($headerL2->children->count() > 0)
                                        <optgroup label="[Level 2] {{ $headerL2->code }} - {{ $headerL2->name }}">
                                            @foreach($headerL2->children as $childL3)
                                                <option value="{{ $childL3->id }}">
                                                    &nbsp;&nbsp;&nbsp;&nbsp; [L3: {{ $childL3->code }}] {{ $childL3->name }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Nominal Pengeluaran (Rp) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" step="0.01" min="1" class="form-control form-control-sm fw-bold text-danger fs-6" placeholder="0.00" required>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Keterangan / Memo <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="Pembayaran gaji / sewa / listrik & air..." required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-dark btn-sm px-3">
                        <i class="bi bi-save me-1"></i> Simpan & Posting Jurnal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript Integration -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    // 1. Export Excel Event
    document.getElementById('btn-export-excel')?.addEventListener('click', function () {
        const form     = document.getElementById('form-filter-kas-keluar');
        const formData = new FormData(form);
        const params   = new URLSearchParams(formData).toString();

        window.location.href = "{{ route('akuntansi.kas-keluar.export') }}?" + params;
    });

    // 2. AJAX Fetch Bills saat Supplier Dipilih
    document.getElementById('select-supplier-ap')?.addEventListener('change', function () {
        const supplierId  = this.value;
        const wrapper     = document.getElementById('wrapper-unpaid-bills');
        const tbody       = document.getElementById('tbody-unpaid-bills');
        const amountInput = document.getElementById('input-amount-ap');
        const descInput   = document.getElementById('input-description-ap');

        tbody.innerHTML   = '';
        if (amountInput) amountInput.value = '0';
        if (descInput) descInput.value = '';

        if (!supplierId) {
            wrapper.classList.add('d-none');
            return;
        }

        fetch("{{ url('/akuntansi/kas-bank/keluar/unpaid-bills') }}/" + supplierId)
            .then(response => response.json())
            .then(data => {
                wrapper.classList.remove('d-none');

                if (data.success && data.bills.length > 0) {
                    data.bills.forEach(bill => {
                        const row = `
                            <tr>
                                <td class="text-center">
                                    <input type="checkbox" name="purchase_ids[]" value="${bill.id}" 
                                           data-id="${bill.id}" 
                                           data-no="${bill.purchase_no}" 
                                           data-max="${bill.remaining_amount}" 
                                           class="form-check-input chk-bill">
                                </td>
                                <td class="fw-semibold text-danger">${bill.purchase_no}</td>
                                <td>${bill.purchase_date}</td>
                                <td class="text-end fw-bold">Rp ${parseFloat(bill.remaining_amount).toLocaleString('id-ID')}</td>
                                <td>
                                    <input type="number" name="pay_amounts[${bill.id}]" id="pay-amount-${bill.id}" 
                                           class="form-control form-control-sm text-end fw-semibold input-pay-amount" 
                                           step="0.01" min="0" max="${bill.remaining_amount}" value="0" disabled>
                                </td>
                            </tr>
                        `;
                        tbody.insertAdjacentHTML('beforeend', row);
                    });

                    // Event Listener Checkbox
                    document.querySelectorAll('.chk-bill').forEach(chk => {
                        chk.addEventListener('change', function() {
                            const billId    = this.dataset.id;
                            const maxAmount = this.dataset.max;
                            const inputPay  = document.getElementById('pay-amount-' + billId);

                            if (this.checked) {
                                inputPay.disabled = false;
                                inputPay.value    = maxAmount; 
                            } else {
                                inputPay.disabled = true;
                                inputPay.value    = 0;
                            }
                            calculateSelectedBills();
                        });
                    });

                    // Event Listener Input Nominal Bayar
                    document.querySelectorAll('.input-pay-amount').forEach(input => {
                        input.addEventListener('input', calculateSelectedBills);
                        input.addEventListener('keyup', calculateSelectedBills);
                    });

                } else {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted small py-2"><i class="bi bi-check-all me-1"></i> Tidak ada faktur pembelian belum lunas untuk supplier ini.</td></tr>';
                }
            })
            .catch(error => console.error('Error fetching bills:', error));
    });

    // 3. Auto Hitung Total & Buat Memo AP
    function calculateSelectedBills() {
        let totalSum = 0;
        let selectedBills = [];

        document.querySelectorAll('.chk-bill:checked').forEach(chk => {
            const billId   = chk.dataset.id;
            const billNo   = chk.dataset.no;
            const inputPay = document.getElementById('pay-amount-' + billId);
            const payValue = parseFloat(inputPay ? inputPay.value : 0) || 0;

            totalSum += payValue;
            if (billNo) {
                selectedBills.push(billNo);
            }
        });

        const amountInput = document.getElementById('input-amount-ap');
        const descInput   = document.getElementById('input-description-ap');

        if (amountInput) {
            amountInput.value = totalSum;
        }

        if (descInput) {
            if (selectedBills.length > 0) {
                descInput.value = 'Pembayaran/Cicilan Hutang Faktur: ' + selectedBills.join(', ');
            } else {
                descInput.value = '';
            }
        }
    }

});
</script>
@endsection