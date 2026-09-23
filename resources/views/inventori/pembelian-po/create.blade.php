@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Buat Purchase Order</h4>
        <div class="text-secondary small">Isi informasi pembelian dan detail barang.</div>
    </div>
    <a href="{{ route('inventori.pembelian-po') }}" class="btn btn-outline-secondary btn-sm">← Kembali</a>
</div>

<form id="purchaseOrderForm">
    @csrf

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white">
            <strong>Informasi Pembelian</strong>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">No. PO</label>
                    <input type="text" class="form-control form-control-sm" value="Otomatis" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Tanggal PO</label>
                    <input type="date" class="form-control form-control-sm" value="{{ now()->toDateString() }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Business Unit</label>
                    <select class="form-select form-select-sm" required>
                        <option value="">Pilih Business Unit</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white">
            <strong>Vendor</strong>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-lg-6">
                    <label class="form-label small fw-semibold">Pilih Vendor</label>
                    <select id="supplierSelect" class="form-select form-select-sm">
                        <option value="">Pilih vendor...</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}"
                                    data-name="{{ $supplier->name }}"
                                    data-phone="{{ $supplier->phone }}"
                                    data-address="{{ $supplier->address }}">
                                {{ $supplier->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-6">
                    <div class="small fw-semibold mb-2">Informasi Vendor</div>
                    <div id="supplierInfo" class="rounded border bg-light p-3 small text-secondary">
                        <div class="mb-1"><strong id="supplierName">-</strong></div>
                        <div id="supplierAddress">Alamat: -</div>
                        <div id="supplierPhone">No. HP: -</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Detail Barang</strong>
            <span class="small text-secondary">Pilih barang → satuan otomatis → qty → harga</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" id="poItemsTable">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:50px;">No</th>
                            <th style="min-width:280px;">Pilih Barang</th>
                            <th style="width:120px;">Satuan</th>
                            <th class="text-end" style="width:130px;">Qty</th>
                            <th class="text-end" style="width:160px;">Harga</th>
                            <th class="text-end" style="width:170px;">Subtotal</th>
                            <th class="text-center" style="width:55px;"></th>
                        </tr>
                    </thead>
                    <tbody id="poItemsBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white"><strong>Informasi Pembelian & Total</strong></div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-lg-7">
                    <label class="form-label small fw-semibold">Memo</label>
                    <textarea class="form-control form-control-sm mb-3" rows="4" placeholder="Catatan pembelian..."></textarea>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Term Pembayaran</label>
                            <select class="form-select form-select-sm">
                                <option value="cash">Tunai</option>
                                <option value="credit">Kredit</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Tgl Target Terima</label>
                            <input type="date" class="form-control form-control-sm">
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="border rounded p-3 bg-light">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal</span><strong id="totalSubtotal">Rp 0</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Diskon</span><strong>Rp 0</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>PPN</span><strong>Rp 0</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Biaya Lain</span><strong>Rp 0</strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-semibold">TOTAL</span>
                            <strong class="fs-5" id="grandTotal">Rp 0</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <a href="{{ route('inventori.pembelian-po') }}" class="btn btn-outline-secondary btn-sm">Batal</a>
        <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const products = @json($products);
    const body = document.getElementById('poItemsBody');
    const supplier = document.getElementById('supplierSelect');

    const money = value => new Intl.NumberFormat('id-ID', {
        style: 'currency', currency: 'IDR', maximumFractionDigits: 0
    }).format(Number(value || 0));

    const productOptions = () => {
        const options = products.map(p =>
            '<option value="' + p.id + '" data-unit="' + (p.unit_code || '') + '" data-price="' + (p.cost_price || 0) + '">' +
            escapeHtml(p.code + ' - ' + p.name) + '</option>'
        ).join('');
        return '<option value="">Pilih barang...</option>' + options;
    };

    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'
    }[char]));

    function addRow() {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="text-center row-number"></td>
            <td>
                <select class="form-select form-select-sm item-product">
                    ${productOptions()}
                </select>
            </td>
            <td><input type="text" class="form-control form-control-sm item-unit" readonly></td>
            <td><input type="number" min="0" step="any" class="form-control form-control-sm text-end item-qty" placeholder="0"></td>
            <td><input type="number" min="0" step="1" class="form-control form-control-sm text-end item-price" placeholder="0"></td>
            <td class="text-end fw-semibold item-subtotal">Rp 0</td>
            <td class="text-center">
                <button type="button" class="btn btn-outline-danger btn-sm item-delete" title="Hapus row">×</button>
            </td>
        `;
        body.appendChild(row);
        bindRow(row);
        renumber();
    }

    function bindRow(row) {
        const productSelect = row.querySelector('.item-product');
        const unit = row.querySelector('.item-unit');
        const qty = row.querySelector('.item-qty');
        const price = row.querySelector('.item-price');

        productSelect.addEventListener('change', () => {
            const option = productSelect.selectedOptions[0];
            unit.value = option?.dataset.unit || '';
            if (!price.value && option?.dataset.price) price.value = Number(option.dataset.price);
            calculate(row);
            ensureTrailingRow();
        });

        [qty, price].forEach(input => input.addEventListener('input', () => {
            calculate(row);
            ensureTrailingRow();
        }));

        row.querySelector('.item-delete').addEventListener('click', () => {
            row.remove();
            if (!body.children.length) addRow();
            renumber();
            calculateTotals();
        });
    }

    function calculate(row) {
        const qty = Number(row.querySelector('.item-qty').value || 0);
        const price = Number(row.querySelector('.item-price').value || 0);
        row.querySelector('.item-subtotal').textContent = money(qty * price);
        calculateTotals();
    }

    function ensureTrailingRow() {
        const rows = [...body.children];
        const last = rows[rows.length - 1];
        if (last && last.querySelector('.item-product').value) addRow();
    }

    function calculateTotals() {
        let total = 0;
        body.querySelectorAll('tr').forEach(row => {
            total += Number(row.querySelector('.item-qty')?.value || 0) *
                     Number(row.querySelector('.item-price')?.value || 0);
        });
        document.getElementById('totalSubtotal').textContent = money(total);
        document.getElementById('grandTotal').textContent = money(total);
    }

    function renumber() {
        [...body.children].forEach((row, index) => {
            row.querySelector('.row-number').textContent = index + 1;
        });
    }

    supplier.addEventListener('change', () => {
        const option = supplier.selectedOptions[0];
        document.getElementById('supplierName').textContent = option?.dataset.name || '-';
        document.getElementById('supplierAddress').textContent = 'Alamat: ' + (option?.dataset.address || '-');
        document.getElementById('supplierPhone').textContent = 'No. HP: ' + (option?.dataset.phone || '-');
    });

    document.getElementById('purchaseOrderForm').addEventListener('submit', event => {
        event.preventDefault();
        window.erpNotify('UI Purchase Order sudah siap. Penyimpanan transaksi akan dihubungkan pada tahap engine PO.', 'info');
    });

    addRow();
});
</script>
@endpush
