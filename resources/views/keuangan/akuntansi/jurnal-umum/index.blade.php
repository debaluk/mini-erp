@extends('layouts.app')

@section('content')

<!-- =========================================================
     1. HEADER HALAMAN & TOMBOL AKSI
     ========================================================= -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1 fw-bold">Jurnal Umum & Ledger</h3>
        <div class="text-secondary small">Daftar pencatatan jurnal otomatis sistem dan jurnal penyesuaian manual</div>
    </div>

    <button type="button" class="btn btn-primary fw-bold" onclick="openFormJurnal()">
        + Input Jurnal Umum
    </button>
</div>

<!-- =========================================================
     2. CARD FILTER KRITERIA & TABLE DATATABLES
     ========================================================= -->
<div class="card shadow-sm border-0">
    
    <!-- Form Filter Kriteria -->
    <div class="card-body border-bottom py-3 bg-light-subtle">
        <form id="form-filter-jurnal" action="" class="d-flex align-items-end gap-2 flex-wrap">

    <!-- Filter 1: Mulai Tanggal -->
    <div>
        <label class="form-label mb-1 small fw-bold">Mulai Tanggal</label>
        <input type="date"
               name="start_date"
               class="form-control form-control-sm"
               value="{{ request('start_date', now()->startOfMonth()->format('Y-m-d')) }}">
    </div>

    <!-- Filter 2: Sampai Tanggal -->
    <div>
        <label class="form-label mb-1 small fw-bold">Sampai Tanggal</label>
        <input type="date"
               name="end_date"
               class="form-control form-control-sm"
               value="{{ request('end_date', now()->endOfMonth()->format('Y-m-d')) }}">
    </div>

    <!-- Filter 3: Business Unit -->
    <div style="min-width:190px;">
        <label class="form-label mb-1 small fw-bold">Business Unit</label>
        <select name="business_unit_id" class="form-select form-select-sm">
            <option value="">Semua Business Unit</option>
            @foreach($businessUnits as $businessUnit)
                <option value="{{ $businessUnit->id }}" {{ (string) request('business_unit_id') === (string) $businessUnit->id ? 'selected' : '' }}>
                    {{ $businessUnit->code }} - {{ $businessUnit->name }}
                </option>
            @endforeach
        </select>
    </div>

    <!-- Filter 4: Tipe / Sumber Jurnal -->
    <div style="min-width:200px;">
        <label class="form-label mb-1 small fw-bold">Tipe / Sumber Jurnal</label>
        <select name="source_type" class="form-select form-select-sm">
            <option value="">Semua Tipe Jurnal</option>
            <option value="sale" {{ request('source_type') == 'sale' ? 'selected' : '' }}>🛒 Penjualan</option>
            <option value="sales_return" {{ request('source_type') == 'sales_return' ? 'selected' : '' }}>🔄 Retur Penjualan</option>
            <option value="purchase" {{ request('source_type') == 'purchase' ? 'selected' : '' }}>📦 Pembelian</option>
            <option value="cash_in" {{ request('source_type') == 'cash_in' ? 'selected' : '' }}>💰 Penerimaan Kas</option>
            <option value="cash_out" {{ request('source_type') == 'cash_out' ? 'selected' : '' }}>💸 Pengeluaran Kas</option>
            <option value="manual" {{ request('source_type') == 'manual' ? 'selected' : '' }}>📝 Jurnal Umum / Manual</option>
        </select>
    </div>

    <!-- Tombol 1: Reload DataTables di Layar -->
    <button type="submit" class="btn btn-primary btn-sm px-3">
        Tampilkan
    </button>

    <!-- Tombol 2: Download Excel -->
    <button type="button" id="btn-export-excel" class="btn btn-success btn-sm px-3">
        Export Excel
    </button>
</form>
    </div>

    <!-- Tabel DataTables 9 Kolom -->
    <div class="table-responsive px-2 py-2">
        <table id="journal-datatable" class="table table-hover align-middle w-100 mb-0">
            <thead class="table-light text-muted small">
                <tr>
                    <th style="width:45px;" class="text-center">No</th>
                    <th>No. Jurnal</th>
                    <th>Tanggal</th>
                    <th>Business Unit</th>
                    <th>Tipe / Sumber Jurnal</th>
                    <th>No. Ref / Transaksi Asal</th>
                    <th>Keterangan / Memo Jurnal</th>
                    <th class="text-end">Total Balance (D/K)</th>
                    <th class="text-center" style="width:130px;">Aksi</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- =========================================================
     3. MODAL DETAIL VIEWER JURNAL (READ-ONLY)
     ========================================================= -->
<div class="modal fade" id="modalDetailJurnal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      
      <div class="modal-header bg-primary text-white py-2 px-3">
        <h6 class="modal-title fw-bold">
          📄 Detail Jurnal: <span id="viewJournalNo" class="font-monospace text-warning">-</span>
        </h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-3">
        <div class="row g-2 mb-3 p-2 bg-light rounded border">
          <div class="col-md-3">
            <small class="text-muted d-block fw-bold fs-8">TANGGAL</small>
            <span id="viewJournalDate" class="fw-bold text-dark">-</span>
          </div>
          <div class="col-md-3">
            <small class="text-muted d-block fw-bold fs-8">BUSINESS UNIT</small>
            <span id="viewBusinessUnit" class="badge bg-secondary">-</span>
          </div>
          <div class="col-md-3">
            <small class="text-muted d-block fw-bold fs-8">TIPE / SUMBER</small>
            <span id="viewSourceType" class="badge bg-info text-dark">-</span>
          </div>
          <div class="col-md-3">
            <small class="text-muted d-block fw-bold fs-8">STATUS EDIT</small>
            <span id="viewLockStatus" class="badge bg-light text-muted border">-</span>
          </div>
          <div class="col-12 mt-2">
            <small class="text-muted d-block fw-bold fs-8">KETERANGAN / MEMO</small>
            <span id="viewDescription" class="text-dark fw-semibold">-</span>
          </div>
        </div>

        <label class="form-label fw-bold text-dark text-uppercase small mb-1">RINCIAN ENTRI DEBIT & KREDIT</label>
        <div class="table-responsive border rounded mb-2">
          <table class="table table-sm table-striped align-middle mb-0">
            <thead class="table-light text-muted small">
              <tr>
                <th style="width: 40px;" class="text-center">#</th>
                <th style="width: 150px;">Kode Akun</th>
                <th>Nama Akun COA</th>
                <th class="text-end" style="width: 140px;">Debit (Rp)</th>
                <th class="text-end" style="width: 140px;">Kredit (Rp)</th>
              </tr>
            </thead>
            <tbody id="viewEntriesTable"></tbody>
            <tfoot class="table-light font-monospace fw-bold">
              <tr>
                <td colspan="3" class="text-end">TOTAL BALANCE</td>
                <td class="text-end text-primary" id="viewTotalDebit">Rp 0</td>
                <td class="text-end text-success" id="viewTotalCredit">Rp 0</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <div class="modal-footer bg-light py-1 px-3 d-flex justify-content-between">
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
          🖨️ Cetak Jurnal
        </button>
        <div>
          <button type="button" class="btn btn-warning btn-sm fw-bold text-dark me-1" id="btnEditFromDetail" style="display:none;">
            ✏️ Edit Jurnal
          </button>
          <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Tutup</button>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- =========================================================
     4. MODAL FORM INPUT & EDIT JURNAL UMUM (MANUAL)
     ========================================================= -->
<div class="modal fade" id="modalFormJurnal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow">
      
      <div class="modal-header bg-dark text-white py-2 px-3">
        <h6 class="modal-title fw-bold" id="modalFormJurnalTitle">
          📝 Input Jurnal Umum (Manual / Adjustment)
        </h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <form id="formJurnalUmum" autocomplete="off">
        @csrf
        <input type="hidden" id="formJournalId" name="journal_id">

        <div class="modal-body p-3">
          <div class="row g-2 mb-3">
            <div class="col-md-3">
              <label class="form-label text-muted small fw-bold mb-1">No. Jurnal</label>
              <input type="text" id="formJournalNo" class="form-control form-control-sm bg-light font-monospace fw-bold" value="Otomatis" readonly>
            </div>
            <div class="col-md-4">
              <label class="form-label text-muted small fw-bold mb-1">Tanggal Transaksi <span class="text-danger">*</span></label>
              <input type="date" id="formJournalDate" name="journal_date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required>
            </div>
            <div class="col-md-5">
              <label class="form-label text-muted small fw-bold mb-1">Business Unit <span class="text-danger">*</span></label>
              <select id="formBusinessUnitId" name="business_unit_id" class="form-select form-select-sm fw-bold" required>
    <option value="">-- Pilih Business Unit --</option>
    @foreach($businessUnits as $bu)
        <option value="{{ $bu->id }}">{{ $bu->code }} — {{ $bu->name }}</option>
    @endforeach
</select>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label text-muted small fw-bold mb-1">Keterangan / Memo Jurnal <span class="text-danger">*</span></label>
            <input type="text" id="formDescription" name="description" class="form-control form-control-sm" placeholder="Contoh: Penyesuaian Beban Penyusutan Aset Bulan September 2026" required>
          </div>

          <hr class="text-muted my-2">

          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label fw-bold text-dark text-uppercase small mb-0">BARIS JURNAL (DEBIT & KREDIT)</label>
            <button type="button" id="btnAddJournalRow" class="btn btn-outline-primary btn-sm fw-bold py-0 px-2">
              + Tambah Baris
            </button>
          </div>

          <div class="table-responsive border rounded mb-2">
            <table class="table table-sm table-bordered align-middle mb-0">
              <thead class="table-light text-muted small">
                <tr>
                  <th style="width: 45%;">Akun COA <span class="text-danger">*</span></th>
                  <th style="width: 25%;" class="text-end">Debit (Rp)</th>
                  <th style="width: 25%;" class="text-end">Kredit (Rp)</th>
                  <th style="width: 40px;" class="text-center">#</th>
                </tr>
              </thead>
              <tbody id="formEntriesBody"></tbody>
            </table>
          </div>

          <div id="balanceSummaryAlert" class="alert alert-secondary d-flex justify-content-between align-items-center py-1 px-3 mb-0">
            <span class="small fw-bold text-dark">TOTAL DEBIT vs KREDIT</span>
            <div class="font-monospace fw-bold fs-7">
              <span class="text-primary me-3" id="textSumDebit">D: Rp 0</span>
              <span class="text-success me-3" id="textSumCredit">K: Rp 0</span>
              <span id="badgeBalanceStatus" class="badge bg-danger">UNBALANCED</span>
            </div>
          </div>
        </div>

        <div class="modal-footer bg-light py-2 px-3">
          <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
          <button type="submit" id="btnSubmitJurnal" class="btn btn-dark btn-sm px-4 fw-bold">
            💾 Simpan Jurnal Umum
          </button>
        </div>
      </form>

    </div>
  </div>
</div>

<!-- =========================================================
     5. JAVASCRIPT ENGINE & DATATABLES INITIALIZATION
     ========================================================= -->
<script>
window.ERPNumber = {
    parse: function (value) {
        if (value === null || value === undefined || value === '') return 0;
        let str = String(value).replace(/\./g, '').replace(/,/g, '.');
        return parseFloat(str) || 0;
    },
    format: function (value) {
        if (value === null || value === undefined || value === '') return '';
        let num = this.parse(value);
        return new Intl.NumberFormat('id-ID').format(num);
    },
    rupiah: function (value) {
        let num = this.parse(value);
        return 'Rp ' + new Intl.NumberFormat('id-ID').format(num);
    }
};

document.addEventListener('DOMContentLoaded', function () {

    const coaOptionsList = @json($accounts ?? []);

    // -------------------------------------------------------------------------
    // A. DATATABLES SERVER-SIDE (9 KOLOM)
    // -------------------------------------------------------------------------
   const table = $('#journal-datatable').DataTable({
        processing: true,
        serverSide: true,
        pageLength: 15,
        lengthMenu: [[15, 25, 50, 100], [15, 25, 50, 100]],

        ajax: {
            url: @json(route('akuntansi.jurnal.data')),
            data: function (d) {
                d.start_date       = document.querySelector('[name="start_date"]').value;
                d.end_date         = document.querySelector('[name="end_date"]').value;
                d.business_unit_id = document.querySelector('[name="business_unit_id"]').value;
                d.source_type      = document.querySelector('[name="source_type"]').value;
            }
        },

        order: [[2, 'desc']],

        language: {
            search: 'Cari Jurnal:',
            lengthMenu: 'Tampilkan _MENU_',
            info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ data',
            infoEmpty: 'Tidak ada data',
            zeroRecords: 'Tidak ada jurnal yang sesuai',
            processing: 'Memuat data jurnal...',
            paginate: { first: '«', last: '»', next: '›', previous: '‹' }
        },

        columns: [
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center small text-muted',
                render: (data, type, row, meta) => meta.row + meta.settings._iDisplayStart + 1
            },
            { data: 'journal_no', className: 'font-monospace fw-bold text-primary' },
            { data: 'journal_date', className: 'text-nowrap' },
            {
                data: 'business_unit_name',
                render: data => `<span class="badge bg-secondary">${data || '-'}</span>`
            },
            {
                data: 'source_type',
                className: 'text-center',
                render: function (data) {
                    const st = String(data || 'manual').toLowerCase();
                    const badgeClass = {
                        manual: 'bg-dark',
                        sale: 'bg-primary',
                        sales_return: 'bg-warning text-dark',
                        purchase: 'bg-info text-dark',
                        cash_in: 'bg-success',
                        cash_out: 'bg-danger',
                    }[st] || 'bg-secondary';

                    return `<span class="badge ${badgeClass}">${st.toUpperCase()}</span>`;
                }
            },
            { data: 'source_id', className: 'font-monospace small text-muted', defaultContent: '-' },
            { data: 'description', className: 'text-wrap' },
            {
                data: 'total_debit',
                className: 'text-end font-monospace fw-bold',
                render: data => ERPNumber.rupiah(data)
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center',
                render: function (data, type, row) {
                    let btn = `<button type="button" class="btn btn-sm btn-outline-info py-0 px-1 me-1" onclick="openDetailJurnal(${row.id})" title="Detail"><i class="bi bi-eye"></i> Detail</button>`;

                    if (row.is_editable) {
                        btn += `
                            <button type="button" class="btn btn-sm btn-outline-warning py-0 px-1 me-1" onclick="openFormJurnal(${row.id})" title="Edit"><i class="bi bi-pencil"></i></button>
                            <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" onclick="deleteJurnal(${row.id})" title="Hapus"><i class="bi bi-trash"></i></button>
                        `;
                    } else {
                        btn += `<span class="badge bg-light text-muted border" title="Jurnal Otomatis Systems">🔒</span>`;
                    }
                    return btn;
                }
            }
        ]
    });

    // Event reload DataTables saat filter form berubah
    document.querySelectorAll('#form-filter-jurnal [name]').forEach(function (element) {
        element.addEventListener('change', function () {
            table.ajax.reload();
        });
    });

    document.getElementById('form-filter-jurnal').addEventListener('submit', function (e) {
        e.preventDefault();
        table.ajax.reload();
    });

    // -------------------------------------------------------------------------
    // B. DETAIL VIEWER MODAL
    // -------------------------------------------------------------------------
    const modalDetail = new bootstrap.Modal(document.getElementById('modalDetailJurnal'));

    window.openDetailJurnal = async function (id) {
        try {
            const response = await fetch(`/akuntansi/jurnal/${id}`);
            const result   = await response.json();

            if (!response.ok || !result.success) throw new Error(result.message || 'Gagal memuat detail.');

            const j = result.journal;
            document.getElementById('viewJournalNo').textContent    = j.journal_no;
            document.getElementById('viewJournalDate').textContent  = j.journal_date_formatted;
            document.getElementById('viewBusinessUnit').textContent = j.business_unit_name;
            document.getElementById('viewSourceType').textContent   = j.source_type.toUpperCase();
            document.getElementById('viewDescription').textContent  = j.description;

            const lockBadge = document.getElementById('viewLockStatus');
            const editBtn   = document.getElementById('btnEditFromDetail');

            if (j.is_editable) {
                lockBadge.className = 'badge bg-success';
                lockBadge.textContent = '✏️ Editable';
                editBtn.style.display = '';
                editBtn.onclick = () => { modalDetail.hide(); openFormJurnal(j.id); };
            } else {
                lockBadge.className = 'badge bg-light text-muted border';
                lockBadge.textContent = '🔒 Locked (System Auto)';
                editBtn.style.display = 'none';
            }

            const tbody = document.getElementById('viewEntriesTable');
            tbody.innerHTML = '';
            let totalD = 0, totalK = 0;

            j.entries.forEach((entry, idx) => {
                totalD += entry.debit;
                totalK += entry.credit;
                tbody.innerHTML += `
                    <tr>
                        <td class="text-center small text-muted">${idx + 1}</td>
                        <td class="font-monospace text-primary fw-bold">${entry.account_code}</td>
                        <td>${entry.account_name}</td>
                        <td class="text-end font-monospace">${entry.debit > 0 ? ERPNumber.format(entry.debit) : '-'}</td>
                        <td class="text-end font-monospace">${entry.credit > 0 ? ERPNumber.format(entry.credit) : '-'}</td>
                    </tr>
                `;
            });

            document.getElementById('viewTotalDebit').textContent  = ERPNumber.rupiah(totalD);
            document.getElementById('viewTotalCredit').textContent = ERPNumber.rupiah(totalK);

            modalDetail.show();
        } catch (err) { alert(err.message); }
    };

    // -------------------------------------------------------------------------
    // C. FORM INPUT & EDIT JURNAL UMUM
    // -------------------------------------------------------------------------
    const modalForm       = new bootstrap.Modal(document.getElementById('modalFormJurnal'));
    const formJurnal      = document.getElementById('formJurnalUmum');
    const formEntriesBody = document.getElementById('formEntriesBody');

    window.openFormJurnal = async function (id = null) {
        formJurnal.reset();
        formEntriesBody.innerHTML = '';

        if (id) {
            document.getElementById('modalFormJurnalTitle').textContent = '📝 Edit Jurnal Umum (Manual)';
            document.getElementById('formJournalId').value = id;

            try {
                const response = await fetch(`/akuntansi/jurnal/${id}`);
                const result   = await response.json();
                const j        = result.journal;

                document.getElementById('formJournalNo').value       = j.journal_no;
                document.getElementById('formJournalDate').value     = j.journal_date;
                document.getElementById('formBusinessUnitId').value = j.business_unit_id;
                document.getElementById('formDescription').value    = j.description;

                j.entries.forEach(entry => addFormRow(entry.account_id, entry.debit, entry.credit));
            } catch (err) { alert('Gagal memuat data jurnal.'); return; }
        } else {
            document.getElementById('modalFormJurnalTitle').textContent = '📝 Input Jurnal Umum (Manual / Adjustment)';
            document.getElementById('formJournalId').value = '';
            document.getElementById('formJournalNo').value = 'Otomatis';
            addFormRow();
            addFormRow();
        }

        recalculateBalanceGuard();
        modalForm.show();
    };

    function addFormRow(accountId = '', debit = 0, credit = 0) {
        const index = formEntriesBody.children.length;
        const tr    = document.createElement('tr');

        let optionsHtml = '<option value="">-- Pilih Akun COA --</option>';
        coaOptionsList.forEach(acc => {
            const selected = String(acc.id) === String(accountId) ? 'selected' : '';
            optionsHtml += `<option value="${acc.id}" ${selected}>${acc.code} — ${acc.name}</option>`;
        });

        tr.innerHTML = `
            <td>
                <select name="entries[${index}][account_id]" class="form-select form-select-sm entry-account" required>
                    ${optionsHtml}
                </select>
            </td>
            <td>
                <input type="text" name="entries[${index}][debit]" class="form-control form-control-sm text-end font-monospace entry-debit" value="${debit ? ERPNumber.format(debit) : '0'}" autocomplete="off">
            </td>
            <td>
                <input type="text" name="entries[${index}][credit]" class="form-control form-control-sm text-end font-monospace entry-credit" value="${credit ? ERPNumber.format(credit) : '0'}" autocomplete="off">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm border-0 py-0 px-1 btn-delete-row">&times;</button>
            </td>
        `;

        formEntriesBody.appendChild(tr);

        const inputD = tr.querySelector('.entry-debit');
        const inputK = tr.querySelector('.entry-credit');

        [inputD, inputK].forEach(input => {
            input.addEventListener('focus', function () {
                let v = ERPNumber.parse(this.value);
                this.value = v ? String(v) : '';
                this.select();
            });

            input.addEventListener('blur', function () {
                let v = ERPNumber.parse(this.value);
                this.value = ERPNumber.format(v);
                recalculateBalanceGuard();
            });

            input.addEventListener('input', function () {
                if (this === inputD && ERPNumber.parse(this.value) > 0) inputK.value = '0';
                else if (this === inputK && ERPNumber.parse(this.value) > 0) inputD.value = '0';
                recalculateBalanceGuard();
            });
        });

        tr.querySelector('.btn-delete-row').addEventListener('click', function () {
            if (formEntriesBody.children.length <= 2) {
                alert('Jurnal minimal membutuhkan 2 baris entri!');
                return;
            }
            tr.remove();
            recalculateBalanceGuard();
        });
    }

    document.getElementById('btnAddJournalRow').addEventListener('click', () => addFormRow());

    function recalculateBalanceGuard() {
        let totalD = 0, totalK = 0;
        document.querySelectorAll('.entry-debit').forEach(i => totalD += ERPNumber.parse(i.value));
        document.querySelectorAll('.entry-credit').forEach(i => totalK += ERPNumber.parse(i.value));

        document.getElementById('textSumDebit').textContent  = 'D: ' + ERPNumber.rupiah(totalD);
        document.getElementById('textSumCredit').textContent = 'K: ' + ERPNumber.rupiah(totalK);

        const badge = document.getElementById('badgeBalanceStatus');
        const isBalanced = Math.abs(totalD - totalK) < 0.01 && totalD > 0;

        if (isBalanced) {
            badge.className = 'badge bg-success';
            badge.textContent = 'BALANCE';
        } else {
            badge.className = 'badge bg-danger';
            badge.textContent = 'UNBALANCED';
        }
        return isBalanced;
    }

    // Submit Form (AJAX POST/PUT)
    formJurnal.addEventListener('submit', async function (e) {
        e.preventDefault();

        if (!recalculateBalanceGuard()) {
            alert('Jurnal tidak seimbang! Total Debit HARUS sama dengan Total Kredit.');
            return;
        }

        const journalId = document.getElementById('formJournalId').value;
        const url       = journalId ? `/akuntansi/jurnal/${journalId}` : '/akuntansi/jurnal';
        const formData  = new FormData(formJurnal);

        if (journalId) formData.append('_method', 'PUT');

        document.querySelectorAll('.entry-debit').forEach((input, i) => formData.set(`entries[${i}][debit]`, ERPNumber.parse(input.value)));
        document.querySelectorAll('.entry-credit').forEach((input, i) => formData.set(`entries[${i}][credit]`, ERPNumber.parse(input.value)));

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: formData
            });

            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Gagal menyimpan.');

            alert(result.message);
            modalForm.hide();
            table.ajax.reload(null, false);
        } catch (err) { alert(err.message); }
    });

    // Delete Jurnal Manual
    window.deleteJurnal = async function (id) {
        if (!confirm('Apakah Anda yakin ingin menghapus jurnal umum ini?')) return;

        try {
            const response = await fetch(`/akuntansi/jurnal/${id}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: new URLSearchParams({ '_method': 'DELETE' })
            });

            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Gagal menghapus.');

            alert(result.message);
            table.ajax.reload(null, false);
        } catch (err) { alert(err.message); }
    };
	
	// 1. Klik Tombol "Tampilkan" -> Reload Tabel DataTables
    document.getElementById('form-filter-jurnal').addEventListener('submit', function (e) {
        e.preventDefault();
        if (typeof table !== 'undefined') {
            table.ajax.reload(); // Reload isi DataTables di layar
        }
    });

    // 2. Klik Tombol "Export Excel" -> Unduh File Excel dengan Filter Terbaru
    document.getElementById('btn-export-excel').addEventListener('click', function () {
        const form     = document.getElementById('form-filter-jurnal');
        const formData = new FormData(form);
        const params   = new URLSearchParams(formData).toString();
        
        // Arahkan ke endpoint export
        window.location.href = "{{ route('akuntansi.jurnal.export') }}?" + params;
    });

});
</script>
@endsection
