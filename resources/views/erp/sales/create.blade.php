@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h4 class="mb-1">Penjualan Baru</h4><div class="text-secondary small">Qty transaksi dikonversi ke Base Unit untuk stok dan HPP.</div></div>
    <a href="{{ route('inventori.penjualan') }}" class="btn btn-outline-secondary">← Kembali</a>
</div>

<div class="card shadow-sm">
<div class="card-body">
<div class="row g-3">
    <div class="col-md-4"><label class="form-label">Tanggal</label><input type="date" class="form-control" value="{{ now()->toDateString() }}" readonly></div>
    <div class="col-md-4"><label class="form-label">Unit Bisnis</label><select id="unitSelect" class="form-select" required><option value="">Pilih Unit</option>@foreach($units as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Gudang</label><select id="warehouseSelect" class="form-select" required><option value="">Pilih Gudang</option>@foreach($warehouses as $w)<option value="{{ $w->id }}" data-unit="{{ $w->business_unit_id }}">{{ $w->code }} — {{ $w->name }}</option>@endforeach</select></div>
    <div class="col-md-6"><label class="form-label">Customer</label><select id="customerSelect" class="form-select"><option value="">Umum</option>@foreach($customers as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
</div>

<hr class="my-4">
<div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0">Detail Penjualan</h6><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#itemModal">+ Tambah Item</button></div>

<div class="table-responsive">
<table class="table align-middle">
<thead class="table-light"><tr><th>Item</th><th>Satuan Transaksi</th><th class="text-end">Qty</th><th class="text-end">Harga/Satuan</th><th class="text-end">Subtotal</th><th></th></tr></thead>
<tbody id="detailBody"><tr><td colspan="6" class="text-center text-secondary py-4">Belum ada item.</td></tr></tbody>
</table>
</div>

<hr class="my-4">
<div class="row g-4">
<div class="col-md-6"><label class="form-label">Memo</label><textarea id="memo" class="form-control" rows="3"></textarea></div>
<div class="col-md-6">
    <div class="d-flex justify-content-between py-1"><span>Subtotal</span><strong id="subtotalAmount">Rp 0</strong></div>
    <div class="d-flex justify-content-between align-items-center py-1"><span>Diskon</span><input id="discountInput" type="number" min="0" class="form-control text-end" style="max-width:160px" value="0"></div>
    <div class="d-flex justify-content-between border-top mt-2 pt-2 fs-5"><strong>TOTAL</strong><strong id="totalAmount">Rp 0</strong></div>
    <div class="mt-3"><label class="form-label">Cara Bayar</label><select id="paymentMethod" class="form-select"><option value="Tunai">Tunai</option><option value="Transfer">Transfer</option><option value="QRIS">QRIS</option><option value="Kredit / Bon">Kredit / Bon</option></select></div>
    <div id="dueDateWrap" class="mt-3 d-none"><label class="form-label">Jatuh Tempo</label><input id="dueDate" type="date" class="form-control"></div>
</div>
</div>
</div>
<div class="card-footer d-flex justify-content-end gap-2"><a href="{{ route('inventori.penjualan') }}" class="btn btn-outline-secondary">Batal</a><button type="button" id="saveSales" class="btn btn-primary">Simpan Penjualan</button></div>
</div>

<div class="modal fade" id="itemModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
<div class="modal-header"><h6 class="modal-title">Pilih Item</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><input id="itemFilter" class="form-control mb-3" placeholder="Cari nama / kode...">
<div class="list-group">
@foreach($products as $p)
<button type="button" class="list-group-item list-group-item-action item-choice" data-id="{{ $p->id }}" data-code="{{ $p->code }}" data-name="{{ $p->name }}" data-price="{{ $p->selling_price }}" data-units='@json($p->transaction_units)'>{{ $p->code }} — {{ $p->name }} <span class="small text-secondary">(Base: {{ $p->base_unit_code }})</span></button>
@endforeach
</div></div></div></div></div>
@endsection

@push('scripts')
<script>
(function () {
    let selectedItem = null;

    const rupiah = n => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
    const warehouses = Array.from(document.querySelectorAll('#warehouseSelect option[data-unit]'));

    function filterWarehouses() {
        const unit = document.getElementById('unitSelect').value;
        const select = document.getElementById('warehouseSelect');
        Array.from(select.options).forEach((option, index) => {
            if (!option.dataset.unit) { option.hidden = index !== 0; return; }
            option.hidden = !!unit && option.dataset.unit !== unit;
        });
        if (select.selectedOptions[0]?.hidden) select.value = '';
    }

    function totals() {
        const subtotal = selectedItem ? Math.max(0, Number(selectedItem.qty) * Number(selectedItem.unit_price) - Number(selectedItem.line_discount || 0)) : 0;
        const discount = Math.min(Math.max(Number(document.getElementById('discountInput').value || 0), 0), subtotal);
        document.getElementById('subtotalAmount').textContent = rupiah(subtotal);
        document.getElementById('totalAmount').textContent = rupiah(subtotal - discount);
        return {subtotal, discount, total: subtotal - discount};
    }

    function renderItem() {
        const body = document.getElementById('detailBody');
        if (!selectedItem) {
            body.innerHTML = '<tr><td colspan="6" class="text-center text-secondary py-4">Belum ada item.</td></tr>';
            totals();
            return;
        }
        const units = selectedItem.units;
        body.innerHTML = '<tr>' +
            '<td><b>' + selectedItem.code + '</b><div class="small text-secondary">' + selectedItem.name + '</div></td>' +
            '<td><select id="lineUnit" class="form-select form-select-sm">' + units.map(u => '<option value="' + u.id + '" data-factor="' + u.factor + '" data-price="' + u.price + '">' + u.code + ' — ' + u.name + '</option>').join('') + '</select></td>' +
            '<td class="text-end"><input id="lineQty" type="number" min="0.000001" step="0.000001" class="form-control form-control-sm text-end" value="' + selectedItem.qty + '"></td>' +
            '<td class="text-end"><input id="linePrice" type="number" min="0" step="0.01" class="form-control form-control-sm text-end" value="' + selectedItem.unit_price + '"></td>' +
            '<td class="text-end" id="lineTotal">' + rupiah(selectedItem.qty * selectedItem.unit_price) + '</td>' +
            '<td class="text-end"><button type="button" id="removeItem" class="btn btn-sm btn-outline-danger">×</button></td>' +
            '</tr>';

        document.getElementById('lineUnit').value = selectedItem.unit_id;
        document.getElementById('lineUnit').addEventListener('change', function () {
            const u = units.find(x => Number(x.id) === Number(this.value));
            selectedItem.unit_id = Number(this.value);
            selectedItem.factor = Number(u.factor);
            selectedItem.unit_price = Number(u.price);
            document.getElementById('linePrice').value = selectedItem.unit_price;
            renderLineOnly();
        });
        document.getElementById('lineQty').addEventListener('input', function () { selectedItem.qty = Number(this.value || 0); renderLineOnly(); });
        document.getElementById('linePrice').addEventListener('input', function () { selectedItem.unit_price = Number(this.value || 0); renderLineOnly(); });
        document.getElementById('removeItem').addEventListener('click', function () { selectedItem = null; renderItem(); });
        totals();
    }

    function renderLineOnly() {
        document.getElementById('lineTotal').textContent = rupiah(selectedItem.qty * selectedItem.unit_price);
        totals();
    }

    document.getElementById('unitSelect').addEventListener('change', filterWarehouses);
    document.getElementById('discountInput').addEventListener('input', totals);
    document.getElementById('paymentMethod').addEventListener('change', function () {
        document.getElementById('dueDateWrap').classList.toggle('d-none', this.value !== 'Kredit / Bon');
    });
    document.getElementById('itemFilter').addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('.item-choice').forEach(b => b.classList.toggle('d-none', !b.textContent.toLowerCase().includes(q)));
    });

    document.querySelectorAll('.item-choice').forEach(function (button) {
        button.addEventListener('click', function () {
            const units = JSON.parse(button.dataset.units || '[]');
            const defaultUnit = units.find(u => u.default_sale) || units[0];
            selectedItem = {
                product_id: Number(button.dataset.id),
                code: button.dataset.code,
                name: button.dataset.name,
                units: units,
                unit_id: Number(defaultUnit.id),
                factor: Number(defaultUnit.factor),
                qty: 1,
                unit_price: Number(defaultUnit.price || 0),
                line_discount: 0
            };
            renderItem();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('itemModal')).hide();
        });
    });

    document.getElementById('saveSales').addEventListener('click', async function () {
        try {
            const businessUnitId = Number(document.getElementById('unitSelect').value);
            const warehouseId = Number(document.getElementById('warehouseSelect').value);
            if (!businessUnitId) throw new Error('Unit Bisnis wajib dipilih.');
            if (!warehouseId) throw new Error('Gudang wajib dipilih.');
            if (!selectedItem) throw new Error('Tambahkan item terlebih dahulu.');
            if (selectedItem.qty <= 0) throw new Error('Qty harus lebih besar dari nol.');

            const t = totals();
            const payload = {
                customer_id: document.getElementById('customerSelect').value ? Number(document.getElementById('customerSelect').value) : null,
                business_unit_id: businessUnitId,
                warehouse_id: warehouseId,
                payment_method: document.getElementById('paymentMethod').value,
                due_date: document.getElementById('dueDate').value || null,
                memo: document.getElementById('memo').value || null,
                discount: t.discount,
                items: [{
                    product_id: selectedItem.product_id,
                    unit_id: selectedItem.unit_id,
                    qty: selectedItem.qty,
                    unit_price: selectedItem.unit_price,
                    discount: selectedItem.line_discount || 0
                }]
            };

            const response = await fetch('{{ route('inventori.penjualan.store') }}', {
                method: 'POST',
                headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content || ''},
                body: JSON.stringify(payload)
            });
            const json = await response.json();
            if (!response.ok) throw new Error(json.message || Object.values(json.errors || {}).flat().join(' ') || 'Gagal menyimpan penjualan.');
            window.location.href = json.redirect || '{{ route('inventori.penjualan') }}';
        } catch (e) {
            alert(e.message);
        }
    });
})();
</script>
@endpush
