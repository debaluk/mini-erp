@extends('layouts.app')
@section('content')
<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="card-title fw-bold mb-0 text-dark">Daftar Penerimaan Kas & Bank</h5>
            <small class="text-muted">Monitoring seluruh transaksi arus uang masuk (Sales POS, Pelunasan Piutang, & Penerimaan Umum)</small>
        </div>
        <div class="d-flex gap-2">
            <!-- Tombol Memicu Modal Pelunasan Piutang -->
            <button type="button" 
                    class="btn btn-warning btn-sm fw-bold" 
                    data-bs-toggle="modal" 
                    data-bs-target="#modalPelunasanPiutang">
                <i class="bi bi-plus-circle me-1"></i> + Pelunasan Piutang
            </button>

            <!-- Tombol Memicu Modal Penerimaan Umum -->
            <button type="button" 
                    class="btn btn-primary btn-sm" 
                    data-bs-toggle="modal" 
                    data-bs-target="#modalKasMasukUmum">
                <i class="bi bi-plus-circle me-1"></i> + Penerimaan Umum
            </button>
        </div>
    </div>

    <!-- Card Body: Form Filter Bar -->
    <div class="card-body border-bottom py-2 bg-light">
        <form method="GET"
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

            <div style="min-width:180px;">
                <label class="form-label mb-1 small fw-bold">Business Unit</label>
                <select name="unit_id" class="form-select form-select-sm">
                    <option value="">Semua Business Unit</option>
                    @foreach($businessUnits ?? [] as $bu)
                        <option value="{{ $bu->id }}" {{ request('unit_id') == $bu->id ? 'selected' : '' }}>
                            {{ $bu->nama }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div style="min-width:180px;">
                <label class="form-label mb-1 small fw-bold">Jenis Penerimaan</label>
                <select name="jenis_penerimaan" class="form-select form-select-sm">
                    <option value="">Semua Jenis Penerimaan</option>
                    <option value="POS">Penjualan</option>
                    <option value="PELUNASAN_PIUTANG">Pelunasan Piutang</option>
                    <option value="UMUM">Penerimaan Umum</option>
                </select>
            </div>

            <div style="min-width:180px;">
                <label class="form-label mb-1 small fw-bold">Kas / Bank</label>
                <select name="kas_bank_id" class="form-select form-select-sm">
                    <option value="">Semua Rekening</option>
                    <option value="KAS">Kas Laci / Kas Besar</option>
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

    <!-- Table Section -->
    <div class="table-responsive px-2 py-2">
        <table id="penerimaan-kas-bank-datatable"
               class="table table-hover align-middle w-100 mb-0">
            <thead class="table-light text-muted small">
                <tr>
                    <th style="width:45px;" class="text-center">No</th>
                    <th>No. Bukti</th>
                    <th>Tanggal</th>
                    <th>Tipe Penerimaan</th>
                    <th>Diterima Pada Kas/Bank</th>
                    <th>Dari / Sumber</th>
                    <th class="text-end">Total Nominal</th>
                    <th class="text-center" style="width:90px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <!-- DataTables AJAX / Server-side Rendering -->
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Form Penerimaan Umum -->
<div class="modal fade" id="modalKasMasukUmum" tabindex="-1" aria-labelledby="modalKasMasukUmumLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg">
      
      <!-- Modal Header -->
      <div class="modal-header bg-primary text-white py-3">
        <h5 class="modal-title fw-bold fs-6" id="modalKasMasukUmumLabel">
          📝 Form Penerimaan Kas & Bank Masuk Umum
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body p-4">
        
        <!-- Section 1: Header Info -->
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label text-muted small fw-bold mb-1">No. Voucher</label>
            <input type="text" value="CR/202609/0003" readonly class="form-control form-control-sm bg-light font-monospace">
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
              Diterima Pada (Kas/Bank) <span class="text-danger">*</span>
            </label>
            <select class="form-select form-select-sm fw-bold text-primary">
              <option value="1000201">1000201 - Bank BCA Operasional</option>
              <option value="1000102">1000102 - Kas Besar Utama</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted small fw-bold mb-1">No. Referensi / Bank</label>
            <input type="text" placeholder="Contoh: TRF-BCA-988212 / No. Slip" class="form-control form-control-sm">
          </div>
        </div>

        <hr class="text-muted my-3">

        <!-- Section 2: Dynamic Multi-Line Table -->
        <div class="mb-4">
          <label class="form-label fw-bold text-dark text-uppercase small mb-2">
            RINCIAN AKUN SUMBER / KREDIT
          </label>
          
          <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle mb-2">
              <thead class="table-light text-muted small">
                <tr>
                  <th style="width: 45%;">Akun COA Sumber (Kredit)</th>
                  <th>Keterangan Rincian</th>
                  <th style="width: 25%;" class="text-end">Nominal (Rp)</th>
                  <th style="width: 50px;" class="text-center">Aksi</th>
                </tr>
              </thead>
              <tbody id="gridAkunSumber">
                <tr>
                  <td>
                    <select class="form-select form-select-sm">
                      <option value="3000101">3000101 - Modal Disetor</option>
                      <option value="4000301">4000301 - Pendapatan Bunga Bank</option>
                    </select>
                  </td>
                  <td>
                    <input type="text" value="Setoran modal tambahan owner" class="form-control form-control-sm">
                  </td>
                  <td>
                    <input type="number" value="50000000" class="form-control form-control-sm text-end font-monospace">
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
        <div class="alert alert-primary d-flex justify-content-between align-items-center mb-0 py-3 px-3">
          <span class="fw-bold text-primary">TOTAL PENERIMAAN KAS/BANK</span>
          <span class="font-monospace fw-bold fs-5 text-primary">Rp 50.150.000</span>
        </div>

      </div>

      <!-- Modal Footer -->
      <div class="modal-footer bg-light px-4 py-2">
        <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-primary btn-sm px-3 fw-bold">💾 Simpan & Post Jurnal</button>
      </div>

    </div>
  </div>
</div>

<!-- Modal Form Pelunasan Piutang -->
<div class="modal fade" id="modalPelunasanPiutang" tabindex="-1" aria-labelledby="modalPelunasanPiutangLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg">
      
      <!-- Modal Header -->
      <div class="modal-header bg-warning text-white py-3">
        <h5 class="modal-title fw-bold fs-6" id="modalPelunasanPiutangLabel">
          💳 Form Pelunasan Piutang Pelanggan (Invoice Settlement)
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body p-4">
        
        <!-- Section 1: Header Info & Filter Pelanggan -->
        <div class="row g-3 mb-3">
          <div class="col-md-3">
            <label class="form-label text-muted small fw-bold mb-1">No. Bukti Pelunasan</label>
            <input type="text" value="OR/202609/0012" readonly class="form-control form-control-sm bg-light font-monospace">
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
              Pelanggan / Customer <span class="text-danger">*</span>
            </label>
            <select class="form-select form-select-sm fw-bold border-primary">
              <option value="">-- Pilih Pelanggan (Piutang Aktif) --</option>
              <option value="CUST-001" selected>Budiasa (Total AR: Rp 14.000)</option>
              <option value="CUST-002">CV Jaya Kontraktor Utama (Total AR: Rp 35.000.000)</option>
            </select>
          </div>
        </div>

        <div class="row g-3 mb-4">
          <div class="col-md-6">
            <label class="form-label text-muted small fw-bold mb-1">
              Diterima Pada (Rekening Kas/Bank) <span class="text-danger">*</span>
            </label>
            <select class="form-select form-select-sm fw-bold text-primary">
              <option value="1000201">1000201 - Bank BCA Operasional</option>
              <option value="1000102">1000102 - Kas Besar Utama</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label text-muted small fw-bold mb-1">No. Referensi / Bukti Transfer</label>
            <input type="text" placeholder="Contoh: TRF-BCA-889102 / Slip Setoran" class="form-control form-control-sm">
          </div>
        </div>

        <hr class="text-muted my-3">

        <!-- Section 2: Invoice Matching Table (Rincian Faktur Aktif) -->
        <div class="mb-4">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <label class="form-label fw-bold text-dark text-uppercase small mb-0">
              DAFTAR FAKTUR AKTIF (INVOICE MATCHING)
            </label>
            <span class="badge bg-info text-dark">Pelanggan Selected: <strong>Budiasa</strong></span>
          </div>
          
          <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle mb-2">
              <thead class="table-light text-muted small text-nowrap">
                <tr>
                  <th style="width: 40px;" class="text-center">Pilih</th>
                  <th>No. Faktur Asal</th>
                  <th>Tgl Faktur</th>
                  <th>Jatuh Tempo</th>
                  <th class="text-end">Nilai Faktur (Rp)</th>
                  <th class="text-end">Sisa Piutang (Rp)</th>
                  <th style="width: 200px;" class="text-end">Nominal Bayar (Rp)</th>
                  <th style="width: 80px;" class="text-center">Aksi Cepat</th>
                </tr>
              </thead>
              <tbody id="gridFakturAktif">
                <!-- Row Faktur 1 -->
                <tr>
                  <td class="text-center">
                    <input type="checkbox" class="form-check-input" id="chk1" checked>
                  </td>
                  <td class="font-monospace fw-bold text-primary">INV-20260926163608-CAOM</td>
                  <td>26/09/2026</td>
                  <td>30/09/2026</td>
                  <td class="text-end font-monospace">Rp 6.000</td>
                  <td class="text-end font-monospace text-danger fw-bold">Rp 6.000</td>
                  <td>
                    <input type="number" value="6000" class="form-control form-control-sm text-end font-monospace fw-bold border-success">
                  </td>
                  <td class="text-center">
                    <button type="button" class="btn btn-outline-success btn-sm py-0 px-2 text-nowrap fs-7">Full</button>
                  </td>
                </tr>
                <!-- Row Faktur 2 -->
                <tr>
                  <td class="text-center">
                    <input type="checkbox" class="form-check-input" id="chk2">
                  </td>
                  <td class="font-monospace fw-bold text-primary">INV-20260927042003-LON1</td>
                  <td>27/09/2026</td>
                  <td>30/09/2026</td>
                  <td class="text-end font-monospace">Rp 8.000</td>
                  <td class="text-end font-monospace text-danger fw-bold">Rp 8.000</td>
                  <td>
                    <input type="number" value="0" disabled class="form-control form-control-sm text-end font-monospace bg-light">
                  </td>
                  <td class="text-center">
                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 text-nowrap fs-7">Full</button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Section 3: Catatan & Total Summary Box -->
        <div class="row g-3 align-items-center">
          <div class="col-md-6">
            <label class="form-label text-muted small fw-bold mb-1">Catatan / Keterangan Pelunasan</label>
            <textarea rows="2" placeholder="Pelunasan faktur INV-... via transfer BCA Pak Budiasa" class="form-control form-control-sm"></textarea>
          </div>
          <div class="col-md-6">
            <div class="alert alert-success d-flex justify-content-between align-items-center mb-0 py-3 px-3 border-success">
              <div>
                <span class="d-block text-muted small fw-bold">TOTAL ALOKASI PEMBAYARAN</span>
                <small class="text-muted">Memotong Saldo Piutang Usaha (`1000301`)</small>
              </div>
              <span class="font-monospace fw-bold fs-4 text-success">Rp 6.000</span>
            </div>
          </div>
        </div>

      </div>

      <!-- Modal Footer -->
      <div class="modal-footer bg-light px-4 py-2">
        <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-warning btn-sm px-4 fw-bold">💾 Simpan & Post Jurnal</button>
      </div>

    </div>
  </div>
</div>


@endsection

@push('scripts')

@endpush