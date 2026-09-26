@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Penjualan Baru</h4>
        
    </div>
    <a href="{{ route('inventori.penjualan') }}" class="btn btn-outline-secondary">← Kembali</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-6">
                <div class="d-flex align-items-center">
                    <label class="form-label mb-0 me-3 text-nowrap" style="min-width:80px">Tanggal</label>
                    <input type="date" class="form-control" value="{{ now()->toDateString() }}" readonly>
                </div>
            </div>

            <div class="col-md-6">
                <div class="d-flex align-items-center">
                    <label class="form-label mb-0 me-3 text-nowrap" style="min-width:80px">Pelanggan</label>
                    <div class="input-group">
                        <input id="customerSearch" class="form-control" placeholder="Pilih pelanggan..." readonly>
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#customerModal">Pilih</button>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="d-flex align-items-center">
                    <label class="form-label mb-0 me-3 text-nowrap" style="min-width:80px">Nomor</label>
                    <input class="form-control" value="Otomatis" readonly>
                </div>
            </div>

            <div class="col-md-6">
                <div class="d-flex align-items-center">
                    <label class="form-label mb-0 me-3 text-nowrap" style="min-width:80px">Unit Bisnis</label>
                    <select id="unitSelect" class="form-select" required>
                        <option value="">Pilih Unit Bisnis</option>
                        @foreach($units as $u)
                            <option value="{{ $u->id }}" @selected((int) $defaultUnitId === (int) $u->id)>
                                {{ $u->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @if($units->isEmpty())
                    <div class="form-text text-danger ms-5">User belum memiliki alokasi Business Unit.</div>
                @endif
            </div>
        </div>

        

        <div class="mb-2">
            <h6 class="mb-0 mt-3">Detail Penjualan</h6>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered align-middle mb-0" id="salesDetailTable">
                <thead class="table-light">
                    <tr>
                        <th style="width:130px">Kode Barang</th>
                        <th style="min-width:280px">Nama Barang</th>
                        <th style="width:120px">Satuan</th>
                        <th style="width:110px" class="text-end">Qty</th>
                        <th style="width:160px" class="text-end">Harga</th>
                        <th style="width:140px" class="text-end">Diskon</th>
                        <th style="width:170px" class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody id="detailBody"></tbody>
            </table>
        </div>

        

        <div class="row g-4">
            <div class="col-md-6">
                <h6 class="mt-3">Informasi Pelanggan</h6>
                <div class="small text-secondary">Pelanggan</div>
                <div id="customerInfo" class="fw-semibold mb-3">-</div>

                <div class="small text-secondary">Piutang Sebelumnya</div>
                <div id="customerBalance" class="fw-semibold mb-2">Rp 0</div>

                <div class="alert alert-warning py-2 d-none" id="arrears">⚠ Ada tunggakan sebelumnya</div>

                <label class="form-label">Memo</label>
                <textarea id="memoInput" class="form-control" rows="3"></textarea>
            </div>

            <div class="col-md-6">
                <h6 class="mt-3">Informasi Transaksi</h6>

                <div class="d-flex justify-content-between py-1">
                    <span>Subtotal</span>
                    <strong id="subtotalAmount">Rp 0</strong>
                </div>

                <div class="d-flex justify-content-between align-items-center py-1">
                    <span>Diskon (Rp)</span>
                    <input id="discountInput" type="number" min="0" class="form-control text-end" style="max-width:160px" value="0">
                </div>

                <div class="d-flex justify-content-between mt-2 pt-2 fs-5">
                    <strong>TOTAL</strong>
                    <strong id="totalAmount">Rp 0</strong>
                </div>

                <div class="row g-2 mt-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">Cara Bayar</label>
                        <select id="paymentMethod" class="form-select">
                            <option value="Tunai">Tunai</option>
                            <option value="Transfer">Transfer</option>
                            <option value="QRIS">QRIS</option>
                            <option value="Kredit / Bon">Kredit / Bon</option>
                        </select>
                    </div>

                    <div id="dueDate" class="col-md-6 d-none">
                        <label class="form-label">Jatuh Tempo</label>
                        <input type="date" class="form-control">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card-footer d-flex justify-content-end gap-2">
        <a href="{{ route('inventori.penjualan') }}" class="btn btn-outline-secondary">Batal</a>
        <button type="button" id="saveSales" class="btn btn-primary">Simpan Penjualan</button>
    </div>
</div>

<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Pilih Pelanggan</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input id="customerFilter" class="form-control mb-3" placeholder="Ketik nama pelanggan...">

                <div class="list-group" id="customerList">
                    @foreach($customers as $c)
                        <button
                            type="button"
                            class="list-group-item list-group-item-action customer-choice d-flex justify-content-between align-items-center"
                            data-id="{{ $c->id }}"
                            data-name="{{ $c->name }}"
                            data-balance="{{ $c->outstanding }}"
                        >
                            <span>{{ $c->name }}</span>
                            <span class="small text-secondary">Piutang: Rp {{ number_format($c->outstanding, 0, ',', '.') }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pilih Barang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <input type="text"
                           id="productFilter"
                           class="form-control"
                           placeholder="Cari kode, nama, barcode, atau SKU...">
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Barang</th>
                                <th>Barcode</th>
                                <th>Satuan</th>
                                <th class="text-end">Harga</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="productList"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="saleSavedModal" tabindex="-1"
     aria-labelledby="saleSavedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="saleSavedModalLabel">
                    Penjualan Berhasil
                </h5>
            </div>

            <div class="modal-body">
                Penjualan berhasil disimpan.<br>
                Cetak penjualan sekarang?
            </div>

            <div class="modal-footer">
                <button type="button"
                        class="btn btn-outline-secondary"
                        id="saleSavedNo">
                    Tidak
                </button>

                <button type="button"
                        class="btn btn-primary"
                        id="saleSavedYes">
                    Ya
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script type="application/json" id="salesCreateData">{!! json_encode([
    'mode' => 'tempo',
    'requireCustomer' => true,
    'allowCredit' => true,
    'mode' => 'tempo',
    'requireCustomer' => true,
    'allowCredit' => true,
    'mode' => 'tempo',
    'requireCustomer' => true,
    'allowCredit' => true,
    'products' => $productCatalog,
    'storeUrl' => route('inventori.penjualan.store'),
    'indexUrl' => route('inventori.penjualan'),
    'createUrl' => route('inventori.penjualan.create'),
]) !!}</script>
<script src="{{ asset('js/sales-create.js') }}"></script>
@endpush
