@extends('layouts.app')
@section('content')
<div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
    <div>
        <h4 class="mb-1">Mutasi & Transfer Internal</h4>
        <div class="text-secondary small">
            Daftar & Monitoring Pemindahan Dana Antar-Rekening Kas & Bank
        </div>
    </div>

    <div>
        <!-- Tombol Memicu Modal Form Mutasi / Transfer Internal -->
        <button type="button"
                class="btn btn-info fw-bold text-dark shadow-sm"
                data-bs-toggle="modal"
                data-bs-target="#modalMutasiKasBank">
            <i class="bi bi-arrow-left-right me-1"></i> + Transfer / Mutasi Internal
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
        <table id="mutasi-internal-datatable"
               class="table table-hover align-middle w-100 mb-0">
            <thead class="table-light text-muted small">
                <tr>
                    <th style="width:45px;" class="text-center">No</th>
                    <th>No. Bukti</th>
                    <th>Tanggal</th>
                    <th>Kas/Bank Asal (Pengirim & BU)</th>
                    <th>Kas/Bank Tujuan (Penerima & BU)</th>
                    <th class="text-end">Nominal TF</th>
                    <th class="text-end">Admin Bank & Skema</th>
                    <th class="text-end">Total Dipotong</th>
                    <th class="text-center" style="width:80px;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <!-- Contoh Dummy Data Row (Rendered via Server-Side / AJAX) -->
                <tr>
                    <td class="text-center">1</td>
                    <td class="font-monospace fw-bold text-primary">TRF/202609/0005</td>
                    <td>27/09/2026</td>
                    <td>
                        <span class="d-block fw-bold text-dark">1000102 - Kas Besar</span>
                        <small class="badge bg-secondary">Retail Toko Bangunan</small>
                    </td>
                    <td>
                        <span class="d-block fw-bold text-dark">1000201 - Bank BCA</span>
                        <small class="badge bg-primary">Holding Company</small>
                    </td>
                    <td class="text-end font-monospace fw-bold text-dark">Rp 10.000.000</td>
                    <td class="text-end font-monospace text-muted small">
                        Rp 6.500<br>
                        <span class="badge bg-info text-dark fs-8">Menambah Pengirim</span>
                    </td>
                    <td class="text-end font-monospace fw-bold text-danger">Rp 10.006.500</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-secondary py-0 px-2">Detail</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Bootstrap 5 Container: Form Mutasi / Transfer Internal Kas & Bank (FIN-03) -->
<div class="modal fade" id="modalMutasiKasBank" tabindex="-1" aria-labelledby="modalMutasiKasBankLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      
      <!-- Modal Header -->
      <div class="modal-header bg-info text-dark py-3">
        <h5 class="modal-title fw-bold fs-6" id="modalMutasiKasBankLabel">
          🔄 Form Transfer / Mutasi Internal Kas & Bank (FIN-03)
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <!-- Modal Body Form -->
      <form id="formMutasiKasBank" action="" method="POST">
        @csrf
        <div class="modal-body p-4">
          
          <!-- Row 1: Informasi Dasar -->
          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label text-muted small fw-bold mb-1">No. Bukti / Ref</label>
              <input type="text" name="transfer_no" value="TRF/{{ date('Ym') }}/Oto" readonly class="form-control form-control-sm bg-light font-monospace fw-bold">
            </div>
            <div class="col-md-4">
              <label class="form-label text-muted small fw-bold mb-1">Tanggal Transaksi <span class="text-danger">*</span></label>
              <input type="date" name="transfer_date" value="{{ date('Y-m-d') }}" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-4">
              <label class="form-label text-muted small fw-bold mb-1">Penanggung Jawab / User</label>
              <input type="text" value="{{ auth()->user()->name ?? 'Kasir / Finance' }}" readonly class="form-control form-control-sm bg-light">
            </div>
          </div>

          <hr class="text-muted my-3">

          <!-- Row 2: Pemilihan Kas / Bank Asal & Tujuan -->
          <div class="row g-3 mb-3">
            <!-- Kas/Bank Asal (Pengirim) -->
            <div class="col-md-6">
              <div class="p-3 bg-light rounded border border-danger-subtle">
                <label class="form-label text-danger small fw-bold mb-1">
                  <i class="bi bi-box-arrow-up-right me-1"></i> KAS / BANK ASAL (PENGIRIM) <span class="text-danger">*</span>
                </label>
                <select name="from_account_id" id="fromAccountId" class="form-select form-select-sm fw-bold text-dark mb-2" required>
                  <option value="">-- Pilih Rekening Sumber --</option>
                  <optgroup label="Holding / Pusat">
                    <option value="1000102_1">1000102 - Kas Besar Utama (Holding)</option>
                    <option value="1000201_1">1000201 - Bank BCA Operasional (Holding)</option>
                  </optgroup>
                  <optgroup label="BU Retail Toko Bangunan">
                    <option value="1000101_2">1000101 - Kas Laci POS (Retail Toko)</option>
                    <option value="1000102_2">1000102 - Kas Brankas Toko (Retail Toko)</option>
                  </optgroup>
                  <optgroup label="BU Produksi Batako BUASO">
                    <option value="1000101_3">1000101 - Kas Operasional Pabrik (BUASO)</option>
                  </optgroup>
                </select>
               
              </div>
            </div>

            <!-- Kas/Bank Tujuan (Penerima) -->
            <div class="col-md-6">
              <div class="p-3 bg-light rounded border border-success-subtle">
                <label class="form-label text-success small fw-bold mb-1">
                  <i class="bi bi-box-arrow-in-down-left me-1"></i> KAS / BANK TUJUAN (PENERIMA) <span class="text-danger">*</span>
                </label>
                <select name="to_account_id" id="toAccountId" class="form-select form-select-sm fw-bold text-dark mb-2" required>
                  <option value="">-- Pilih Rekening Tujuan --</option>
                  <optgroup label="Holding / Pusat">
                    <option value="1000201_1">1000201 - Bank BCA Operasional (Holding)</option>
                    <option value="1000202_1">1000202 - Bank Mandiri Utama (Holding)</option>
                  </optgroup>
                  <optgroup label="BU Retail Toko Bangunan">
                    <option value="1000101_2">1000101 - Kas Laci POS / Operasional (Retail Toko)</option>
                  </optgroup>
                  <optgroup label="BU Produksi Batako BUASO">
                    <option value="1000101_3">1000101 - Kas Kecil Operasional (BUASO)</option>
                  </optgroup>
                </select>
              
              </div>
            </div>
          </div>

          <!-- Row 3: Input Nominal Transfer & Biaya Admin -->
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label text-dark small fw-bold mb-1">
                Nominal Ditransfer (Rp) <span class="text-danger">*</span>
              </label>
              <input type="text" name="amount" id="mutasiNominal" class="form-control form-control-sm text-end font-monospace fw-bold fs-6 text-primary" placeholder="0" autocomplete="off" required>
            </div>
            <div class="col-md-6">
              <label class="form-label text-dark small fw-bold mb-1">
                Biaya Admin Bank (Rp) <small class="text-muted fw-normal">(Opsional)</small>
              </label>
              <input type="text" name="admin_fee" id="mutasiAdmin" class="form-control form-control-sm text-end font-monospace" placeholder="0" autocomplete="off" value="0">
            </div>
          </div>

          <!-- Row 4: Choice Option Skema Pembebanan Biaya Admin -->
          <div class="p-3 bg-light rounded border mb-3">
            <label class="form-label text-dark small fw-bold mb-2">
              <i class="bi bi-sliders me-1"></i> Skema Pembebanan Biaya Admin Bank:
            </label>
            <div class="d-flex gap-4">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="skema_admin" id="skemaPengirim" value="PENGIRIM" checked>
                <label class="form-check-label small fw-bold text-dark" for="skemaPengirim">
                  Menambah Pengirim <small class="text-muted d-block">(Rekening tujuan menerima dana bersih full)</small>
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="skema_admin" id="skemaPenerima" value="PENERIMA">
                <label class="form-check-label small fw-bold text-dark" for="skemaPenerima">
                  Memotong Penerima <small class="text-muted d-block">(Rekening tujuan dipotong admin bank)</small>
                </label>
              </div>
            </div>
          </div>

          <!-- Row 5: Dynamic Live Summary Box Jurnal -->
          <div id="summaryBoxMutasi" class="alert alert-info border-info mb-3 p-3 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="small fw-bold text-dark">DITERIMA DI REKENING TUJUAN (D)</span>
              <span id="textNettDiterima" class="font-monospace fw-bold text-success fs-6">Rp 0</span>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-1 text-muted small">
              <span>Beban Administrasi Bank (D)</span>
              <span id="textBebanAdmin" class="font-monospace fw-bold">Rp 0</span>
            </div>
            <hr class="my-2 border-info">
            <div class="d-flex justify-content-between align-items-center fw-bold text-dark">
              <span>TOTAL KAS DIPOTONG DARI PENGIRIM (K)</span>
              <span id="textTotalDipotong" class="font-monospace text-danger fs-6">Rp 0</span>
            </div>
          </div>

          <!-- Row 6: Keterangan / Memo -->
          <div>
            <label class="form-label text-muted small fw-bold mb-1">Keterangan / Catatan Transfer <span class="text-danger">*</span></label>
            <textarea name="memo" id="mutasiMemo" rows="2" class="form-control form-control-sm" placeholder="Contoh: Setoran omzet tunai toko shift 1 ke rekening BCA utama" required></textarea>
          </div>

        </div>

        <!-- Modal Footer -->
        <div class="modal-footer bg-light px-4 py-2">
          <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Batal</button>
          <button type="submit" id="btnSimpanMutasi" class="btn btn-info btn-sm px-4 fw-bold text-dark">
            💾 Simpan Transfer / Mutasi
          </button>
        </div>
      </form>

    </div>
  </div>
</div>

@endsection