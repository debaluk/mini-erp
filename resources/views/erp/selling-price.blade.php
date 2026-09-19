@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1">Harga Jual</h3>
        <div class="text-secondary">Harga jual item Retail</div>
    </div>
    <button type="button" class="btn btn-primary" id="btn-setup-awal" data-bs-toggle="modal" data-bs-target="#setupAwalModal">+ Setup Awal</button>
</div>

<div id="price-alert"></div>

<div class="card shadow-sm">
    <div class="card-body border-bottom py-2">
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <div>
                <div class="fw-semibold">Daftar Harga Jual</div>
                <div class="small text-secondary">Item yang sudah Setup Awal dapat diedit selama belum ada transaksi.</div>
            </div>
            <button type="button" class="btn btn-outline-success btn-sm" id="btn-export-excel">Export Excel</button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" id="selling-price-table">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Item</th>
                    <th>Satuan</th>
                    <th class="text-end">HPP Awal</th>
                    <th class="text-end">UP %</th>
                    <th class="text-end">Harga Jual</th>
                    <th class="text-end">Stok Awal</th>
                    <th>Tgl Setup</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @foreach($rows as $row)
                <tr>
                    <td class="fw-semibold">{{ $row->code }}</td>
                    <td>{{ $row->name }}</td>
                    <td>{{ $row->unit_code ?: '-' }}</td>
                    <td class="text-end fw-semibold">{{ $row->initial_purchase_price !== null ? 'Rp '.number_format((float) $row->initial_purchase_price, 0, ',', '.') : '-' }}</td>
                    <td class="text-end">{{ $row->markup_percent !== null ? number_format((float) $row->markup_percent, 2, ',', '.') : '-' }}%</td>
                    <td class="text-end fw-semibold">{{ (float) $row->selling_price > 0 ? 'Rp '.number_format((float) $row->selling_price, 0, ',', '.') : '-' }}</td>
                    <td class="text-end">{{ $row->initial_stock !== null ? rtrim(rtrim(number_format((float) $row->initial_stock, 3, ',', '.'), '0'), ',') : '-' }}</td>
                    <td>{{ $row->setup_date ? date('d/m/Y', strtotime($row->setup_date)) : '-' }}</td>
                    <td class="text-center">
@if($row->setup_date)
<span class="badge text-bg-success">Sudah Setup</span>
@else
<span class="badge text-bg-secondary">Belum Setup</span>
@endif
</td>
<td class="text-center">
@if($row->setup_date)
<button type="button" class="btn btn-outline-primary btn-sm btn-edit" data-id="{{ $row->id }}" data-name="{{ $row->name }}" data-price="{{ (float) $row->selling_price }}" data-hpp="{{ (float) $row->initial_purchase_price }}" data-stock="{{ (float) $row->initial_stock }}" data-up="{{ (float) $row->markup_percent }}" data-date="{{ $row->setup_date }}">Edit</button>
@else
<span class="text-secondary">-</span>
@endif
</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="detailHargaJualModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Detail Harga Jual</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="row g-3">
            <div class="col-md-6"><label class="form-label text-secondary">Kode</label><div id="d-code" class="fw-semibold"></div></div>
            <div class="col-md-6"><label class="form-label text-secondary">Item</label><div id="d-name" class="fw-semibold"></div></div>
            <div class="col-md-4"><label class="form-label text-secondary">Satuan</label><div id="d-unit"></div></div>
            <div class="col-md-4"><label class="form-label text-secondary">HPP Awal</label><div id="d-hpp"></div></div>
            <div class="col-md-4"><label class="form-label text-secondary">UP</label><div id="d-up"></div></div>
            <div class="col-md-4"><label class="form-label text-secondary">Harga Jual</label><div id="d-price" class="fw-semibold"></div></div>
            <div class="col-md-4"><label class="form-label text-secondary">Stok Awal</label><div id="d-stock"></div></div>
            <div class="col-md-4"><label class="form-label text-secondary">Tanggal Setup</label><div id="d-date"></div></div>
        </div></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button></div>
    </div></div>
</div>

<div class="modal fade" id="setupAwalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="setup-awal-form">
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="setup-modal-title">Setup Awal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="setup-form-alert"></div>

                    <div class="mb-2">
                        <label class="form-label">Tanggal <span class="text-danger">*</span></label>
                        <input type="date" name="setup_date" class="form-control" value="{{ now()->toDateString() }}" required>
                    </div>

                    <div class="mb-2 position-relative">
                        <label class="form-label">Cari Barang <span class="text-danger">*</span></label>
                        <input type="text" id="setup-product-search" class="form-control" autocomplete="off" placeholder="Ketik nama / kode / barcode..." required>
                        <input type="hidden" name="product_id" id="setup-product-id">
                        <div id="setup-product-popup" class="list-group position-absolute w-100 shadow-sm" style="z-index:1080;display:none;max-height:260px;overflow-y:auto;"></div>
                        <div id="setup-product-unit" class="small text-secondary mt-1"></div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Harga Beli / HPP Awal <span class="text-danger">*</span></label>
                        <input type="text" name="purchase_price" id="setup-purchase-price" class="form-control" inputmode="numeric" autocomplete="off" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Stok Awal <span class="text-danger">*</span></label>
                        <input type="text" name="initial_stock" id="setup-initial-stock" class="form-control" inputmode="decimal" autocomplete="off" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">UP (%)</label>
                        <input type="text" name="markup_percent" id="setup-markup" class="form-control" inputmode="decimal" autocomplete="off" value="0">
                    </div>

                    <div class="mb-1">
                        <label class="form-label">Harga Jual <span class="text-danger">*</span></label>
                        <input type="text" name="selling_price" id="setup-selling-price" class="form-control" inputmode="numeric" autocomplete="off" required>
                    </div>
                    <div class="small text-secondary">Harga Jual = Harga Beli + (Harga Beli × UP%). UP dan Harga Jual bisa diubah dua arah.</div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="setup-save">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>
<script>
(function () {
    const products = @json($products);
    const modalEl = document.getElementById('setupAwalModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    const detailModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('detailHargaJualModal'));
    const priceTable = new DataTable('#selling-price-table', {
        pageLength: 10,
        autoWidth: false,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, 'asc']],
        columnDefs: [{ targets: [3,4,5,6], className: 'text-end' }, { targets: [9], orderable: false, searchable: false }]
    });
    const form = document.getElementById('setup-awal-form');
    const search = document.getElementById('setup-product-search');
    const productId = document.getElementById('setup-product-id');
    const popup = document.getElementById('setup-product-popup');
    const purchase = document.getElementById('setup-purchase-price');
    const markup = document.getElementById('setup-markup');
    const selling = document.getElementById('setup-selling-price');
    const modalTitle = document.getElementById('setup-modal-title');
    const initialStock = document.getElementById('setup-initial-stock');
    const setupPurchase = document.getElementById('setup-purchase-price');
    let editMode = false;
    let syncing = false;
    const parseDecimal = value => {
        let s = String(value ?? '').trim().replace(/\s/g, '');
        if (!s) return 0;
        if (s.includes(',')) s = s.replace(/\./g, '').replace(',', '.');
        return Number(s.replace(/[^0-9.-]/g, '')) || 0;
    };
    const parseMoney = value => {
        if (typeof value === 'number') return Number.isFinite(value) ? value : 0;
        let s = String(value ?? '').trim().replace(/\s/g, '');
        if (!s) return 0;
        // Input tampilan Rupiah memakai titik sebagai pemisah ribuan.
        s = s.replace(/[^0-9-]/g, '');
        return Number(s) || 0;
    };
    const fmtMoney = value => new Intl.NumberFormat('id-ID',{maximumFractionDigits:0}).format(parseMoney(value));
    const fmtNum = value => new Intl.NumberFormat('id-ID',{minimumFractionDigits:0, maximumFractionDigits:3}).format(parseDecimal(value));

    document.getElementById('btn-export-excel').addEventListener('click', () => {
        if (typeof XLSX === 'undefined') {
            alert('Library Excel belum termuat. Silakan refresh halaman lalu coba lagi.');
            return;
        }

        const rows = priceTable.rows({ search: 'applied' }).data().toArray();
        const data = rows.map(r => {
            const raw = value => String(value ?? '').replace(/<[^>]*>/g, '').trim();
            const money = value => {
                const s = raw(value).replace(/[^0-9-]/g, '');
                return s ? Number(s) : null;
            };
            const percent = value => {
                const s = raw(value).replace(/%/g, '').replace(',', '.').replace(/[^0-9.-]/g, '');
                return s ? Number(s) / 100 : null;
            };
            const stock = value => {
                const s = raw(value).replace(/\./g, '').replace(',', '.').replace(/[^0-9.-]/g, '');
                return s ? Number(s) : null;
            };
            return [raw(r[0]), raw(r[1]), raw(r[2]), money(r[3]), percent(r[4]), money(r[5]), stock(r[6]), raw(r[7])];
        });

        const printDate = new Intl.DateTimeFormat('id-ID', {
            day: '2-digit', month: '2-digit', year: 'numeric'
        }).format(new Date());

        const ws = XLSX.utils.aoa_to_sheet([
            ['NAMA ENTITAS'],
            ['Data Setup Harga Jual'],
            ['Tgl Cetak : ' + printDate],
            [],
            ['Kode','Item','Satuan','HPP Awal','UP (%)','Harga Jual','Stok Awal','Tgl Setup'],
            ...data
        ]);

        ws['!merges'] = [
            { s: { r: 0, c: 0 }, e: { r: 0, c: 7 } },
            { s: { r: 1, c: 0 }, e: { r: 1, c: 7 } },
            { s: { r: 2, c: 0 }, e: { r: 2, c: 7 } }
        ];

        ws['!cols'] = [
            {wch:15},{wch:32},{wch:12},{wch:18},
            {wch:12},{wch:18},{wch:14},{wch:14}
        ];

        data.forEach((row, i) => {
            const excelRow = i + 6;
            if (row[3] !== null) ws['D'+excelRow].z = 'Rp #,##0';
            if (row[4] !== null) ws['E'+excelRow].z = '0.00%';
            if (row[5] !== null) ws['F'+excelRow].z = 'Rp #,##0';
            if (row[6] !== null) ws['G'+excelRow].z = '#,##0.###';
            if (row[7]) {
                const parts = row[7].split('/');
                if (parts.length === 3) {
                    ws['H'+excelRow].v = new Date(Number(parts[2]), Number(parts[1])-1, Number(parts[0]));
                    ws['H'+excelRow].t = 'd';
                    ws['H'+excelRow].z = 'dd/mm/yyyy';
                }
            }
        });

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Harga Jual');
        XLSX.writeFile(wb, 'harga-jual-' + new Date().toISOString().slice(0,10) + '.xlsx');
    });

    function esc(v) {
        return String(v ?? '').replace(/[&<>"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[s]));
    }

    function chooseProduct(p) {
        productId.value = p.id;
        search.value = p.name;
        document.getElementById('setup-product-unit').textContent = p.unit_code ? 'Satuan: ' + p.unit_code : '';
        if (Number(p.selling_price || 0) > 0) selling.value = Number(p.selling_price);
        popup.style.display = 'none';
    }

    function showPopup() {
        const q = search.value.trim().toLowerCase();
        const rows = products
            .filter(p => !q || [p.name, p.code, p.barcode].some(v => String(v || '').toLowerCase().includes(q)))
            .slice(0, 30);

        popup.innerHTML = rows.length
            ? rows.map((p, i) =>
                '<button type="button" class="list-group-item list-group-item-action text-start px-3 py-2" data-i="' + i + '">' +
                    '<div class="fw-semibold">' + esc(p.name) + '</div>' +
                    '<div class="small text-secondary">' + esc(p.code) +
                    (p.barcode ? ' · ' + esc(p.barcode) : '') +
                    (p.unit_code ? ' · ' + esc(p.unit_code) : '') +
                    '</div></button>'
              ).join('')
            : '<div class="list-group-item text-secondary">Barang tidak ditemukan.</div>';

        popup.style.display = 'block';
        popup.querySelectorAll('[data-i]').forEach(btn => {
            btn.addEventListener('mousedown', e => {
                e.preventDefault();
                chooseProduct(rows[Number(btn.dataset.i)]);
            });
        });
    }

    function calcFromMarkup() {
        if (syncing) return;
        syncing = true;
        const hpp = parseMoney(purchase.value);
        const up = parseDecimal(markup.value);
        if (hpp > 0) selling.value = fmtMoney(hpp * (1 + up / 100));
        syncing = false;
    }

    function calcFromSelling() {
        if (syncing) return;
        syncing = true;
        const hpp = parseMoney(purchase.value);
        const jual = parseMoney(selling.value);
        markup.value = hpp > 0 ? fmtNum(((jual - hpp) / hpp) * 100) : '0';
        syncing = false;
    }

    search.addEventListener('input', () => { productId.value = ''; showPopup(); });
    search.addEventListener('focus', showPopup);
    search.addEventListener('click', showPopup);
    search.addEventListener('blur', () => setTimeout(() => popup.style.display = 'none', 150));
    search.addEventListener('keydown', e => {
        if (e.key === 'Escape') popup.style.display = 'none';
    });

    purchase.addEventListener('input', calcFromMarkup);
    markup.addEventListener('input', calcFromMarkup);
    selling.addEventListener('input', calcFromSelling);
    purchase.addEventListener('blur',()=>purchase.value=fmtMoney(purchase.value));
    initialStock.addEventListener('blur',()=>initialStock.value=fmtNum(initialStock.value));
    markup.addEventListener('blur',()=>markup.value=fmtNum(markup.value));
    selling.addEventListener('blur',()=>selling.value=fmtMoney(selling.value));

    document.getElementById('btn-setup-awal').addEventListener('click', () => {
        editMode=false; modalTitle.textContent='Setup Awal'; setupPurchase.readOnly=false; initialStock.readOnly=false;
        form.reset();
        form.querySelector('[name="setup_date"]').value = '{{ now()->toDateString() }}';
        productId.value = '';
        document.getElementById('setup-product-unit').textContent = '';
        document.getElementById('setup-form-alert').innerHTML = '';
        modal.show();
        setTimeout(() => search.focus(), 250);
    });

    document.querySelector('#selling-price-table tbody').addEventListener('click', e => {
        const edit=e.target.closest('.btn-edit');
        if(edit){ editMode=true; modalTitle.textContent='Edit Setup Awal'; form.reset(); search.value=edit.dataset.name; productId.value=edit.dataset.id; setupPurchase.value=fmtMoney(edit.dataset.hpp); setupPurchase.readOnly=false; initialStock.value=fmtNum(edit.dataset.stock); initialStock.readOnly=false; markup.value=fmtNum(edit.dataset.up||'0'); selling.value=fmtMoney(edit.dataset.price); form.querySelector('[name="setup_date"]').value=edit.dataset.date||'{{ now()->toDateString() }}'; document.getElementById('setup-form-alert').innerHTML='<div class="alert alert-info py-2 small">Bisa diedit selama item belum memiliki transaksi Pembelian atau Penjualan.</div>'; popup.style.display='none'; modal.show(); return; }

        const btn = e.target.closest('.btn-detail');
        if (!btn) return;
        ['code','name','unit','hpp','up','price','stock','date'].forEach(k => document.getElementById('d-'+k).textContent = btn.dataset[k] || '-');
        detailModal.show();
    });

    form.addEventListener('submit', async e => {
        e.preventDefault();
        if (!productId.value) {
            document.getElementById('setup-form-alert').innerHTML = '<div class="alert alert-danger py-2 small">Pilih barang dari popup pencarian.</div>';
            return;
        }
        const save = document.getElementById('setup-save');
        save.disabled = true;
        const url=editMode ? '{{ url('/master/harga-jual') }}/'+productId.value+'/edit' : '{{ route('master.harga-jual.setup-awal') }}';
        const response = await fetch(url, {
            method: editMode ? 'POST' : 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: (() => {
                const fd = new FormData(form);
                if (editMode) fd.append('_method', 'PUT');
                fd.set('product_id', productId.value);
                fd.set('purchase_price', parseMoney(purchase.value));
                fd.set('initial_stock', parseDecimal(initialStock.value));
                fd.set('markup_percent', parseDecimal(markup.value));
                fd.set('selling_price', parseMoney(selling.value));
                return fd;
            })()
        });
        const payload = await response.json();
        if (!response.ok) {
            const errors = payload.errors ? Object.values(payload.errors).flat().join('<br>') : (payload.message || 'Setup awal gagal disimpan.');
            document.getElementById('setup-form-alert').innerHTML = '<div class="alert alert-danger py-2 small">'+errors+'</div>';
            save.disabled = false;
            return;
        }
        modal.hide();
        document.getElementById('price-alert').innerHTML = '<div class="alert alert-success py-2">'+payload.message+'</div>';
        setTimeout(() => window.location.reload(), 500);
    });
})();
</script>
@endpush
