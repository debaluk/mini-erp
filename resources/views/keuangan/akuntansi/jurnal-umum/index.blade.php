@extends('layouts.app')
@section('content')
<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-1">Daftar Jurnal (General Ledger Register)</h4>
        <div class="text-secondary small">
            Monitoring Seluruh Jurnal Otomatis Sistem & Jurnal Umum Manual
        </div>
    </div>

    <div>
        <!-- Tombol Memicu Modal Form Jurnal Umum (Manual) -->
        <button type="button"
                class="btn btn-secondary fw-bold shadow-sm"
                data-bs-toggle="modal"
                data-bs-target="#modalJurnalUmum">
            <i class="bi bi-journal-plus me-1"></i> + Tambah Jurnal Umum (Manual)
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
               <div style="min-width:200px;">
                <label class="form-label mb-1 small fw-bold">Tipe / Sumber Jurnal</label>
                <select name="source_type" class="form-select form-select-sm">
                    <option value="">Semua Tipe Jurnal</option>
                    <option value="MANUAL" {{ request('source_type') == 'MANUAL' ? 'selected' : '' }}>📝 Jurnal Umum (Manual)</option>
                    <option value="POS" {{ request('source_type') == 'POS' ? 'selected' : '' }}>🛒 Penjualan POS Retail</option>
                    <option value="SALES" {{ request('source_type') == 'SALES' ? 'selected' : '' }}>📄 Penjualan Invoice Tempo</option>
                    <option value="SALES_RETURN" {{ request('source_type') == 'SALES_RETURN' ? 'selected' : '' }}>🔄 Retur Penjualan</option>
                    <option value="PURCHASE" {{ request('source_type') == 'PURCHASE' ? 'selected' : '' }}>📦 Pembelian Supplier</option>
                    <option value="PAYMENT_AR" {{ request('source_type') == 'PAYMENT_AR' ? 'selected' : '' }}>💳 Pelunasan Piutang</option>
                    <option value="PAYMENT_AP" {{ request('source_type') == 'PAYMENT_AP' ? 'selected' : '' }}>💳 Pelunasan Hutang</option>
                    <option value="CASH_IN" {{ request('source_type') == 'CASH_IN' ? 'selected' : '' }}>💵 Kas/Bank Masuk Umum</option>
                    <option value="CASH_OUT" {{ request('source_type') == 'CASH_OUT' ? 'selected' : '' }}>💸 Kas/Bank Keluar Umum</option>
                    <option value="TRANSFER" {{ request('source_type') == 'TRANSFER' ? 'selected' : '' }}>🔄 Mutasi Internal Kas/Bank</option>
                    <option value="PRODUCTION" {{ request('source_type') == 'PRODUCTION' ? 'selected' : '' }}>🏗️ Produksi BUASO</option>
                    <option value="STOCK_ADJUSTMENT" {{ request('source_type') == 'STOCK_ADJUSTMENT' ? 'selected' : '' }}>📊 Penyesuaian Stok</option>
                </select>
            </div>
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
        <table id="jurnal-gabungan-datatable"
               class="table table-hover align-middle w-100 mb-0">
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
            <tbody>
                <!-- Row 1: Contoh Jurnal Umum (Manual) -> BISA DI-EDIT -->
                <tr>
                    <td class="text-center">1</td>
                    <td class="font-monospace fw-bold text-dark">JRN/202609/0015</td>
                    <td>27/09/2026</td>
                    <td><span class="badge bg-secondary">Retail Toko Bangunan</span></td>
                    <td>
                        <span class="badge bg-dark">📝 Jurnal Umum (Manual)</span>
                    </td>
                    <td class="font-monospace text-muted small">-</td>
                    <td>Penyusutan Kendaraan Armada Bulan September 2026</td>
                    <td class="text-end font-monospace fw-bold text-primary">Rp 2.500.000</td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <button title="Lihat Detail" class="btn btn-outline-secondary py-0 px-2"><i class="bi bi-eye"></i></button>
                            <!-- Tombol Edit AKTIF Khusus Jurnal Umum -->
                            <button title="Edit Jurnal Umum" class="btn btn-outline-warning py-0 px-2 text-dark"><i class="bi bi-pencil-square"></i></button>
                            <button title="Hapus" class="btn btn-outline-danger py-0 px-2"><i class="bi bi-trash"></i></button>
                        </div>
                    </td>
                </tr>

                <!-- Row 2: Contoh Jurnal Otomatis POS -> TERKUNCI (TIDAK BISA DI-EDIT) -->
                <tr>
                    <td class="text-center">2</td>
                    <td class="font-monospace fw-bold text-primary">JRN-POS-202609-0089</td>
                    <td>26/09/2026</td>
                    <td><span class="badge bg-secondary">Retail Toko Bangunan</span></td>
                    <td>
                        <span class="badge bg-info text-dark">🛒 POS Retail (Auto)</span>
                    </td>
                    <td class="font-monospace text-primary small">POS/202609/0089</td>
                    <td>Penjualan POS Retail Nota #POS/202609/0089 an Pelanggan Umum</td>
                    <td class="text-end font-monospace fw-bold text-primary">Rp 185.000</td>
                    <td class="text-center">
                        <div class="d-flex align-items-center justify-content-center gap-1">
                            <button title="Lihat Detail" class="btn btn-sm btn-outline-secondary py-0 px-2 me-1"><i class="bi bi-eye"></i> Detail</button>
                            <!-- Status Terkunci (Read Only) -->
                            <span class="badge bg-light text-muted border px-2 py-1 fs-8" title="Jurnal Otomatis Engine - Tidak Dapat Di-edit">
                                🔒 Locked
                            </span>
                        </div>
                    </td>
                </tr>

                <!-- Row 3: Contoh Jurnal Otomatis Mutasi -> TERKUNCI (TIDAK BISA DI-EDIT) -->
                <tr>
                    <td class="text-center">3</td>
                    <td class="font-monospace fw-bold text-primary">JRN-TRF-202609-0005</td>
                    <td>26/09/2026</td>
                    <td><span class="badge bg-primary">Holding / Pusat</span></td>
                    <td>
                        <span class="badge bg-warning text-dark">🔄 Mutasi Internal (Auto)</span>
                    </td>
                    <td class="font-monospace text-primary small">TRF/202609/0005</td>
                    <td>Transfer Kas Besar ke Bank BCA Operasional</td>
                    <td class="text-end font-monospace fw-bold text-primary">Rp 10.006.500</td>
                    <td class="text-center">
                        <div class="d-flex align-items-center justify-content-center gap-1">
                            <button title="Lihat Detail" class="btn btn-sm btn-outline-secondary py-0 px-2 me-1"><i class="bi bi-eye"></i> Detail</button>
                            <!-- Status Terkunci (Read Only) -->
                            <span class="badge bg-light text-muted border px-2 py-1 fs-8" title="Jurnal Otomatis Engine - Tidak Dapat Di-edit">
                                🔒 Locked
                            </span>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Bootstrap 5 Container: Form Jurnal Umum Non-Operasional -->
<div class="modal fade" id="modalJurnalUmum" tabindex="-1" aria-labelledby="modalJurnalUmumLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg">
      
      <!-- Modal Header -->
      <div class="modal-header bg-secondary text-white py-3">
        <h5 class="modal-title fw-bold fs-6" id="modalJurnalUmumLabel">
          📝 Form Jurnal Umum (Non-Operasional / Adjustment)
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <!-- Modal Body -->
      <div class="modal-body p-4">
        
        <!-- Header Info -->
        <div class="row g-3 mb-3">
          <div class="col-md-4">
            <label class="form-label text-muted small fw-bold mb-1">No. Jurnal</label>
            <input type="text" value="JRN/202609/0015" readonly class="form-control form-control-sm bg-light font-monospace">
          </div>
          <div class="col-md-4">
            <label class="form-label text-muted small fw-bold mb-1">Tanggal Transaksi</label>
            <input type="date" value="2026-09-27" class="form-control form-control-sm">
          </div>
          <div class="col-md-4">
            <label class="form-label text-muted small fw-bold mb-1">Business Unit <span class="text-danger">*</span></label>
            <select class="form-select form-select-sm fw-bold">
              <option value="1">Retail Toko Bangunan</option>
              <option value="2">Produksi Batako BUASO</option>
              <option value="3">Holding / Pusat</option>
            </select>
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label text-muted small fw-bold mb-1">Keterangan / Memo Jurnal <span class="text-danger">*</span></label>
          <input type="text" placeholder="Contoh: Penyusutan Kendaraan Armada Bulan September 2026" class="form-control form-control-sm">
        </div>

        <hr class="text-muted my-3">

        <!-- Multi-line Journal Entries Grid -->
        <div class="mb-3">
          <label class="form-label fw-bold text-dark text-uppercase small mb-2">RINCIAN BARIS JURNAL (DEBIT / KREDIT)</label>
          <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle mb-2">
              <thead class="table-light text-muted small">
                <tr>
                  <th style="width: 40%;">Akun COA</th>
                  <th style="width: 25%;" class="text-end">Debit (Rp)</th>
                  <th style="width: 25%;" class="text-end">Kredit (Rp)</th>
                  <th style="width: 45px;" class="text-center">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>
                    <select class="form-select form-select-sm">
                      <option>6000303 - Beban Penyusutan Kendaraan</option>
                      <option>1000703 - Akum. Penyusutan Kendaraan</option>
                    </select>
                  </td>
                  <td><input type="number" value="2500000" class="form-control form-control-sm text-end font-monospace"></td>
                  <td><input type="number" value="0" class="form-control form-control-sm text-end font-monospace"></td>
                  <td class="text-center"><button class="btn btn-outline-danger btn-sm border-0 py-0 px-2 fw-bold">&times;</button></td>
                </tr>
                <tr>
                  <td>
                    <select class="form-select form-select-sm">
                      <option selected>1000703 - Akum. Penyusutan Kendaraan</option>
                    </select>
                  </td>
                  <td><input type="number" value="0" class="form-control form-control-sm text-end font-monospace"></td>
                  <td><input type="number" value="2500000" class="form-control form-control-sm text-end font-monospace"></td>
                  <td class="text-center"><button class="btn btn-outline-danger btn-sm border-0 py-0 px-2 fw-bold">&times;</button></td>
                </tr>
              </tbody>
            </table>
          </div>
          <button type="button" class="btn btn-outline-secondary btn-sm fw-bold">+ Tambah Baris Jurnal</button>
        </div>

        <!-- System Guard Balance Summary -->
        <div class="alert alert-secondary d-flex justify-content-between align-items-center mb-0 py-2 px-3">
          <span class="small fw-bold text-dark">TOTAL DEBIT vs KREDIT</span>
          <div class="font-monospace fw-bold">
            <span class="text-primary me-3">D: Rp 2.500.000</span>
            <span class="text-success me-3">K: Rp 2.500.000</span>
            <span class="badge bg-success">BALANCE</span>
          </div>
        </div>

      </div>

      <!-- Modal Footer -->
      <div class="modal-footer bg-light px-4 py-2">
        <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
        <button type="button" class="btn btn-dark btn-sm px-4 fw-bold">💾 Simpan Jurnal Umum</button>
      </div>

    </div>
  </div>
</div>
@endsection