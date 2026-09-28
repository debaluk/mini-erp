@extends('layouts.app')
@section('content')
<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-1">Buku Besar (General Ledger)</h4>
        <div class="text-secondary small">
           Rincian Mutasi Kronologis Akun & Saldo Berjalan
        </div>
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
                <label class="form-label mb-1 small fw-bold">Pilih Akun COA</label>
                <select name="account_id" class="form-select form-select-sm fw-bold text-primary">
                    <option value="">-- Pilih Akun (Level 3) --</option>
                    <option value="1000101" {{ request('account_id') == '1000101' ? 'selected' : '' }}>1000101 - Kas Kecil / Laci POS</option>
                    <option value="1000102" {{ request('account_id') == '1000102' ? 'selected' : '' }}>1000102 - Kas Besar Utama</option>
                    <option value="1000201" {{ request('account_id') == '1000201' ? 'selected' : '' }}>1000201 - Bank BCA Operasional</option>
                    <option value="1000301" {{ request('account_id') == '1000301' ? 'selected' : '' }}>1000301 - Piutang Usaha</option>
                    <option value="1000401" {{ request('account_id') == '1000401' ? 'selected' : '' }}>1000401 - Persediaan Barang Dagangan</option>
                    <option value="2000101" {{ request('account_id') == '2000101' ? 'selected' : '' }}>2000101 - Hutang Usaha Supplier</option>
                    <option value="4000101" {{ request('account_id') == '4000101' ? 'selected' : '' }}>4000101 - Penjualan Barang Retail</option>
                    <option value="5000101" {{ request('account_id') == '5000101' ? 'selected' : '' }}>5000101 - HPP Penjualan Retail</option>
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
    <!-- Card Ringkasan Saldo Akun (Summary Cards) -->
<div class="row g-2 mb-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-white p-3">
            <small class="text-muted fw-bold d-block mb-1">SALDO AWAL PERIODE</small>
            <span class="font-monospace fw-bold fs-6 text-secondary">Rp 120.500.000</span>
            <small class="text-muted fs-8">Per Tanggal: 01/09/2026</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-white p-3 border-start border-4 border-primary">
            <small class="text-muted fw-bold d-block mb-1">TOTAL MUTASI DEBIT (+)</small>
            <span class="font-monospace fw-bold fs-6 text-primary">Rp 56.156.000</span>
            <small class="text-muted fs-8">Total Masuk Periode Ini</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-white p-3 border-start border-4 border-danger">
            <small class="text-muted fw-bold d-block mb-1">TOTAL MUTASI KREDIT (-)</small>
            <span class="font-monospace fw-bold fs-6 text-danger">Rp 26.506.500</span>
            <small class="text-muted fs-8">Total Keluar Periode Ini</small>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-primary text-white p-3">
            <small class="text-white-50 fw-bold d-block mb-1">SALDO AKHIR BERJALAN</small>
            <span class="font-monospace fw-bold fs-5">Rp 150.149.500</span>
            <small class="text-white-50 fs-8">Posisi Normal: DEBIT</small>
        </div>
    </div>
</div>

<!-- Card Tabel Detail Buku Besar -->
<div class="card shadow-sm border-0">
    <div class="table-responsive px-2 py-2">
        <table id="buku-besar-datatable" class="table table-hover align-middle w-100 mb-0">
            <thead class="table-light text-muted small">
                <tr>
                    <th style="width:40px;" class="text-center">No</th>
                    <th style="width:90px;">Tanggal</th>
                    <th>No. Jurnal</th>
                    <th>Business Unit</th>
                    <th>Ref / Sumber Transaksi</th>
                    <th>Keterangan / Memo Jurnal</th>
                    <th class="text-end" style="width:130px;">Debit (Rp)</th>
                    <th class="text-end" style="width:130px;">Kredit (Rp)</th>
                    <th class="text-end" style="width:150px;">Saldo Berjalan (Rp)</th>
                    <th class="text-center" style="width:50px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <!-- Row Saldo Awal Pembuka -->
                <tr class="table-secondary fw-bold">
                    <td class="text-center">-</td>
                    <td>01/09/2026</td>
                    <td class="font-monospace">-</td>
                    <td><span class="badge bg-dark">Konsolidasi</span></td>
                    <td>SALDO_AWAL</td>
                    <td>Saldo Awal Akun Per 01/09/2026</td>
                    <td class="text-end font-monospace">-</td>
                    <td class="text-end font-monospace">-</td>
                    <td class="text-end font-monospace">Rp 120.500.000</td>
                    <td class="text-center">-</td>
                </tr>

                <!-- Row Mutasi 1: Pelunasan Piutang Pak Budiasa -->
                <tr>
                    <td class="text-center">1</td>
                    <td>27/09/2026</td>
                    <td class="font-monospace fw-bold text-primary">JRN-OR-202609-0012</td>
                    <td><span class="badge bg-secondary">Retail Toko</span></td>
                    <td><small class="text-primary font-monospace">OR/202609/0012</small></td>
                    <td>Pelunasan Piutang Invoice #INV-... Pak Budiasa</td>
                    <td class="text-end font-monospace fw-bold text-primary">Rp 6.000</td>
                    <td class="text-end font-monospace text-muted">0</td>
                    <td class="text-end font-monospace fw-bold">Rp 120.506.000</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-secondary py-0 px-1" title="Lihat Detail Jurnal"><i class="bi bi-eye"></i></button>
                    </td>
                </tr>

                <!-- Row Mutasi 2: Penerimaan Umum Setoran Modal -->
                <tr>
                    <td class="text-center">2</td>
                    <td>27/09/2026</td>
                    <td class="font-monospace fw-bold text-primary">JRN-CR-202609-0003</td>
                    <td><span class="badge bg-secondary">Retail Toko</span></td>
                    <td><small class="text-primary font-monospace">CR/202609/0003</small></td>
                    <td>Setoran modal tambahan owner & bunga bank</td>
                    <td class="text-end font-monospace fw-bold text-primary">Rp 50.150.000</td>
                    <td class="text-end font-monospace text-muted">0</td>
                    <td class="text-end font-monospace fw-bold">Rp 170.656.000</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-secondary py-0 px-1" title="Lihat Detail Jurnal"><i class="bi bi-eye"></i></button>
                    </td>
                </tr>

                <!-- Row Mutasi 3: Pelunasan Hutang Supplier Semen -->
                <tr>
                    <td class="text-center">3</td>
                    <td>27/09/2026</td>
                    <td class="font-monospace fw-bold text-primary">JRN-PV-202609-0008</td>
                    <td><span class="badge bg-secondary">Retail Toko</span></td>
                    <td><small class="text-primary font-monospace">PV/202609/0008</small></td>
                    <td>Pelunasan PO PT Semen Indonesia Utama</td>
                    <td class="text-end font-monospace text-muted">0</td>
                    <td class="text-end font-monospace fw-bold text-danger">Rp 20.500.000</td>
                    <td class="text-end font-monospace fw-bold">Rp 150.156.000</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-secondary py-0 px-1" title="Lihat Detail Jurnal"><i class="bi bi-eye"></i></button>
                    </td>
                </tr>

                <!-- Row Mutasi 4: Biaya Admin Bank (Transfer) -->
                <tr>
                    <td class="text-center">4</td>
                    <td>27/09/2026</td>
                    <td class="font-monospace fw-bold text-primary">JRN-TRF-202609-0005</td>
                    <td><span class="badge bg-primary">Holding</span></td>
                    <td><small class="text-primary font-monospace">TRF/202609/0005</small></td>
                    <td>Biaya admin bank transfer dari Kas Besar</td>
                    <td class="text-end font-monospace text-muted">0</td>
                    <td class="text-end font-monospace fw-bold text-danger">Rp 6.500</td>
                    <td class="text-end font-monospace fw-bold">Rp 150.149.500</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-secondary py-0 px-1" title="Lihat Detail Jurnal"><i class="bi bi-eye"></i></button>
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