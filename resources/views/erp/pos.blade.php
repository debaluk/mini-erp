<div class="pos-screen" id="posScreen">
<div class="pos-topbar">
    <div>
        <strong>MINI ERP POS</strong>
        <div class="small text-light mt-1">
            Tanggal: <span id="posDate"></span> &nbsp; | &nbsp; Jam: <span id="posClock"></span>
            &nbsp; | &nbsp; Kasir: <strong>{{ auth()->user()->name }}</strong>
        </div>
    </div>
    <div class="text-end">
        <div class="small text-light">Unit Bisnis</div>
        <strong>{{ $businessUnit?->code ?? '-' }} — {{ $businessUnit?->name ?? 'Belum ditentukan' }}</strong>
        <div class="small text-light mt-1">Gudang: <strong>{{ $posWarehouse?->code ?? '-' }} — {{ $posWarehouse?->name ?? 'Belum dipetakan' }}</strong></div>
    </div>
</div>

<div class="pos-main">
    <div class="pos-entry">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <div class="fw-semibold">Item Penjualan</div>
                <div class="small text-secondary">Cari item langsung pada baris, pilih item, isi qty, tambah atau hapus baris.</div>
            </div>
            <button type="button" id="posAddRow" class="btn btn-primary">+ Tambah Baris</button>
        </div>
    </div>

    <div class="table-responsive pos-table-wrap">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:38%">Item</th>
                    <th style="width:10%">Satuan</th>
                    <th class="text-end" style="width:15%">Harga Jual</th>
                    <th class="text-end" style="width:12%">Qty</th>
                    <th class="text-end" style="width:17%">Sub Total</th>
                    <th class="text-center" style="width:8%">Aksi</th>
                </tr>
            </thead>
            <tbody id="posCartBody"></tbody>
        </table>
    </div>

    <div class="pos-bottom">
        <div class="pos-summary">
            <div class="row g-2">
                <div class="col-sm-4">
                    <label class="form-label">Diskon</label>
                    <input id="posDiscount" type="number" min="0" step="0.01" class="form-control text-end" value="0">
                </div>
                <div class="col-sm-4">
                    <label class="form-label">Pembayaran</label>
                    <select id="posPaymentMethod" class="form-select">
                        <option>Tunai</option>
                        <option>Transfer</option>
                        <option>QRIS</option>
                    </select>
                </div>
                <div class="col-sm-4">
                    <label class="form-label">Nominal Bayar</label>
                    <input id="posPayment" type="number" min="0" step="0.01" class="form-control text-end" value="0">
                </div>
            </div>
            <div class="d-flex justify-content-between mt-3"><span>SUBTOTAL</span><strong id="posSubtotalText">Rp 0</strong></div>
            <div class="d-flex justify-content-between"><span>DISKON</span><strong id="posDiscountText">Rp 0</strong></div>
            <div class="d-flex justify-content-between fs-3"><span>TOTAL</span><strong id="posTotal">Rp 0</strong></div>
            <div class="d-flex justify-content-between mt-2"><span>KEMBALIAN</span><strong id="posChangeText">Rp 0</strong></div>
            <div class="d-flex gap-2 mt-3">
                <button id="posClear" class="btn btn-outline-secondary flex-fill">Kosongkan</button>
                <button id="posPay" class="btn btn-success btn-lg flex-fill">BAYAR & POSTING</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="posPrintModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Cetak Struk</h5></div>
            <div class="modal-body text-center">Transaksi berhasil diposting.<br><strong id="posPrintInvoice"></strong><div class="mt-3">Cetak struk sekarang?</div></div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tidak</button>
                <button type="button" class="btn btn-primary" id="posPrintYes">Ya, Cetak</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const body = document.getElementById('posCartBody');
    const discount = document.getElementById('posDiscount');
    const payment = document.getElementById('posPayment');
    const method = document.getElementById('posPaymentMethod');
    const subtotalText = document.getElementById('posSubtotalText');
    const discountText = document.getElementById('posDiscountText');
    const totalText = document.getElementById('posTotal');
    const changeText = document.getElementById('posChangeText');
    const clearBtn = document.getElementById('posClear');
    const payBtn = document.getElementById('posPay');
    const products = @json($products);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';
    let cart = Object.values(@json($posCart));
    let rows = [];
    let lastReceipt = null;

    const money = n => 'Rp ' + Math.round(n || 0).toLocaleString('id-ID');
    const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
    const productById = id => products.find(p => Number(p.id) === Number(id));

    function updateClock() {
        const d = new Date();
        document.getElementById('posDate').textContent = d.toLocaleDateString('id-ID',{day:'2-digit',month:'2-digit',year:'numeric'});
        document.getElementById('posClock').textContent = d.toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:false});
    }
    updateClock();
    setInterval(updateClock,1000);

    async function api(url, opts = {}) {
        opts.headers = {...(opts.headers || {}), 'X-CSRF-TOKEN': csrf, 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest'};
        const r = await fetch(url, opts);
        const j = await r.json();
        if (!r.ok) throw new Error(j.message || Object.values(j.errors || {}).flat()[0] || 'Terjadi kesalahan.');
        return j;
    }

    function syncRows() {
        rows = cart.map(x => ({type:'item', product_id:Number(x.product_id), qty:Number(x.qty), item:x}));
        if (!rows.length || rows[rows.length - 1].type !== 'empty') rows.push({type:'empty'});
    }

    function recalc() {
        const sub = cart.reduce((a,x) => a + (+x.price || 0) * (+x.qty || 0), 0);
        const dis = Math.min(Math.max(+discount.value || 0,0), sub);
        const tot = sub - dis;
        const paid = Math.max(+payment.value || 0,0);
        const change = Math.max(paid - tot,0);
        subtotalText.textContent = money(sub);
        discountText.textContent = money(dis);
        totalText.textContent = money(tot);
        changeText.textContent = money(method.value === 'Tunai' ? change : 0);
        if (!payment.dataset.userEdited) payment.value = Math.round(tot);
        clearBtn.disabled = !cart.length;
        payBtn.disabled = !cart.length;
        return {sub,dis,tot,paid,change};
    }

    function productRows(rowIndex, query) {
        const q = String(query || '').trim().toLowerCase();
        if (!q) return [];
        return products.filter(p => [p.name,p.sku,p.barcode,p.code].some(v => String(v || '').toLowerCase().includes(q))).slice(0,20);
    }

    function render() {
        syncRows();
        body.innerHTML = rows.map((row,i) => {
            if (row.type === 'empty') {
                return '<tr data-row="'+i+'"><td colspan="6"><div class="position-relative"><input class="form-control pos-row-search" data-row="'+i+'" placeholder="Cari nama, kode, SKU, barcode..." autocomplete="off"><div class="pos-row-popup list-group position-absolute w-100 shadow-sm" data-popup="'+i+'" style="z-index:1080;display:none;max-height:260px;overflow-y:auto"></div></div></td><td></td></tr>';
            }
            const x = row.item;
            return '<tr data-row="'+i+'">'+
                '<td><div class="fw-semibold">'+esc(x.name)+'</div><div class="small text-secondary">'+esc(x.code || x.sku || '-')+'</div></td>'+
                '<td>'+esc(x.selling_unit_code || '-')+'</td>'+
                '<td class="text-end">'+money(x.price)+'</td>'+
                '<td><input type="number" min="0.001" step="0.001" class="form-control form-control-sm text-end pos-row-qty" data-product="'+x.product_id+'" value="'+esc(x.qty)+'"></td>'+
                '<td class="text-end fw-semibold">'+money((+x.price||0)*(+x.qty||0))+'</td>'+
                '<td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger pos-row-remove" data-product="'+x.product_id+'">Hapus</button></td>'+
            '</tr>';
        }).join('');
        recalc();
    }

    body.addEventListener('input', event => {
        const input = event.target.closest('.pos-row-search');
        if (!input) return;
        const popup = body.querySelector('[data-popup="'+input.dataset.row+'"]');
        const found = productRows(input.dataset.row, input.value);
        if (!found.length) {
            popup.innerHTML = input.value.trim() ? '<div class="list-group-item text-secondary">Item tidak ditemukan.</div>' : '';
            popup.style.display = input.value.trim() ? 'block' : 'none';
            return;
        }
        popup.innerHTML = found.map(p =>
            '<button type="button" class="list-group-item list-group-item-action text-start" data-product="'+p.id+'">'+
            '<div class="fw-semibold">'+esc(p.name)+'</div>'+
            '<div class="small text-secondary">'+esc(p.code || p.sku || '-')+' · '+money(p.selling_price)+' · Stok '+Number(p.stock_qty||0).toLocaleString('id-ID')+' '+esc(p.selling_unit_code||'')+'</div>'+
            '</button>'
        ).join('');
        popup.style.display = 'block';
    });

    body.addEventListener('mousedown', async event => {
        const button = event.target.closest('[data-product]');
        if (!button) return;
        event.preventDefault();
        const p = productById(button.dataset.product);
        if (!p) return;
        try {
            const j = await api('{{ route('erp.pos.add') }}',{method:'POST',body:new URLSearchParams({product_id:p.id,qty:1})});
            cart = Object.values(j.cart || {});
            render();
        } catch(e) { alert(e.message); }
    });

    body.addEventListener('change', async event => {
        const input = event.target.closest('.pos-row-qty');
        if (!input) return;
        try {
            const j = await api('{{ url('/erp/pos/item') }}/'+input.dataset.product,{method:'PUT',body:new URLSearchParams({qty:input.value})});
            cart = Object.values(j.cart || {});
            render();
        } catch(e) { alert(e.message); render(); }
    });

    body.addEventListener('click', async event => {
        const btn = event.target.closest('.pos-row-remove');
        if (!btn) return;
        try {
            const j = await api('{{ url('/erp/pos/item') }}/'+btn.dataset.product+'/remove',{method:'POST'});
            cart = Object.values(j.cart || {});
            render();
        } catch(e) { alert(e.message); }
    });

    document.getElementById('posAddRow').addEventListener('click', () => {
        rows.push({type:'empty'});
        render();
        const inputs = body.querySelectorAll('.pos-row-search');
        inputs[inputs.length-1]?.focus();
    });

    discount.addEventListener('input', recalc);
    payment.addEventListener('input', () => { payment.dataset.userEdited='1'; recalc(); });
    method.addEventListener('change', () => { payment.dataset.userEdited=''; recalc(); });

    clearBtn.addEventListener('click', async () => {
        if (!confirm('Kosongkan transaksi?')) return;
        try {
            await api('{{ route('erp.pos.clear') }}',{method:'POST'});
            cart = [];
            discount.value = 0;
            payment.dataset.userEdited = '';
            render();
        } catch(e) { alert(e.message); }
    });

    payBtn.addEventListener('click', async () => {
        const z = recalc();
        if (!cart.length) return;
        if (z.paid < z.tot) { alert('Nominal pembayaran kurang.'); payment.focus(); return; }
        if (method.value !== 'Tunai' && Math.round(z.paid*100) !== Math.round(z.tot*100)) {
            alert('Transfer/QRIS harus dibayar tepat sesuai total.'); payment.focus(); return;
        }
        payBtn.disabled = true;
        try {
            const j = await api('{{ route('erp.pos.store') }}',{method:'POST',body:new URLSearchParams({
                payment_method:method.value, discount:z.dis, payment_amount:payment.value
            })});
            lastReceipt = j;
            document.getElementById('posPrintInvoice').textContent = j.invoice_no;
            cart = [];
            discount.value = 0;
            payment.dataset.userEdited = '';
            render();
            new bootstrap.Modal(document.getElementById('posPrintModal')).show();
        } catch(e) { alert(e.message); payBtn.disabled = false; }
    });

    document.getElementById('posPrintYes').addEventListener('click', () => {
        printReceipt(lastReceipt);
        bootstrap.Modal.getInstance(document.getElementById('posPrintModal'))?.hide();
    });

    function printReceipt(r) {
        if (!r) return;
        const w = window.open('','_blank','width=420,height=700');
        if (!w) { alert('Popup diblokir browser. Izinkan popup untuk mencetak.'); return; }
        const rowsHtml = r.items.map(x => {
            const qty = Number(x.qty).toLocaleString('id-ID');
            const price = Math.round(x.price).toLocaleString('id-ID');
            const line = Math.round((+x.price||0)*(+x.qty||0)).toLocaleString('id-ID');
            return '<tr><td colspan="2"><b>'+esc(x.name)+'</b></td></tr><tr><td>'+qty+' '+esc(x.selling_unit_code||'-')+' x '+price+'</td><td class="right">'+line+'</td></tr>';
        }).join('');
        const bu = r.business_unit || {};
        const wh = r.warehouse || {};
        const html = '<!doctype html><html><head><meta charset="utf-8"><title>'+esc(r.invoice_no)+'</title><style>@page{size:80mm auto;margin:0}*{box-sizing:border-box}html,body{margin:0;padding:0;width:80mm}body{font-family:Arial,sans-serif;font-size:11px;line-height:1.35;padding:4mm 4mm 6mm;color:#000}.center{text-align:center}.kop{font-weight:700;font-size:16px}.line{border-top:1px dashed #000;margin:6px 0}.row{display:flex;justify-content:space-between;gap:8px}.right{text-align:right}.items{width:100%;border-collapse:collapse}.items td{padding:1px 0;vertical-align:top}.grand{font-size:14px;margin-top:4px}.footer{margin-top:10px;text-align:center}.small{font-size:10px}</style></head><body>'+
            '<div class="center"><div class="kop">'+esc(r.entity?.name||'MINI ERP')+'</div><div>'+esc(bu.code||'')+' — '+esc(bu.name||'')+'</div><div>Gudang: '+esc(wh.code||'')+' — '+esc(wh.name||'')+'</div></div><div class="line"></div>'+
            '<div>No. Transaksi : '+esc(r.invoice_no)+'</div><div>Tanggal/Jam : '+esc(r.sale_date||'')+'</div><div>Kasir : '+esc(r.cashier||'')+'</div><div class="line"></div><table class="items">'+rowsHtml+'</table><div class="line"></div>'+
            '<div class="row"><span>Subtotal</span><b>'+money(r.subtotal)+'</b></div>'+(Number(r.discount||0)>0?'<div class="row"><span>Diskon</span><b>- '+money(r.discount)+'</b></div>':'')+
            '<div class="row grand"><span>TOTAL</span><b>'+money(r.total)+'</b></div><div class="line"></div><div class="row"><span>Pembayaran</span><b>'+esc(r.payment_method||'')+'</b></div><div class="row"><span>Dibayar</span><b>'+money(r.paid_amount)+'</b></div><div class="row"><span>Kembalian</span><b>'+money(r.change_amount)+'</b></div>'+
            '<div class="footer"><b>TERIMA KASIH</b><div>Selamat berbelanja</div><div class="line"></div><div class="small">Powered by MINI ERP</div></div><script>window.onload=function(){window.focus();window.print()}<\\/script></body></html>';
        w.document.open(); w.document.write(html); w.document.close();
    }

    document.addEventListener('click', event => {
        if (!event.target.closest('.pos-row-search') && !event.target.closest('.pos-row-popup')) {
            document.querySelectorAll('.pos-row-popup').forEach(x => x.style.display='none');
        }
    });

    syncRows();
    render();
    body.querySelector('.pos-row-search')?.focus();
});
</script>
</div>