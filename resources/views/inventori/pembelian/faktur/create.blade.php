@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold">Input Faktur Pembelian</h3>
            <div class="text-secondary small">PO tidak diproses melalui penerimaan. Non-PO dapat langsung diterima melalui ReceiptController.</div>
        </div>
        <a href="{{ route('inventori.pembelian.index') }}" class="btn btn-outline-secondary btn-sm">Kembali</a>
    </div>

    <form action="{{ route('inventori.pembelian.store') }}" method="POST" id="form-invoice">
        @csrf

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">No. Faktur Sistem</label>
                        <input type="text" class="form-control bg-light fw-bold" value="{{ $autoInvNo }}" readonly>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">No. Faktur Supplier</label>
                        <input type="text" name="supplier_invoice_no" class="form-control">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Referensi PO</label>
                        <select name="purchase_order_id" id="select-ref-po" class="form-select">
                            <option value="">Tidak ada PO</option>
                            @foreach($approvedPos as $po)
                                <option
                                    value="{{ $po->id }}"
                                    data-supplier="{{ $po->supplier_id }}"
                                    data-bu="{{ $po->business_unit_id }}"
                                    data-warehouse="{{ $po->warehouse_id }}"
                                    {{ (string) $fromPoId === (string) $po->id ? 'selected' : '' }}>
                                    {{ $po->po_no }} - {{ $po->supplier_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Tanggal Pembelian</label>
                        <input type="date" name="purchase_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Unit Bisnis</label>
                        <select name="business_unit_id" id="business-unit-id" class="form-select" required>
                            <option value="">-- Pilih --</option>
                            @foreach($businessUnits as $bu)
                                <option value="{{ $bu->id }}">{{ $bu->code }} - {{ $bu->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Supplier</label>
                        <select name="supplier_id" id="supplier-id" class="form-select" required>
                            <option value="">-- Pilih --</option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Gudang Tujuan</label>
                        <select name="warehouse_id" id="warehouse-id" class="form-select">
                            <option value="">-- Pilih Gudang --</option>
                            @foreach($warehouses as $warehouse)
                                <option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Dipakai hanya untuk penerimaan langsung non-PO.</div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold">Cara Pembayaran</label>
                        <select name="payment_method" id="payment-method" class="form-select" required>
                            <option value="cash">Tunai / Cash</option>
                            <option value="credit">Kredit / Tempo</option>
                        </select>
                    </div>

                    <div class="col-md-3 d-none" id="due-date-wrap">
                        <label class="form-label fw-bold">Jatuh Tempo</label>
                        <input type="date" name="due_date" class="form-control" value="{{ date('Y-m-d', strtotime('+30 days')) }}">
                    </div>

                    <div class="col-md-9">
                        <div class="form-check form-switch border rounded p-3">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="goods_received" id="goods-received" value="1">
                            <label class="form-check-label fw-bold" for="goods-received">
                                Barang langsung diterima di gudang
                                <span class="d-block small text-muted fw-normal">Non-PO + centang = proses ReceiptController (stok, moving average HPP, mutasi dan jurnal). Jika ada PO, opsi ini otomatis nonaktif.</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <span class="fw-bold">Detail Item</span>
                <button type="button" class="btn btn-sm btn-light" id="btn-add-row">+ Tambah Item</button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light text-center">
                            <tr>
                                <th>Produk</th>
                                <th style="width:120px">Qty</th>
                                <th style="width:150px">Harga</th>
                                <th style="width:120px">Diskon</th>
                                <th style="width:160px">Subtotal</th>
                                <th style="width:50px"></th>
                            </tr>
                        </thead>
                        <tbody id="tbody-items">
                            @foreach($poItems as $item)
                                <tr>
                                    <td>
                                        <input type="hidden" name="products[]" value="{{ $item->product_id }}">
                                        <input type="hidden" name="unit_id[]" value="{{ $item->unit_id }}">
                                        <input type="hidden" name="conversion_factor[]" value="{{ $item->conversion_factor ?: 1 }}">
                                        <div class="fw-semibold">{{ $item->product_name }}</div>
                                        <div class="small text-muted">{{ $item->product_code }}</div>
                                    </td>
                                    <td><input type="number" name="qty[]" class="form-control form-control-sm input-qty text-center" step="0.001" value="{{ $item->qty }}" required></td>
                                    <td><input type="number" name="unit_price[]" class="form-control form-control-sm input-price text-end" step="0.01" value="{{ $item->unit_price }}" required></td>
                                    <td><input type="number" name="discount[]" class="form-control form-control-sm input-discount text-end" step="0.01" value="{{ $item->discount }}" min="0"></td>
                                    <td class="text-end fw-bold cell-subtotal">Rp 0</td>
                                    <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">×</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="4" class="text-end">SUBTOTAL</td>
                                <td class="text-end" id="footer-subtotal">Rp 0</td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="4" class="text-end">PPN / PAJAK</td>
                                <td><input type="number" name="tax_amount" id="input-tax" class="form-control form-control-sm text-end" value="0" min="0"></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="4" class="text-end text-primary">TOTAL</td>
                                <td class="text-end text-primary fs-5" id="footer-total">Rp 0</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="card-footer text-end">
                <a href="{{ route('inventori.pembelian.index') }}" class="btn btn-secondary me-2">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const poSelect = document.getElementById('select-ref-po');
    const supplier = document.getElementById('supplier-id');
    const businessUnit = document.getElementById('business-unit-id');
    const warehouse = document.getElementById('warehouse-id');
    const goodsReceived = document.getElementById('goods-received');

    function syncPoMode() {
        const selected = poSelect.options[poSelect.selectedIndex];
        const isPo = !!poSelect.value;

        goodsReceived.checked = false;
        goodsReceived.disabled = isPo;
        warehouse.disabled = isPo;

        if (isPo && selected) {
            supplier.value = selected.dataset.supplier || '';
            businessUnit.value = selected.dataset.bu || '';
            warehouse.value = selected.dataset.warehouse || '';
            loadPoItems(poSelect.value);
        } else if (!isPo) {
            warehouse.value = '';
            clearItems();
        }
    }

    function loadPoItems(poId) {
        fetch('{{ url('/inventori/pembelian/po-items') }}/' + poId)
            .then(response => response.json())
            .then(res => {
                if (!res.success) return;
                document.getElementById('tbody-items').innerHTML = '';
                res.items.forEach(item => addRow(
                    item.product_id,
                    item.product_name,
                    item.product_code,
                    item.qty,
                    item.unit_price,
                    item.discount,
                    item.unit_id,
                    item.conversion_factor || 1
                ));
                calcTotals();
            });
    }

    function clearItems() {
        document.getElementById('tbody-items').innerHTML = '';
        addRow();
    }

    function addRow(productId = '', name = '', code = '', qty = 1, price = 0, discount = 0, unitId = '', factor = 1) {
        const id = Date.now() + Math.random();
        const products = @json($products->map(fn($p) => ['id' => $p->id, 'code' => $p->code, 'name' => $p->name]));
        const options = products.map(p => '<option value="' + p.id + '">' + p.code + ' - ' + p.name + '</option>').join('');
        const productCell = productId
            ? '<input type="hidden" name="products[]" value="' + productId + '">' +
              '<input type="hidden" name="unit_id[]" value="' + (unitId || '') + '">' +
              '<input type="hidden" name="conversion_factor[]" value="' + factor + '">' +
              '<div class="fw-semibold">' + name + '</div><div class="small text-muted">' + code + '</div>'
            : '<select name="products[]" class="form-select form-select-sm" required><option value="">-- Pilih Barang --</option>' + options + '</select>' +
              '<input type="hidden" name="unit_id[]" value="">' +
              '<input type="hidden" name="conversion_factor[]" value="1">';

        document.getElementById('tbody-items').insertAdjacentHTML('beforeend', '<tr id="row-' + id + '">' +
            '<td>' + productCell + '</td>' +
            '<td><input type="number" name="qty[]" class="form-control form-control-sm input-qty text-center" step="0.001" value="' + qty + '" required></td>' +
            '<td><input type="number" name="unit_price[]" class="form-control form-control-sm input-price text-end" step="0.01" value="' + price + '" required></td>' +
            '<td><input type="number" name="discount[]" class="form-control form-control-sm input-discount text-end" step="0.01" value="' + discount + '" min="0"></td>' +
            '<td class="text-end fw-bold cell-subtotal">Rp 0</td>' +
            '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger btn-remove-row">×</button></td>' +
            '</tr>');
        calcTotals();
    }

    poSelect.addEventListener('change', syncPoMode);
    document.getElementById('btn-add-row').addEventListener('click', () => addRow());
    document.addEventListener('click', function (e) {
        if (e.target.closest('.btn-remove-row')) {
            e.target.closest('tr').remove();
            calcTotals();
        }
    });
    document.addEventListener('input', function (e) {
        if (e.target.matches('.input-qty, .input-price, .input-discount, #input-tax')) calcTotals();
    });

    document.getElementById('payment-method').addEventListener('change', function () {
        document.getElementById('due-date-wrap').classList.toggle('d-none', this.value !== 'credit');
    });

    function calcTotals() {
        let subtotal = 0;
        document.querySelectorAll('#tbody-items tr').forEach(row => {
            const qty = parseFloat(row.querySelector('.input-qty')?.value || 0);
            const price = parseFloat(row.querySelector('.input-price')?.value || 0);
            const discount = parseFloat(row.querySelector('.input-discount')?.value || 0);
            const line = Math.max(0, qty * price - discount);
            row.querySelector('.cell-subtotal').textContent = 'Rp ' + line.toLocaleString('id-ID');
            subtotal += line;
        });
        const tax = parseFloat(document.getElementById('input-tax').value || 0);
        document.getElementById('footer-subtotal').textContent = 'Rp ' + subtotal.toLocaleString('id-ID');
        document.getElementById('footer-total').textContent = 'Rp ' + (subtotal + tax).toLocaleString('id-ID');
    }

    syncPoMode();
    if (!document.querySelector('#tbody-items tr')) addRow();
    calcTotals();
});
</script>
@endsection