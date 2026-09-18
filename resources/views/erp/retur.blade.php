@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1">Retur Penjualan</h3>
        <div class="text-secondary">Retur berdasarkan nomor struk penjualan</div>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#return-modal">+ Tambah Retur</button>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-semibold">Riwayat Retur</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>No. Retur</th><th>Tanggal</th><th>No. Struk</th><th>Customer</th><th>Gudang</th><th class="text-end">Total</th><th>User</th></tr></thead>
            <tbody>
            @forelse($rows as $r)
                <tr>
                    <td class="fw-semibold">{{ $r->return_no }}</td>
                    <td>{{ CarbonCarbon::parse($r->return_date)->format('d-m-Y H:i') }}</td>
                    <td>{{ $r->invoice_no }}</td>
                    <td>{{ $r->customer_name }}</td>
                    <td>{{ $r->warehouse_name }}</td>
                    <td class="text-end">Rp {{ number_format($r->total, 0, ',', '.') }}</td>
                    <td>{{ $r->user_name }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-secondary py-4">Belum ada retur.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $rows->links() }}</div>
</div>

<div class="modal fade" id="return-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="{{ route('pos.retur.store') }}" id="return-form">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Retur Penjualan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-8">
                            <label class="form-label">No. Struk</label>
                            <div class="input-group">
                                <input type="text" id="return-invoice" class="form-control" placeholder="Ketik nomor struk" required>
                                <button type="button" class="btn btn-outline-primary" id="return-search">Cari</button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Gudang Retur</label>
                            <select name="warehouse_id" id="return-warehouse" class="form-select" required>
                                <option value="">Pilih gudang</option>
                                @foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->code }} — {{ $w->name }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="sale_id" id="return-sale-id">
                    <div id="return-sale-info" class="alert alert-light border d-none"></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle">
                            <thead><tr><th>#</th><th>SKU</th><th>Produk</th><th class="text-end">Terjual</th><th class="text-end">Sudah Retur</th><th class="text-end">Bisa Retur</th><th style="width:130px">Qty Retur</th><th style="width:150px">Kondisi</th><th class="text-end">Nilai</th></tr></thead>
                            <tbody id="return-items"><tr><td colspan="9" class="text-center text-secondary">Cari nomor struk terlebih dahulu.</td></tr></tbody>
                            <tfoot><tr><th colspan="8" class="text-end">TOTAL RETUR</th><th class="text-end" id="return-total">Rp 0</th></tr></tfoot>
                        </table>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Alasan Retur</label>
                        <textarea name="reason" class="form-control" rows="2" placeholder="Contoh: barang rusak / salah barang / customer batal"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="return-submit" disabled>Proses Retur</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('return-form');
    const tbody = document.getElementById('return-items');
    const totalEl = document.getElementById('return-total');
    let sale = null;
    let items = [];

    const money = n => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));

    function renderItems() {
        tbody.innerHTML = '';
        items.forEach((item, index) => {
            const max = Number(item.available_qty || 0);
            tbody.insertAdjacentHTML('beforeend',
                '<tr>' +
                '<td>' + (index + 1) + '</td>' +
                '<td>' + esc(item.sku || '-') + '</td>' +
                '<td>' + esc(item.name || '-') + '</td>' +
                '<td class="text-end">' + Number(item.qty || 0).toLocaleString('id-ID') + '</td>' +
                '<td class="text-end">' + Number(item.returned_qty || 0).toLocaleString('id-ID') + '</td>' +
                '<td class="text-end fw-semibold">' + max.toLocaleString('id-ID') + '</td>' +
                '<td><input type="number" class="form-control form-control-sm return-qty" data-index="' + index + '" min="0" max="' + max + '" step="0.001" value="0"' + (max <= 0 ? ' disabled' : '') + '></td>' +
                '<td><select class="form-select form-select-sm return-condition" data-index="' + index + '"' + (max <= 0 ? ' disabled' : '') + '><option value="good">Layak Jual</option><option value="reject">Reject/Rusak</option></select></td>' +
                '<td class="text-end return-line-value" data-index="' + index + '">Rp 0</td>' +
                '</tr>'
            );
        });
        if (!items.length) tbody.innerHTML = '<tr><td colspan="9" class="text-center text-secondary">Tidak ada detail penjualan.</td></tr>';
        bindInputs();
        calculate();
    }

    function bindInputs() {
        document.querySelectorAll('.return-qty').forEach(el => el.addEventListener('input', calculate));
        document.querySelectorAll('.return-condition').forEach(el => el.addEventListener('change', calculate));
    }

    function calculate() {
        let total = 0;
        document.querySelectorAll('.return-qty').forEach(input => {
            const i = Number(input.dataset.index);
            let qty = Number(input.value || 0);
            const max = Number(items[i]?.available_qty || 0);
            if (qty < 0) qty = 0;
            if (qty > max) { qty = max; input.value = max; }
            const gross = Number(items[i]?.total || 0);
            const subtotal = Number(sale?.subtotal || 0);
            const allocatedDiscount = subtotal > 0 ? (gross / subtotal) * Number(sale?.discount || 0) : 0;
            const netUnit = Number(items[i]?.qty || 0) > 0 ? Math.max(0, gross - allocatedDiscount) / Number(items[i].qty) : 0;
            const value = Math.round(netUnit * qty * 100) / 100;
            const cell = document.querySelector('.return-line-value[data-index="' + i + '"]');
            if (cell) cell.textContent = money(value);
            total += value;
        });
        totalEl.textContent = money(total);
        document.getElementById('return-submit').disabled = !sale || total <= 0;
    }

    document.getElementById('return-search').addEventListener('click', function () {
        const invoice = document.getElementById('return-invoice').value.trim();
        if (!invoice) return alert('Nomor struk wajib diisi.');
        fetch(@json(route('pos.retur.lookup')) + '?invoice_no=' + encodeURIComponent(invoice), { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.ok ? r.json() : r.json().then(e => Promise.reject(new Error(e.message || 'Struk tidak ditemukan.'))))
            .then(payload => {
                sale = payload.sale;
                items = payload.items || [];
                document.getElementById('return-sale-id').value = sale.id;
                const info = document.getElementById('return-sale-info');
                info.classList.remove('d-none');
                info.innerHTML =
                    '<div class="row g-2 small">' +
                    '<div class="col-md-3"><strong>No. Struk:</strong> ' + esc(sale.invoice_no) + '</div>' +
                    '<div class="col-md-3"><strong>Tanggal:</strong> ' + esc(sale.sale_date) + '</div>' +
                    '<div class="col-md-3"><strong>Customer:</strong> ' + esc(sale.customer_name) + '</div>' +
                    '<div class="col-md-3"><strong>Total:</strong> ' + money(sale.total) + '</div>' +
                    '</div>';
                renderItems();
            })
            .catch(e => alert(e.message));
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        const fd = new FormData(form);
        fd.delete('items[]');
        const selected = [];
        document.querySelectorAll('.return-qty').forEach(input => {
            const i = Number(input.dataset.index);
            const qty = Number(input.value || 0);
            if (qty > 0) {
                selected.push({ sale_item_id: items[i].sale_item_id, qty, condition: document.querySelector('.return-condition[data-index="' + i + '"]').value });
            }
        });
        if (!selected.length) return alert('Isi minimal satu Qty Retur.');
        selected.forEach((item, i) => {
            fd.append('items[' + i + '][sale_item_id]', item.sale_item_id);
            fd.append('items[' + i + '][qty]', item.qty);
            fd.append('items[' + i + '][condition]', item.condition);
        });
        fetch(form.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(r => {
                if (r.ok) return window.location.href = @json(route('pos.retur'));
                return r.json().then(e => Promise.reject(new Error(e.message || 'Retur gagal diproses.')));
            })
            .catch(e => alert(e.message));
    });

    document.getElementById('return-modal').addEventListener('hidden.bs.modal', function () {
        form.reset();
        document.getElementById('return-sale-id').value = '';
        document.getElementById('return-sale-info').classList.add('d-none').innerHTML = '';
        tbody.innerHTML = '<tr><td colspan="9" class="text-center text-secondary">Cari nomor struk terlebih dahulu.</td></tr>';
        totalEl.textContent = 'Rp 0';
        document.getElementById('return-submit').disabled = true;
        sale = null; items = [];
    });
});
</script>
@endpush
