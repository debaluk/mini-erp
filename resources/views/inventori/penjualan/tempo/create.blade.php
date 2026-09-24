@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h4 class="mb-1">Penjualan Baru</h4><div class="text-secondary small">Satuan transaksi dikonversi otomatis ke Base Unit untuk stok dan HPP.</div></div>
    <a href="{{ route('inventori.penjualan') }}" class="btn btn-outline-secondary">← Kembali</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Tanggal</label><input type="date" class="form-control" value="{{ now()->toDateString() }}" readonly></div>
            <div class="col-md-6"><label class="form-label">Nomor</label><input class="form-control" value="Otomatis" readonly></div>
            <div class="col-md-6"><label class="form-label">Customer</label><div class="input-group"><input id="customerSearch" class="form-control" placeholder="Pilih customer..." readonly><button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#customerModal">🔍</button></div></div>
            <div class="col-md-6"><label class="form-label">Business Unit</label><select id="unitSelect" class="form-select" required><option value="">Pilih Unit</option>@foreach($units as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
        </div>

        <hr class="my-4">
        <div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0">Detail Penjualan</h6><button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#itemModal">+ Tambah Item</button></div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead class="table-light"><tr><th>Item</th><th style="width:160px">Satuan</th><th style="width:120px" class="text-end">Qty</th><th style="width:160px" class="text-end">Harga/Satuan</th><th class="text-end">Subtotal</th><th></th></tr></thead>
                <tbody id="detailBody"><tr><td colspan="6" class="text-center text-secondary py-4">Belum ada item.</td></tr></tbody>
            </table>
        </div>

        <hr class="my-4">
        <div class="row g-4">
            <div class="col-md-6">
                <h6>Informasi Customer</h6>
                <div class="small text-secondary">Customer</div><div id="customerInfo" class="fw-semibold mb-3">-</div>
                <div class="small text-secondary">Piutang Sebelumnya</div><div id="customerBalance" class="fw-semibold mb-2">Rp 0</div>
                <div class="alert alert-warning py-2 d-none" id="arrears">⚠ Ada tunggakan sebelumnya</div>
                <label class="form-label">Memo</label><textarea id="memoInput" class="form-control" rows="3"></textarea>
            </div>
            <div class="col-md-6">
                <h6>Informasi Transaksi</h6>
                <div class="d-flex justify-content-between py-1"><span>Subtotal</span><strong id="subtotalAmount">Rp 0</strong></div>
                <div class="d-flex justify-content-between align-items-center py-1"><span>Diskon (Rp)</span><input id="discountInput" type="number" min="0" class="form-control text-end" style="max-width:160px" value="0"></div>
                <div class="d-flex justify-content-between border-top mt-2 pt-2 fs-5"><strong>TOTAL</strong><strong id="totalAmount">Rp 0</strong></div>
                <div class="mt-3"><label class="form-label">Cara Bayar</label><select id="paymentMethod" class="form-select"><option value="Tunai">Tunai</option><option value="Transfer">Transfer</option><option value="QRIS">QRIS</option><option value="Kredit / Bon">Kredit / Bon</option></select></div>
                <div id="dueDate" class="mt-3 d-none"><label class="form-label">Jatuh Tempo</label><input type="date" class="form-control"></div>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-end gap-2"><a href="{{ route('inventori.penjualan') }}" class="btn btn-outline-secondary">Batal</a><button type="button" id="saveSales" class="btn btn-primary">Simpan Penjualan</button></div>
</div>

<div class="modal fade" id="customerModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h6 class="modal-title">Cari Customer</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input id="customerFilter" class="form-control mb-3" placeholder="Ketik nama customer..."><div class="list-group" id="customerList">@foreach($customers as $c)<button type="button" class="list-group-item list-group-item-action customer-choice d-flex justify-content-between align-items-center" data-id="{{ $c->id }}" data-name="{{ $c->name }}" data-balance="{{ $c->outstanding }}"><span>{{ $c->name }}</span><span class="small text-secondary">Piutang: Rp {{ number_format($c->outstanding, 0, ',', '.') }}</span></button>@endforeach</div></div></div></div></div>

<div class="modal fade" id="itemModal" tabindex="-1"><div class="modal-dialog modal-md modal-dialog-centered"><div class="modal-content"><div class="modal-header py-2"><h6 class="modal-title">Cari Item</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-2"><input id="itemFilter" class="form-control form-control-sm mb-2" placeholder="Ketik nama / kode..."><div class="list-group list-group-flush">@foreach($products as $p)<button type="button" class="list-group-item list-group-item-action item-choice" data-id="{{ $p->id }}" data-name="{{ $p->name }}" data-code="{{ $p->code ?? $p->sku }}" data-base-unit-id="{{ $p->base_unit_id }}" data-base-unit="{{ $p->base_unit_code ?? '-' }}" data-price="{{ $p->selling_price ?? 0 }}" data-conversions='@json(($productConversions[$p->id] ?? collect())->values())'>{{ $p->code ?? $p->sku }} — {{ $p->name }}</button>@endforeach</div></div></div></div></div>
@endsection

@push('scripts')
<script>
(function () {
    let selectedCustomerId = null;
    let selectedItem = null;

    function rupiah(n) { return 'Rp ' + Number(n || 0).toLocaleString('id-ID'); }
    function conversionsFor(button) {
        let rows = [];
        try { rows = JSON.parse(button.dataset.conversions || '[]'); } catch (e) {}
        return rows.map(x => ({id:Number(x.unit_id), name:x.name, code:x.code, factor:Number(x.conversion_factor), defaultSale:Boolean(Number(x.is_default_sale))}));
    }

    function calculateTotals() {
        const subtotal = selectedItem ? Math.max(0, (Number(selectedItem.qty) * Number(selectedItem.unit_price)) - Number(selectedItem.discount || 0)) : 0;
        const discount = Math.min(Math.max(Number(document.getElementById('discountInput').value || 0), 0), subtotal);
        document.getElementById('subtotalAmount').textContent = rupiah(subtotal);
        document.getElementById('totalAmount').textContent = rupiah(subtotal - discount);
        return {subtotal, discount, total:subtotal-discount};
    }

    function renderItem() {
        const body = document.getElementById('detailBody');
        if (!selectedItem) { body.innerHTML = '<tr><td colspan="6" class="text-center text-secondary py-4">Belum ada item.</td></tr>'; calculateTotals(); return; }

        body.innerHTML = '';
        const row = document.createElement('tr');
        row.innerHTML =
            '<td><b>'+selectedItem.code+'</b><div class="small text-secondary">'+selectedItem.name+'</div><div class="small text-secondary">Base: '+selectedItem.baseUnit+'</div></td>' +
            '<td><select class="form-select form-select-sm" id="itemUnitSelect"></select></td>' +
            '<td><input id="itemQty" type="number" min="0.001" step="0.001" class="form-control form-control-sm text-end" value="'+selectedItem.qty+'"></td>' +
            '<td><input id="itemPrice" type="number" min="0" step="0.0001" class="form-control form-control-sm text-end" value="'+selectedItem.unit_price+'"></td>' +
            '<td class="text-end" id="lineTotal">'+rupiah(selectedItem.qty*selectedItem.unit_price-selectedItem.discount)+'</td>' +
            '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" id="removeItem">×</button></td>';
        body.appendChild(row);

        const select = row.querySelector('#itemUnitSelect');
        selectedItem.units.forEach(u => { const opt=document.createElement('option'); opt.value=u.id; opt.textContent=u.code+' — 1 = '+u.factor+' '+selectedItem.baseUnit; opt.dataset.factor=u.factor; select.appendChild(opt); });
        select.value = String(selectedItem.unit_id);

        function refresh() {
            selectedItem.qty=Number(row.querySelector('#itemQty').value||0);
            selectedItem.unit_price=Number(row.querySelector('#itemPrice').value||0);
            selectedItem.unit_id=Number(select.value);
            selectedItem.factor=Number(select.selectedOptions[0]?.dataset.factor||1);
            selectedItem.discount=0;
            row.querySelector('#lineTotal').textContent=rupiah(selectedItem.qty*selectedItem.unit_price);
            calculateTotals();
        }
        select.addEventListener('change', function(){
            const oldFactor=selectedItem.factor||1;
            const newFactor=Number(select.selectedOptions[0]?.dataset.factor||1);
            selectedItem.unit_price = oldFactor ? (selectedItem.unit_price / oldFactor) * newFactor : selectedItem.unit_price;
            row.querySelector('#itemPrice').value=selectedItem.unit_price;
            refresh();
        });
        row.querySelector('#itemQty').addEventListener('input', refresh);
        row.querySelector('#itemPrice').addEventListener('input', refresh);
        row.querySelector('#removeItem').addEventListener('click', function(){selectedItem=null;renderItem();});
        calculateTotals();
    }

    document.querySelectorAll('.customer-choice').forEach(button => button.addEventListener('click', function () {
        selectedCustomerId=button.dataset.id;
        document.getElementById('customerSearch').value=button.dataset.name;
        document.getElementById('customerInfo').textContent=button.dataset.name;
        document.getElementById('customerBalance').textContent=rupiah(button.dataset.balance);
        document.getElementById('arrears').classList.toggle('d-none', Number(button.dataset.balance||0)<=0);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('customerModal')).hide();
    }));

    document.getElementById('customerFilter').addEventListener('input', function(e){
        const q=e.target.value.toLowerCase();
        document.querySelectorAll('.customer-choice').forEach(b=>b.classList.toggle('d-none',!b.textContent.toLowerCase().includes(q)));
    });
    document.getElementById('itemFilter').addEventListener('input', function(e){
        const q=e.target.value.toLowerCase();
        document.querySelectorAll('.item-choice').forEach(b=>b.classList.toggle('d-none',!b.textContent.toLowerCase().includes(q)));
    });

    document.querySelectorAll('.item-choice').forEach(button => button.addEventListener('click', function () {
        const conversions=conversionsFor(button);
        const base={id:Number(button.dataset.baseUnitId),code:button.dataset.baseUnit,name:button.dataset.baseUnit,factor:1,defaultSale:false};
        const units=[base,...conversions];
        const defaultUnit=conversions.find(x=>x.defaultSale) || base;
        const basePrice=Number(button.dataset.price||0);
        selectedItem={
            product_id:Number(button.dataset.id), code:button.dataset.code, name:button.dataset.name,
            baseUnit:button.dataset.baseUnit, units, unit_id:defaultUnit.id, factor:defaultUnit.factor,
            qty:1, unit_price:basePrice*defaultUnit.factor, discount:0
        };
        renderItem();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('itemModal')).hide();
    }));

    document.getElementById('discountInput').addEventListener('input', calculateTotals);
    document.getElementById('paymentMethod').addEventListener('change', e => document.getElementById('dueDate').classList.toggle('d-none',e.target.value!=='Kredit / Bon'));

    document.getElementById('saveSales').addEventListener('click', async function(){
        try {
            if(!document.getElementById('unitSelect').value) throw new Error('Business Unit wajib dipilih.');
            if(!selectedItem) throw new Error('Tambahkan item terlebih dahulu.');
            const totals=calculateTotals();
            const payload={
                customer_id:selectedCustomerId?Number(selectedCustomerId):null,
                unit_id:Number(document.getElementById('unitSelect').value),
                payment_method:document.getElementById('paymentMethod').value,
                due_date:document.querySelector('#dueDate input').value||null,
                memo:document.getElementById('memoInput').value||null,
                discount:totals.discount,
                items:[{product_id:selectedItem.product_id,unit_id:selectedItem.unit_id,qty:selectedItem.qty,unit_price:selectedItem.unit_price,discount:0}]
            };
            const response=await fetch('{{ route('inventori.penjualan.store') }}',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content||''},body:JSON.stringify(payload)});
            const json=await response.json();
            if(!response.ok) throw new Error(json.message||Object.values(json.errors||{}).flat().join(' ')||'Gagal menyimpan penjualan.');
            const doPrint=window.confirm('Penjualan berhasil disimpan. Cetak penjualan sekarang?');
            window.location.href=doPrint?(json.redirect||'{{ route('inventori.penjualan') }}')+'?print=1':'{{ route('inventori.penjualan.create') }}';
        } catch(error){ alert(error.message); }
    });
})();
</script>
@endpush
