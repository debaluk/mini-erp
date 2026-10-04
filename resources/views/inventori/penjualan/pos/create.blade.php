@extends('layouts.app')

@section('content')
<style>
    html,
    body {
        height: 100%;
        overflow: hidden !important;
        background: #fff !important;
    }

    body > nav.navbar,
    body > footer,
    .breadcrumb,
    .app-sidebar {
        display: none !important;
    }

    main {
        height: 100vh !important;
        min-height: 100vh !important;
        overflow: hidden !important;
        padding: 0 !important;
        margin: 0 !important;
        background: #fff !important;
    }

    .pos-screen {
        height: calc(100vh - 24px);
        min-height: calc(100vh - 24px);
        padding: 0 !important;
        margin: 0 0 24px 0 !important;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: #fff !important;
    }

    .pos-card {
        flex: 1 1 auto;
        min-height: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        border: 0 !important;
        border-radius: .5rem !important;
        box-shadow: 0 .125rem .25rem rgba(0, 0, 0, .075) !important;
    }

    .pos-card-body {
        flex: 1 1 auto;
        min-height: 0;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        padding: 12px !important;
    }

    .pos-main-row {
        flex: 1 1 auto;
        min-height: 0;
        overflow: hidden;
    }

    .pos-left-panel,
    .pos-right-panel {
        min-height: 0;
        height: 100%;
    }

    .pos-left-panel {
        overflow-y: auto;
    }

    .pos-right-panel {
        display: flex;
        flex-direction: column;
    }

    .pos-right-panel .pos-cart {
        flex: 1 1 auto;
    }

    .pos-cart {
        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
    }

    .pos-cart .barcode-input,
    .pos-cart .choose-product {
        display: none !important;
    }

    .pos-summary {
        flex: 0 0 auto;
    }

    .pos-total {
        font-size: 22pt;
        line-height: 1;
        font-weight: 800;
        letter-spacing: .02em;
    }

    .pos-total-card {
        background: #212529;
        color: #fff;
        border-radius: .5rem;
        padding: 12px 16px;
    }

    .pos-action {
        flex: 0 0 52px;
        height: 52px;
        min-height: 52px;
        padding: 6px 12px !important;
        position: relative;
        z-index: 5;
        margin: 0 !important;
        border-top: 1px solid var(--bs-border-color);
    }

    @media (max-width: 767.98px) {
        .pos-screen {
            padding: 0;
        }

        .pos-main-row {
            overflow-y: auto;
        }

        .pos-left-panel,
        .pos-right-panel {
            height: auto;
            min-height: auto;
        }

        .pos-right-panel .pos-cart {
            max-height: 45vh;
        }

        .pos-total {
            font-size: 20pt;
        }
    }
</style>
<div class="pos-screen">
<div class="card pos-card">
    <div class="card-body pos-card-body">
        <div class="row g-3 pos-main-row">
            <div class="col-md-4 pos-left-panel">
                <div class="border rounded p-3 h-100 d-flex flex-column">
                    <h6 class="mb-3"><i class="bi bi-receipt me-2"></i>Transaksi</h6>

                    <div class="mb-3">
                        <label class="form-label mb-1">Tanggal</label>
                        <x-date-input-id name="sale_date" :value="now()->toDateString()" required />
                    </div>

                    <div class="mb-3">
                        <label class="form-label mb-1">Pelanggan</label>
                        <div class="input-group">
                            <input id="customerSearch" class="form-control" value="Umum" placeholder="Pilih pelanggan..." readonly>
                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#customerModal">Pilih</button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label mb-1">Nomor</label>
                        <input class="form-control" value="Otomatis" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label mb-1">Unit Bisnis</label>
                        <select id="unitSelect" class="form-select" required disabled>
                            @foreach($units as $u)
                                <option value="{{ $u->id }}" @selected((int) $defaultUnitId === (int) $u->id)>
                                    {{ $u->name }}
                                </option>
                            @endforeach
                        </select>
                        @if($units->isEmpty())
                            <div class="form-text text-danger">User belum memiliki alokasi Business Unit.</div>
                        @endif
                    </div>

                    <div class="pt-3 mt-3 border-top">
                        <div class="small fw-semibold text-secondary mb-2">Shortcut Kasir</div>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="small text-secondary"><kbd>F2</kbd> Pilih Barang</span>
                            <span class="small text-secondary"><kbd>F4</kbd> Cara Bayar</span>
                            <span class="small text-secondary"><kbd>F5</kbd> Refresh</span>
                            <span class="small text-secondary"><kbd>F8</kbd> Bayar</span>
                            <span class="small text-secondary"><kbd>Esc</kbd> Tutup</span>
                            <span class="small text-secondary"><kbd>Enter</kbd> Barcode</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8 pos-right-panel">
                <div class="d-flex align-items-center mb-2">
                    <div>
                        <h6 class="mb-0"><i class="bi bi-receipt me-2"></i>Detil Penjualan</h6>
                        <div class="small text-secondary">Scan barcode atau ketik kode barang pada kolom di bawah.</div>
                    </div>
                </div>

                <div class="input-group input-group-sm mb-2">
                    <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                    <input type="text" id="posBarcodeSearch" class="form-control" autocomplete="off" placeholder="Scan / ketik barcode barang..." autofocus>
                    <button type="button" class="btn btn-outline-primary" id="posChooseProduct">Pilih Barang</button>
                </div>

                <div class="table-responsive pos-cart border rounded">
                    <table class="table table-sm table-hover align-middle mb-0" id="salesDetailTable">
                        <thead class="table-primary">
                            <tr>
                                <th style="width:60px">Kode Barang</th>
                                <th style="min-width:220px">Nama Barang</th>
                                <th style="width:120px">Satuan</th>
                                <th style="width:150px" class="text-end">Qty</th>
                                <th style="width:160px" class="text-end">Harga</th>
                                <th style="width:140px" class="text-end">Diskon</th>
                                <th style="width:170px" class="text-end">Subtotal</th>
                                <th style="width:90px" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="detailBody"></tbody>
                    </table>
                </div>


                                    <div class="mt-auto pt-3 border-top pos-summary">
                        <div class="d-flex justify-content-between py-1 text-end">
                            <span class="flex-grow-1">Subtotal</span>
                            <strong id="subtotalAmount" style="min-width:160px">Rp 0</strong>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-1 text-end">
                            <span class="flex-grow-1">Diskon (Rp)</span>
                            <input id="discountInput" type="number" min="0" class="form-control text-end" style="max-width:160px" value="0">
                        </div>
                        <div class="pos-total-card mt-2 d-flex justify-content-between align-items-center gap-3">
                            <div class="small text-uppercase fw-semibold opacity-75 text-nowrap">TOTAL BAYAR</div>
                            <div id="totalAmount" class="pos-total text-end">Rp 0</div>
                        </div>
                        <div class="row align-items-center mt-2 g-2">
                            <div class="col-sm-4 text-end fw-semibold">Cara Bayar</div>
                            <div class="col-sm-8">
                                <select id="paymentMethod" class="form-select">
                                    <option value="Tunai">Tunai</option>
                                    <option value="Transfer">Transfer</option>
                                    <option value="QRIS">QRIS</option>
                                </select>
                            </div>
                        </div>
                    </div>
            </div>
        </div>
</div>
    </div>

    <div class="card-footer pos-action d-flex justify-content-between align-items-center gap-2">
        <div class="small text-secondary">
            <span class="fw-semibold">Retail</span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('pos') }}" class="btn btn-outline-secondary">[ESC] BATAL</a>
            <button type="button" id="saveSales" class="btn btn-primary px-4">BAYAR</button>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="customerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-primary bg-opacity-10 text-primary border-bottom">
                <h6 class="modal-title"><i class="bi bi-person me-2"></i>Pilih Customer</h6>
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

<div class="modal fade" id="productModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-primary bg-opacity-10 text-primary border-bottom">
                <h5 class="modal-title"><i class="bi bi-box-seam me-2"></i>Pilih Barang</h5>
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
                        <thead class="table-primary">
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
        <div class="modal-content border-0 shadow-sm">
            <div class="modal-header bg-primary bg-opacity-10 text-primary border-bottom">
                <h5 class="modal-title" id="saleSavedModalLabel">
                    <i class="bi bi-check-circle me-2"></i>POS Berhasil
                </h5>
            </div>

            <div class="modal-body">
                POS berhasil disimpan.<br>
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
    'mode' => 'pos',
    'requireCustomer' => false,
    'allowCredit' => false,
    'products' => $productCatalog,
    'storeUrl' => route('pos.penjualan.store'),
    'indexUrl' => route('pos'),
    'createUrl' => route('pos'),
    'printUrlTemplate' => route('pos.penjualan.print', ['id' => '__ID__']),
]) !!}</script>
<script src="{{ asset('js/sales-create.js') }}"></script>
@endpush
@endsection