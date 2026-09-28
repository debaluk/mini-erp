@extends('layouts.app')
@section('content')
<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-1">Pengeluaran Kas & Bank</h4>
        <div class="text-secondary small">
            Daftar & Monitoring Seluruh Arus Uang Keluar
        </div>
    </div>

    <div class="d-flex gap-2">
        <!-- Tombol Memicu Modal Pelunasan Hutang Supplier -->
        <button type="button"
                class="btn btn-warning fw-bold text-dark"
                data-bs-toggle="modal"
                data-bs-target="#modalPelunasanHutang">
            <i class="bi bi-journal-arrow-down me-1"></i> + Pelunasan Hutang
        </button>

        <!-- Tombol Memicu Modal Pengeluaran Umum -->
        <button type="button"
                class="btn btn-danger fw-bold"
                data-bs-toggle="modal"
                data-bs-target="#modalKasKeluarUmum">
            <i class="bi bi-dash-circle me-1"></i> + Pengeluaran Umum
        </button>
    </div>
</div>


<div class="card shadow-sm border-0 bg-white" >
    <div class="card-body border-bottom py-2 bg-light bg-white">
        <form method="GET"
              action=""
              class="d-flex align-items-end gap-2 flex-nowrap overflow-auto py-1"
              style="white-space: nowrap;">

            <div>
                <label class="form-label mb-1 small fw-bold">Mulai Tanggal</label>
                <input type="date"
                       name="start_date"
                       class="form-control form-control-sm"
                       value="{{ request('start_date', now()->startOfMonth()->format('Y-m-d')) }}">
            </div>

            <div>
                <label class="form-label mb-1 small fw-bold">Sampai Tanggal</label>
                <input type="date"
                       name="end_date"
                       class="form-control form-control-sm"
                       value="{{ request('end_date', now()->endOfMonth()->format('Y-m-d')) }}">
            </div>

            <div style="min-width:190px;">
                <label class="form-label mb-1 small fw-bold">Business Unit</label>
                <select name="unit_id" class="form-select form-select-sm">
                    <option value="">Semua Business Unit</option>
                    
                </select>
            </div>

            <div style="min-width:190px;">
                <label class="form-label mb-1 small fw-bold">Jenis Pengeluaran</label>
                <select name="jenis_pengeluaran" class="form-select form-select-sm">
                    <option value="">Semua Jenis Pengeluaran</option>
                    <option value="PELUNASAN_HUTANG">Pelunasan Hutang Supplier</option>
                    <option value="BEBAN_OPERASIONAL">Beban Operasional / Umum</option>
                </select>
            </div>

            <div style="min-width:190px;">
                <label class="form-label mb-1 small fw-bold">Kas / Bank</label>
                <select name="kas_bank_id" class="form-select form-select-sm">
                    <option value="">Semua Kas & Bank</option>
                    <option value="KAS">Kas Besar / Kas Kecil</option>
                    <option value="BANK">Bank Operasional</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary btn-sm px-3 fw-bold">
                Tampilkan
            </button>

            <a href=""
               class="btn btn-success btn-sm px-3 fw-bold">
                Export Excel
            </a>
        </form>
    </div>

    <!-- DataTables Table Container -->
    <div class="table-responsive px-2 py-2">
        <table id="pengeluaran-kas-bank-datatable"
               class="table table-hover align-middle w-100 mb-0">
            <thead class="table-light text-muted small">
                <tr>
                    <th style="width:55px;" class="text-center">No</th>
                    <th>No. Bukti (PV)</th>
                    <th>Tanggal</th>
                    <th>Tipe Pengeluaran</th>
                    <th>Dibayar Dari (Kas/Bank)</th>
                    <th>Kepada / Penerima</th>
                    <th class="text-end">Total Nominal</th>
                    <th class="text-center" style="width:80px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <!-- Data di-load via DataTables Server-Side / AJAX -->
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Bootstrap 5 Container: Form Pelunasan Hutang Supplier -->
<div class="modal fade" id="modalPelunasanHutang" tabindex="-1" aria-labelledby="modalPelunasanHutangLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg">
      
      <!-- Modal Header -->
      <div class="modal-header bg-warning text-dark py-3">
        <h5 class="modal-title fw-bold fs-6" id="modalPelunasanHutangLabel">
          💳 Form Pelunasan Hutang Supplier (Accounts Payable Settlement)
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body p-4">
        
        <!-- Section 1: Header Info & Filter Supplier -->
        <div class="row g-3 mb-3">
          <div class="col-md-3">
            <label class="form-label text-muted small fw-bold mb-1">No. Bukti Pembayaran</label>
            <input type="text" value="PV/202609/0008" readonly class="form-control form-control-sm bg-light font-monospace">
          </div>
          <div class="col-md-3">
            <label class="form-label text-muted small fw-bold mb-1">Tanggal Bayar</label>
            <input type="date" value="2026-09-27" class="form-control form-control-sm">
          </div>
          <div class="col-md-3">
            <label class="form-label text-muted small fw-bold mb-1">Business Unit</label>
            <select class="form-select form-select-sm">
              <option selected>Retail Toko Bangunan</option>
              <option>Produksi Batako BUASO</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label text-muted small fw-bold mb-1">
              Supplier / Pemasok <span class="text-danger">*</span>
            </label>
            <select class="form-select form-select-sm fw-bold border-warning">
              <option value="">-- Pilih Supplier (Hutang Aktif) --</option>
              <option value="SUPP-001" selected>PT Semen Indonesia Utama (Hutang: Rp 45.000.000)</option>
              <option value="SUPP-002">CV Pasir Alam Mandiri (Hutang: Rp 12.500.000)</option>
            </select>
          </div>
        </div>

        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label text-muted small fw-bold mb-1">
              Dibayar Dari (Rekening Kas/Bank) <span class="text-danger">*</span>
            </label>
            <select class="form-select form-select-sm fw-bold text-primary">
              <option value="1000201">1000201 - Bank BCA Operasional</option>
              <option value="1000102">1000102 - Kas Besar Utama</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted small fw-bold mb-1">No. Referensi / Bukti Transfer</label>
            <input type="text" placeholder="Contoh: TRF-BCA-992011 / No. Cek" class="form-control form-control-sm">
          </div>
        </div>

        <hr class="text-muted my-3">

        <!-- Section 2: Invoice Matching Table (Rincian Faktur PO Aktif) -->
        <div class="mb-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="form-label fw-bold text-dark text-uppercase small mb-0">
              DAFTAR FAKTUR PEMBELIAN AKTIF (INVOICE MATCHING)
            </label>
            <span class="badge bg-warning text-dark">Supplier Selected: <strong>PT Semen Indonesia Utama</strong></span>
          </div>
          
          <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle mb-2">
              <thead class="table-light text-muted small text-nowrap">
                <tr>
                  <th style="width: 40px;" class="text-center">Pilih</th>
                  <th>No. Faktur Supplier / PO</th>
                  <th>Tgl Faktur</th>
                  <th>Jatuh Tempo</th>
                  <th class="text-end">Nilai Faktur (Rp)</th>
                  <th class="text-end">Sisa Hutang (Rp)</th>
                  <th style="width: 200px;" class="text-end">Nominal Bayar (Rp)</th>
                  <th style="width: 80px;" class="text-center">Aksi Cepat</th>
                </tr>
              </thead>
              <tbody id="gridFakturHutang">
                <tr>
                  <td class="text-center">
                    <input type="checkbox" class="form-check-input" id="chkHutang1" checked>
                  </td>
                  <td class="font-monospace fw-bold text-primary">PO-20260915-0012 / INV-SIU-881</td>
                  <td>15/09/2026</td>
                  <td>30/09/2026</td>
                  <td class="text-end font-monospace">Rp 25.000.000</td>
                  <td class="text-end font-monospace text-danger fw-bold">Rp 25.000.000</td>
                  <td>
                    <input type="number" value="25000000" class="form-control form-control-sm text-end font-monospace fw-bold border-success">
                  </td>
                  <td class="text-center">
                    <button type="button" class="btn btn-outline-success btn-sm py-0 px-2 text-nowrap fs-7">Full</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Section 3: Summary Total -->
        <div class="row g-3 align-items-center">
          <div class="col-md-6">
            <label class="form-label text-muted small fw-bold mb-1">Catatan / Keterangan</label>
            <textarea rows="2" placeholder="Pelunasan PO Semen Indonesia via Transfer BCA" class="form-control form-control-sm"></textarea>
          </div>
          <div class="col-md-6">
            <div class="alert alert-warning d-flex justify-content-between align-items-center mb-0 py-3 px-3 border-warning">
              <div>
                <span class="d-block text-dark small fw-bold">TOTAL ALOKASI PEMBAYARAN HUTANG</span>
                <small class="text-muted">Memotong Saldo Hutang Usaha (`2000101`)</small>
              </div>
              <span class="font-monospace fw-bold fs-4 text-dark">Rp 25.000.000</span>
            </div>
          </div>
        </div>

      </div>

      <!-- Modal Footer -->
      <div class="modal-footer bg-light px-4 py-2">
        <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-warning btn-sm px-4 fw-bold text-dark">💾 Simpan & Post Jurnal</button>
      </div>

    </div>
  </div>
</div>


<!-- Modal Bootstrap 5 Container: Form Pengeluaran Umum -->
<div class="modal fade" id="modalKasKeluarUmum" tabindex="-1" aria-labelledby="modalKasKeluarUmumLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg">
      
      <!-- Modal Header -->
      <div class="modal-header bg-danger text-white py-3">
        <h5 class="modal-title fw-bold fs-6" id="modalKasKeluarUmumLabel">
          📝 Form Pengeluaran Kas & Bank Umum (Voucher PV)
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body p-4">
        
        <!-- Section 1: Header Info -->
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label text-muted small fw-bold mb-1">No. Voucher</label>
            <input type="text" value="PV/202609/0009" readonly class="form-control form-control-sm bg-light font-monospace">
          </div>
          <div class="col-md-4">
            <label class="form-label text-muted small fw-bold mb-1">Tanggal Transaksi</label>
            <input type="date" value="2026-09-27" class="form-control form-control-sm">
          </div>
          <div class="col-md-4">
            <label class="form-label text-muted small fw-bold mb-1">Business Unit</label>
            <select class="form-select form-select-sm">
              <option selected>Retail Toko Bangunan</option>
              <option>Produksi Batako BUASO</option>
            </select>
          </div>
        </div>

        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label text-muted small fw-bold mb-1">
              Dibayar Dari (Kas/Bank) <span class="text-danger">*</span>
            </label>
            <select class="form-select form-select-sm fw-bold text-danger">
              <option value="1000102">1000102 - Kas Besar Utama</option>
              <option value="1000201">1000201 - Bank BCA Operasional</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted small fw-bold mb-1">Penerimab/ Dibayar Kepada</label>
            <input type="text" placeholder="Contoh: PLN / Toko ATK / Nama Karyawan" class="form-control form-control-sm">
          </div>
        </div>

        <hr class="text-muted my-3">

        <!-- Section 2: Dynamic Multi-Line Table -->
        <div class="mb-4">
          <label class="form-label fw-bold text-dark text-uppercase small mb-2">
            RINCIAN AKUN BEBAN / ALOKASI DEBIT
          </label>
          
          <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle mb-2">
              <thead class="table-light text-muted small">
                <tr>
                  <th style="width: 45%;">Akun COA Tujuan (Debit)</th>
                  <th>Keterangan Rincian</th>
                  <th style="width: 25%;" class="text-end">Nominal (Rp)</th>
                  <th style="width: 50px;" class="text-center">Aksi</th>
                </tr>
              </thead>
              <tbody id="gridAkunBeban">
                <tr>
                  <td>
                    <select class="form-select form-select-sm">
                      <option value="6000101">6000101 - Beban Listrik, Air & Internet</option>
                      <option value="6000102">6000102 - Beban Bahan Bakar & Transport Fleet</option>
                      <option value="1000303">1000303 - Piutang / Kasbon Karyawan</option>
                    </select>
                  </td>
                  <td>
                    <input type="text" value="Pembayaran token listrik PLN Toko" class="form-control form-control-sm">
                  </td>
                  <td>
                    <input type="number" value="1500000" class="form-control form-control-sm text-end font-monospace">
                  </td>
                  <td class="text-center">
                    <button type="button" class="btn btn-outline-danger btn-sm border-0 py-0 px-2 fw-bold">&times;</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          
          <button type="button" class="btn btn-outline-secondary btn-sm fw-bold">
            + Tambah Baris Akun
          </button>
        </div>

        <!-- Section 3: Total Summary Box -->
        <div class="alert alert-danger d-flex justify-content-between align-items-center mb-0 py-3 px-3">
          <span class="fw-bold text-danger">TOTAL PENGELUARAN KAS/BANK</span>
          <span class="font-monospace fw-bold fs-5 text-danger">Rp 1.500.000</span>
        </div>

      </div>

      <!-- Modal Footer -->
      <div class="modal-footer bg-light px-4 py-2">
        <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-danger btn-sm px-3 fw-bold">💾 Simpan & Post Jurnal</button>
      </div>

    </div>
  </div>
</div>

@endsection