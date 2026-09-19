@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1">Harga Jual</h3>
        <div class="text-secondary">Harga jual item Retail</div>
    </div>
    <button type="button" class="btn btn-primary btn-sm" id="btn-setup-awal">+ Setup Awal</button>
</div>

<div id="price-alert"></div>

<div class="card shadow-sm">
    <div class="card-body border-bottom py-2">
        <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <div class="small text-secondary">Daftar harga jual item Retail</div>
            <button type="button" class="btn btn-outline-success btn-sm" id="btn-export-csv">Export CSV</button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" id="selling-price-table">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Item</th>
                    <th>Satuan</th>
                    <th class="text-end">Harga Jual</th>
                    <th class="text-end">HPP Awal</th>
                    <th class="text-end">UP %</th>
                    <th class="text-end">Stok Awal</th>
                    <th>Tgl Setup</th>
                </tr>
            </thead>
            <tbody>
            @foreach($rows as $row)
                <tr>
                    <td class="fw-semibold">{{ $row->code }}</td>
                    <td>{{ $row->name }}</td>
                    <td>{{ $row->unit_code ?: '-' }}</td>
                    <td class="text-end">Rp {{ number_format((float) $row->selling_price, 0, ',', '.') }}</td>
                    <td class="text-end">{{ $row->initial_purchase_price !== null ? 'Rp '.number_format((float) $row->initial_purchase_price, 0, ',', '.') : '-' }}</td>
                    <td class="text-end">{{ $row->markup_percent !== null ? number_format((float) $row->markup_percent, 2, ',', '.') : '-' }}</td>
                    <td class="text-end">{{ $row->initial_stock !== null ? number_format((float) $row->initial_stock, 3, ',', '.') : '-' }}</td>
                    <td>{{ $row->setup_date ? \Carbon\Carbon::parse($row->setup_date)->format('d/m/Y') : '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="setupAwalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="setup-awal-form">
                <div class="modal-header py-2">
                    <h5 class="modal-title">Setup Awal</h5>
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
                        <input type="number" name="purchase_price" id="setup-purchase-price" class="form-control" min="0.01" step="0.01" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">Stok Awal <span class="text-danger">*</span></label>
                        <input type="number" name="initial_stock" id="setup-initial-stock" class="form-control" min="0.001" step="0.001" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label">UP (%)</label>
                        <input type="number" name="markup_percent" id="setup-markup" class="form-control" min="0" step="0.01" value="0">
                    </div>

                    <div class="mb-1">
                        <label class="form-label">Harga Jual <span class="text-danger">*</span></label>
                        <input type="number" name="selling_price" id="setup-selling-price" class="form-control" min="0.01" step="0.01" required>
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
<script>
(function () {
    const products = @json($products);
    const modal = new bootstrap.Modal(document.getElementById('setupAwalModal'));
    const priceTable = new DataTable('#selling-price-table', {
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        order: [[1, 'asc']],
        columnDefs: [{ targets: [3,4,5,6], className: 'text-end' }]
    });
    const form = document.getElementById('setup-awal-form');
    const search = document.getElementById('setup-product-search');
    const productId = document.getElementById('setup-product-id');
    const popup = document.getElementById('setup-product-popup');
    const purchase = document.getElementById('setup-purchase-price');
    const markup = document.getElementById('setup-markup');
    const selling = document.getElementById('setup-selling-price');
    let syncing = false;

    document.getElementById('btn-export-csv').addEventListener('click', () => {
        const rows = priceTable.rows({ search: 'applied' }).data().toArray();
        const header = ['Kode','Item','Satuan','Harga Jual','HPP Awal','UP %','Stok Awal','Tgl Setup'];
        const clean = v => String(v ?? '').replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim();
        const csv = [header, ...rows.map(r => r.map(clean))]
            .map(row => row.map(v => '"' + String(v).replace(/"/g, '""') + '"').join(','))
            .join('\r\n');
        const blob = new Blob([\ufeff + csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'harga-jual-' + new Date().toISOString().slice(0,10) + '.csv';
        document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(url);
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
        if (!q) { popup.style.display = 'none'; return; }
        const rows = products.filter(p => [p.name, p.code, p.barcode].some(v => String(v || '').toLowerCase().includes(q))).slice(0, 30);
        popup.innerHTML = rows.length
            ? rows.map((p, i) => '<button type="button" class="list-group-item list-group-item-action text-start" data-i="'+i+'"><div class="fw-semibold">'+esc(p.name)+'</div><div class="small text-secondary">'+esc(p.code)+(p.barcode ? ' · '+esc(p.barcode) : '')+(p.unit_code ? ' · '+esc(p.unit_code) : '')+'</div></button>').join('')
            : '<div class="list-group-item text-secondary">Barang tidak ditemukan.</div>';
        popup.style.display = 'block';
        popup.querySelectorAll('[data-i]').forEach(btn => btn.addEventListener('mousedown', e => {
            e.preventDefault();
            chooseProduct(rows[Number(btn.dataset.i)]);
        }));
    }

    function calcFromMarkup() {
        if (syncing) return;
        syncing = true;
        const hpp = Number(purchase.value || 0);
        const up = Number(markup.value || 0);
        if (hpp > 0) selling.value = (hpp * (1 + up / 100)).toFixed(2);
        syncing = false;
    }

    function calcFromSelling() {
        if (syncing) return;
        syncing = true;
        const hpp = Number(purchase.value || 0);
        const jual = Number(selling.value || 0);
        markup.value = hpp > 0 ? (((jual - hpp) / hpp) * 100).toFixed(2) : '0';
        syncing = false;
    }

    search.addEventListener('input', () => { productId.value = ''; showPopup(); });
    search.addEventListener('focus', showPopup);
    search.addEventListener('blur', () => setTimeout(() => popup.style.display = 'none', 150));
    search.addEventListener('keydown', e => {
        if (e.key === 'Escape') popup.style.display = 'none';
    });

    purchase.addEventListener('input', calcFromMarkup);
    markup.addEventListener('input', calcFromMarkup);
    selling.addEventListener('input', calcFromSelling);

    document.getElementById('btn-setup-awal').addEventListener('click', () => {
        form.reset();
        form.querySelector('[name="setup_date"]').value = '{{ now()->toDateString() }}';
        productId.value = '';
        document.getElementById('setup-product-unit').textContent = '';
        document.getElementById('setup-form-alert').innerHTML = '';
        modal.show();
        setTimeout(() => search.focus(), 250);
    });

    form.addEventListener('submit', async e => {
        e.preventDefault();
        if (!productId.value) {
            document.getElementById('setup-form-alert').innerHTML = '<div class="alert alert-danger py-2 small">Pilih barang dari popup pencarian.</div>';
            return;
        }
        const save = document.getElementById('setup-save');
        save.disabled = true;
        const response = await fetch('{{ route('master.harga-jual.setup-awal') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            body: new FormData(form)
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
