@extends('layouts.app')
<style>
    .pos-screen{height:100vh;background:#f8f9fa;display:flex;flex-direction:column;overflow:hidden}.pos-topbar{height:48px;flex:0 0 48px;background:#212529;color:#fff;display:flex;align-items:center;justify-content:space-between;padding:0 16px}.pos-main{height:calc(100vh - 48px);display:flex;flex-direction:column;min-height:0;padding:10px 14px 0;overflow:hidden}.pos-entry{background:#fff;border:1px solid #dee2e6;border-radius:6px;padding:10px;flex:0 0 auto}.pos-table-wrap{flex:1 1 auto;min-height:0;margin-top:10px;border:1px solid #dee2e6;border-radius:6px;background:#fff;overflow:auto}.pos-table-wrap table{margin:0}.pos-table-wrap thead th{position:sticky;top:0;z-index:2;background:#fff;box-shadow:0 1px 0 #dee2e6}.pos-cart-row{cursor:pointer}.pos-cart-row:hover{background:#fff3cd}.pos-bottom{flex:0 0 auto;border-top:1px solid #dee2e6;background:#fff;margin:10px -14px 0;padding:8px 14px}.pos-help{font-size:.75rem;color:#6c757d;margin-bottom:6px}.pos-summary{max-width:720px;margin-left:auto}.pos-screen .form-control-lg,.pos-screen .btn-lg{min-height:40px}.pos-screen .table th{font-size:.78rem;padding:.5rem}.pos-screen .table td{font-size:.82rem;padding:.45rem}.pos-screen .modal{z-index:1080}
    .erp-compact .form-label { font-size: .82rem; margin-bottom: .25rem; font-weight: 600; }
    .erp-compact .form-control,
    .erp-compact .form-select { min-height: 34px; padding: .3rem .55rem; font-size: .875rem; }
    .erp-compact .btn { font-size: .875rem; padding: .3rem .7rem; }
    .erp-compact .card-body { padding: .85rem; }
    .erp-compact .card-header { padding: .55rem .85rem; }
    .erp-compact .row { --bs-gutter-x: .65rem; --bs-gutter-y: .55rem; }
    .erp-compact textarea.form-control { min-height: 58px; }
</style>
@section('content')
@if($module !== 'pos')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h3 class="mb-1">{{ $title }}</h3><div class="text-secondary">Mini ERP · {{ ucfirst(str_replace('-', ' ', $module)) }}</div></div>
    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
</div>
@endif

@includeWhen($module === 'pos', 'erp.pos')
@if($module === 'shifts')
<div class="row g-3 mb-4"><div class="col-lg-6"><div class="card shadow-sm h-100"><div class="card-header fw-semibold">Buka Shift</div><div class="card-body"><form method="POST" action="{{ route('erp.shift.store') }}" class="row g-3">@csrf<input type="hidden" name="action" value="open"><div class="col-8"><label class="form-label">Kas Awal</label><input name="opening_cash" type="number" step="0.01" min="0" class="form-control" value="0"></div><div class="col-4 d-flex align-items-end"><button class="btn btn-primary w-100" @if($openShift) disabled @endif>Buka Shift</button></div></form>@if($openShift)<div class="alert alert-success mt-3 mb-0">Shift aktif sejak {{ $openShift->opened_at }}.</div>@endif</div></div></div><div class="col-lg-6"><div class="card shadow-sm h-100"><div class="card-header fw-semibold">Tutup Shift</div><div class="card-body"><form method="POST" action="{{ route('erp.shift.store') }}" class="row g-3">@csrf<input type="hidden" name="action" value="close"><div class="col-8"><label class="form-label">Kas Akhir</label><input name="closing_cash" type="number" step="0.01" min="0" class="form-control" value="0"></div><div class="col-4 d-flex align-items-end"><button class="btn btn-warning w-100" @if(!$openShift) disabled @endif>Tutup Shift</button></div></form></div></div></div></div>
@endif
@if($module === 'purchases')
<div class="card shadow-sm mb-4"><div class="card-header fw-semibold">Pembelian + Penerimaan Barang</div><div class="card-body"><form method="POST" action="{{ route('erp.purchase.store') }}" class="row g-3">@csrf
<div class="col-lg-3"><label class="form-label">Supplier</label><select name="supplier_id" class="form-select" required><option value="">Pilih supplier</option>@foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->code }} — {{ $s->name }}</option>@endforeach</select></div><div class="col-lg-3"><label class="form-label">Produk</label><select name="product_id" class="form-select" required><option value="">Pilih produk</option>@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div><div class="col-lg-3"><label class="form-label">Gudang</label><select name="warehouse_id" class="form-select" required>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div><div class="col-sm-6 col-lg-2"><label class="form-label">Qty</label><input name="qty" type="number" step="0.001" min="0.001" class="form-control" required></div><div class="col-sm-6 col-lg-2"><label class="form-label">Harga Beli</label><input name="unit_cost" type="number" step="0.01" min="0" class="form-control" required></div><div class="col-lg-1 d-flex align-items-end"><button class="btn btn-primary w-100">Simpan</button></div></form></div></div>
@endif
@if($module === 'movements')
<div class="card shadow-sm mb-4"><div class="card-header fw-semibold">Mutasi / Penyesuaian Stok</div><div class="card-body"><form method="POST" action="{{ route('erp.movement.store') }}" class="row g-3">@csrf
<div class="col-lg-4"><label class="form-label">Produk</label><select name="product_id" class="form-select" required>@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div><div class="col-lg-3"><label class="form-label">Gudang</label><select name="warehouse_id" class="form-select" required>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div><div class="col-lg-2"><label class="form-label">Jenis</label><select name="movement_type" class="form-select"><option value="adjustment_in">Masuk</option><option value="adjustment_out">Keluar</option></select></div><div class="col-lg-2"><label class="form-label">Qty</label><input name="qty" type="number" min="0.001" step="0.001" class="form-control" required></div><div class="col-lg-1 d-flex align-items-end"><button class="btn btn-primary w-100">Post</button></div></form></div></div>
@endif
@if($module === 'opname')
<div class="card shadow-sm mb-4"><div class="card-header fw-semibold">Stock Opname</div><div class="card-body"><form method="POST" action="{{ route('erp.opname.store') }}" class="row g-3">@csrf
<div class="col-lg-4"><label class="form-label">Gudang</label><select name="warehouse_id" class="form-select" required>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div><div class="col-lg-4"><label class="form-label">Produk</label><select name="product_id" class="form-select" required>@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div><div class="col-lg-3"><label class="form-label">Qty Aktual</label><input name="actual_qty" type="number" min="0" step="0.001" class="form-control" required></div><div class="col-lg-1 d-flex align-items-end"><button class="btn btn-primary w-100">Post</button></div></form></div></div>
@endif
@if($module === 'bom')
<div class="card shadow-sm mb-4"><div class="card-header fw-semibold">Buat Formula / BOM</div><div class="card-body"><form method="POST" action="{{ route('erp.bom.store') }}" class="row g-3">@csrf
<div class="col-lg-3"><label class="form-label">Produk Jadi</label><select name="product_id" class="form-select" required>@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div><div class="col-lg-2"><label class="form-label">Kode BOM</label><input name="code" class="form-control" required></div><div class="col-lg-3"><label class="form-label">Nama Formula</label><input name="name" class="form-control" required></div><div class="col-lg-2"><label class="form-label">Output</label><input name="output_qty" type="number" min="0.001" step="0.001" class="form-control" required></div><div class="col-lg-3"><label class="form-label">Bahan Utama</label><select name="material_product_id" class="form-select" required>@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div><div class="col-lg-2"><label class="form-label">Qty Bahan</label><input name="material_qty" type="number" min="0.001" step="0.001" class="form-control" required></div><div class="col-lg-2 d-flex align-items-end"><button class="btn btn-primary">Simpan BOM</button></div></form></div></div>
@endif
@if($module === 'production')
<div class="card shadow-sm mb-4"><div class="card-header fw-semibold">Posting Produksi Batako</div><div class="card-body"><form method="POST" action="{{ route('erp.production.store') }}" class="row g-3">@csrf
<div class="col-lg-4"><label class="form-label">Formula / BOM</label><select name="bom_id" class="form-select" required><option value="">Pilih BOM</option>@foreach(DB::table('boms')->where('entity_id',DB::table('entities')->first()->id ?? 0)->where('is_active',1)->orderBy('code')->get() as $b)<option value="{{ $b->id }}">{{ $b->code }} — {{ $b->name }}</option>@endforeach</select></div><div class="col-lg-4"><label class="form-label">Gudang</label><select name="warehouse_id" class="form-select" required>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div><div class="col-lg-2"><label class="form-label">Qty Produksi</label><input name="qty" type="number" min="0.001" step="0.001" class="form-control" required></div><div class="col-lg-2 d-flex align-items-end"><button class="btn btn-primary w-100">Posting</button></div></form></div></div>
@endif

@if(in_array($module,['fleet','deliveries','operations','fleet-costs'],true) && $module==='deliveries')
<div class="card shadow-sm mb-4"><div class="card-header fw-semibold">Buat Pengiriman</div><div class="card-body"><form method="POST" action="{{ route('erp.delivery.store') }}" class="row g-3">@csrf<div class="col-lg-3"><label class="form-label">Kendaraan</label><select name="vehicle_id" class="form-select"><option value="">-</option>@foreach($vehicles as $v)<option value="{{ $v->id }}">{{ $v->code }} — {{ $v->plate_number }}</option>@endforeach</select></div><div class="col-lg-3"><label class="form-label">Driver</label><select name="driver_id" class="form-select"><option value="">-</option>@foreach($drivers as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach</select></div><div class="col-lg-4"><label class="form-label">Tujuan</label><input name="destination" class="form-control" required></div><div class="col-lg-2"><label class="form-label">Jarak KM</label><input name="distance_km" type="number" min="0" step="0.01" class="form-control"></div><div class="col-12"><button class="btn btn-primary">Simpan Pengiriman</button></div></form></div></div>
@elseif($module==='operations')
<div class="card shadow-sm mb-4"><div class="card-header fw-semibold">Operasional Armada</div><div class="card-body"><form method="POST" action="{{ route('erp.operation.store') }}" class="row g-3">@csrf<div class="col-lg-3"><label class="form-label">Kendaraan</label><select name="vehicle_id" class="form-select" required>@foreach($vehicles as $v)<option value="{{ $v->id }}">{{ $v->code }} — {{ $v->plate_number }}</option>@endforeach</select></div><div class="col-lg-2"><label class="form-label">Tanggal</label><input name="operation_date" type="date" class="form-control" value="{{ today()->format('Y-m-d') }}" required></div><div class="col-lg-2"><label class="form-label">KM Awal</label><input name="km_start" type="number" min="0" class="form-control" required></div><div class="col-lg-2"><label class="form-label">KM Akhir</label><input name="km_end" type="number" min="0" class="form-control" required></div><div class="col-lg-1"><label class="form-label">BBM</label><input name="fuel_cost" type="number" min="0" step="0.01" class="form-control"></div><div class="col-lg-2"><label class="form-label">Biaya Lain</label><input name="other_cost" type="number" min="0" step="0.01" class="form-control"></div><div class="col-12"><label class="form-label">Catatan</label><textarea name="notes" class="form-control" rows="2"></textarea></div><div class="col-12"><button class="btn btn-primary">Simpan Operasional</button></div></form></div></div>
@elseif($module==='fleet-costs')
<div class="card shadow-sm mb-4"><div class="card-header fw-semibold">Biaya Armada</div><div class="card-body"><form method="POST" action="{{ route('erp.fleet-cost.store') }}" class="row g-3">@csrf<div class="col-lg-3"><label class="form-label">Kendaraan</label><select name="vehicle_id" class="form-select" required>@foreach($vehicles as $v)<option value="{{ $v->id }}">{{ $v->code }} — {{ $v->plate_number }}</option>@endforeach</select></div><div class="col-lg-2"><label class="form-label">Tanggal</label><input name="cost_date" type="date" class="form-control" value="{{ today()->format('Y-m-d') }}" required></div><div class="col-lg-3"><label class="form-label">Jenis Biaya</label><input name="cost_type" class="form-control" placeholder="BBM / servis / pajak" required></div><div class="col-lg-2"><label class="form-label">Jumlah</label><input name="amount" type="number" min="0" step="0.01" class="form-control" required></div><div class="col-lg-2"><label class="form-label">Keterangan</label><input name="description" class="form-control"></div><div class="col-12"><button class="btn btn-primary">Simpan Biaya</button></div></form></div></div>
@endif

@if($module==='journals')
<div class="card shadow-sm mb-4"><div class="card-header fw-semibold">Jurnal Umum</div><div class="card-body"><form method="POST" action="{{ route('erp.journal.store') }}" class="row g-3">@csrf<div class="col-lg-4"><label class="form-label">Keterangan</label><input name="description" class="form-control" required></div><div class="col-lg-3"><label class="form-label">Debit</label><select name="debit_account" class="form-select" required>@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>@endforeach</select></div><div class="col-lg-3"><label class="form-label">Kredit</label><select name="credit_account" class="form-select" required>@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }} — {{ $a->name }}</option>@endforeach</select></div><div class="col-lg-2"><label class="form-label">Jumlah</label><input name="amount" type="number" min="0.01" step="0.01" class="form-control" required></div><div class="col-12"><button class="btn btn-primary">Posting Jurnal</button></div></form></div></div>
@endif

@if(in_array($module,['ledger','receivables','cashbank','cogs','balance-sheet','cash-flow'],true))
<div class="card shadow-sm mb-4"><div class="card-header d-flex justify-content-between"><span class="fw-semibold">Laporan {{ $title }}</span><span class="badge text-bg-primary">Total Rp {{ number_format($report['total'] ?? 0,0,',','.') }}</span></div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Uraian</th><th class="text-end">Debit / Nilai</th><th class="text-end">Kredit / Saldo</th></tr></thead><tbody>
@if($module==='ledger') @forelse($report['lines'] as $r)<tr><td>{{ $r->journal_date }} · {{ $r->journal_no }} · {{ $r->description }} · {{ $r->code }} {{ $r->name }}</td><td class="text-end">Rp {{ number_format($r->debit,0,',','.') }}</td><td class="text-end">Rp {{ number_format($r->credit,0,',','.') }}</td></tr>@empty<tr><td colspan="3" class="text-center text-secondary py-4">Belum ada jurnal.</td></tr>@endforelse
@elseif(in_array($module,['profit-loss','balance-sheet','cash-flow'],true)) @foreach($report['lines'] as $r)<tr><td>{{ $r['label'] }}</td><td class="text-end">Rp {{ number_format($r['amount'],0,',','.') }}</td><td class="text-end">—</td></tr>@endforeach
@else @forelse($report['lines'] as $r)<tr><td>{{ $r->invoice_no ?? $r->payment_date ?? $r->production_no ?? $r->id }}</td><td class="text-end">Rp {{ number_format($r->total ?? $r->amount ?? $r->total_cost ?? 0,0,',','.') }}</td><td class="text-end">{{ $r->status ?? $r->method ?? '' }}</td></tr>@empty<tr><td colspan="3" class="text-center text-secondary py-4">Belum ada data.</td></tr>@endforelse @endif
</tbody></table></div></div>
@endif

</div>

@if($module==='stock')
<div class="card shadow-sm"><div class="card-header fw-semibold">Posisi Stok</div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Gudang</th><th>Produk</th><th class="text-end">Qty</th><th class="text-end">HPP Rata-rata</th><th class="text-end">Nilai</th></tr></thead><tbody>@forelse($rows as $r)<tr><td>{{ optional(DB::table('warehouses')->find($r->warehouse_id))->name ?? '-' }}</td><td>{{ optional(DB::table('products')->find($r->product_id))->name ?? '-' }}</td><td class="text-end">{{ $r->qty }}</td><td class="text-end">Rp {{ number_format($r->avg_cost,0,',','.') }}</td><td class="text-end">Rp {{ number_format($r->qty*$r->avg_cost,0,',','.') }}</td></tr>@empty<tr><td colspan="5" class="text-center text-secondary py-4">Belum ada stok.</td></tr>@endforelse</tbody></table></div><div class="card-footer">{{ $rows->links() }}</div></div>
@elseif($module==='sales')
<div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold">Register Penjualan</div>
    <div class="card-body border-bottom py-2">
        <form id="sales-period-filter" class="d-flex align-items-end gap-2 flex-nowrap" style="white-space:nowrap;">
            <div>
                <label for="sales-start-date" class="form-label mb-1">Mulai tanggal</label>
                <input type="date" id="sales-start-date" class="form-control" value="{{ request('start_date', now()->startOfMonth()->format('Y-m-d')) }}">
            </div>
            <div>
                <label for="sales-end-date" class="form-label mb-1">Sampai tanggal</label>
                <input type="date" id="sales-end-date" class="form-control" value="{{ request('end_date', now()->endOfMonth()->format('Y-m-d')) }}">
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Tampilkan</button>
            </div>
            <div>
                <button type="button" id="sales-export-excel" class="btn btn-success">Export Excel</button>
            </div>
        </form>
    </div>
    <div class="table-responsive px-2">
        <table id="sales-datatable" class="table table-hover align-middle w-100 mb-0">
            <thead>
                <tr>
                    <th>No. Invoice</th>
                    <th>Tanggal</th>
                    <th>Customer</th>
                    <th>Kasir</th>
                    <th>Shift</th>                    <th>Pembayaran</th>
                    <th class="text-end">Subtotal</th>
                    <th class="text-end">Diskon</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Dibayar</th>
                    <th class="text-end">Kembalian</th>
                    <th>Status</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = new DataTable('#sales-datatable', {
        processing: true,
        serverSide: true,
        pageLength: 15,
        lengthMenu: [[15, 25, 50, 100], [15, 25, 50, 100]],
        ajax: {
            url: @json(route('pos.penjualan.data')),
            data: function (d) {
                d.start_date = document.getElementById('sales-start-date').value;
                d.end_date = document.getElementById('sales-end-date').value;
            }
        },
        order: [[1, 'desc']],
        rowCallback: function (row, data) {
            row.classList.add('sales-detail-row');
            row.title = 'Klik untuk melihat detail penjualan';
            row.onclick = function () { loadSaleDetail(data.id); };
        },
        columns: [
            { data: 'invoice_no', className: 'fw-semibold' },
            { data: 'sale_date', render: function (data) {
                if (!data) return '-';
                const d = new Date(data.replace(' ', 'T'));
                return isNaN(d) ? data : d.toLocaleString('id-ID');
            }},
            { data: 'customer_name', defaultContent: '-' },
            { data: 'cashier_name', defaultContent: '-' },
            { data: 'shift_id', render: data => data ? '#' + data : '-' },
            { data: 'payment_methods', defaultContent: '-' },
            { data: 'subtotal', className: 'text-end', render: data => 'Rp ' + Number(data || 0).toLocaleString('id-ID') },
            { data: 'discount', className: 'text-end', render: data => 'Rp ' + Number(data || 0).toLocaleString('id-ID') },
            { data: 'total', className: 'text-end fw-semibold', render: data => 'Rp ' + Number(data || 0).toLocaleString('id-ID') },
            { data: 'paid_amount', className: 'text-end', render: data => 'Rp ' + Number(data || 0).toLocaleString('id-ID') },
            { data: 'change_amount', className: 'text-end', render: data => 'Rp ' + Number(data || 0).toLocaleString('id-ID') },
            { data: 'status', render: data => '<span class="badge text-bg-success">' + String(data || '-').toUpperCase() + '</span>' }
        ]
    });

    document.getElementById('sales-export-excel').addEventListener('click', function () {
        const start = document.getElementById('sales-start-date').value;
        const end = document.getElementById('sales-end-date').value;
        if (!start || !end) { alert('Periode tanggal wajib diisi.'); return; }
        if (start > end) { alert('Tanggal mulai tidak boleh lebih besar dari tanggal sampai.'); return; }
        window.location.href = @json(url('/pos/penjualan/export-excel')) + '?start_date=' + encodeURIComponent(start) + '&end_date=' + encodeURIComponent(end);
    });

    window.loadSaleDetail = function (saleId) {
        fetch(@json(url('/pos/penjualan')) + '/' + saleId + '/detail', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => {
            if (!response.ok) throw new Error('Gagal mengambil detail penjualan.');
            return response.json();
        })
        .then(payload => {
            const sale = payload.sale;
            document.getElementById('sale-detail-title').textContent = 'Detail Penjualan ' + (sale.invoice_no || '');
            document.getElementById('sale-detail-meta').innerHTML =
                '<div class="col-md-3"><strong>Tanggal:</strong> ' + escapeHtml(sale.sale_date || '-') + '</div>' +
                '<div class="col-md-3"><strong>Customer:</strong> ' + escapeHtml(sale.customer_name || 'Umum') + '</div>' +
                '<div class="col-md-3"><strong>Kasir:</strong> ' + escapeHtml(sale.cashier_name || '-') + '</div>' +
                '<div class="col-md-3"><strong>Status:</strong> ' + escapeHtml(String(sale.status || '-').toUpperCase()) + '</div>';

            const body = document.getElementById('sale-detail-items');
            body.innerHTML = '';
            (payload.items || []).forEach((item, index) => {
                body.insertAdjacentHTML('beforeend', '<tr>' +
                    '<td>' + (index + 1) + '</td>' +
                    '<td>' + escapeHtml(item.sku || '-') + '</td>' +
                    '<td>' + escapeHtml(item.name || '-') + '</td>' +
                    '<td class="text-end">' + Number(item.qty || 0).toLocaleString('id-ID') + '</td>' +
                    '<td class="text-end">Rp ' + Number(item.unit_price || 0).toLocaleString('id-ID') + '</td>' +
                    '<td class="text-end">Rp ' + Number(item.discount || 0).toLocaleString('id-ID') + '</td>' +
                    '<td class="text-end fw-semibold">Rp ' + Number(item.total || 0).toLocaleString('id-ID') + '</td>' +
                '</tr>');
            });
            document.getElementById('sale-detail-subtotal').textContent = 'Rp ' + Number(sale.subtotal || 0).toLocaleString('id-ID');
            document.getElementById('sale-detail-discount').textContent = 'Rp ' + Number(sale.discount || 0).toLocaleString('id-ID');
            document.getElementById('sale-detail-total').textContent = 'Rp ' + Number(sale.total || 0).toLocaleString('id-ID');
            document.getElementById('sale-detail-paid').textContent = 'Rp ' + Number(sale.paid_amount || 0).toLocaleString('id-ID');
            document.getElementById('sale-detail-change').textContent = 'Rp ' + Number(sale.change_amount || 0).toLocaleString('id-ID');
            document.getElementById('sale-detail-payment').textContent = sale.payment_methods || '-';
            window.currentSaleDetail = {
                invoice_no: sale.invoice_no,                sale_date: sale.sale_date,
                cashier: sale.cashier_name || '-',
                shift_id: sale.shift_id || '-',
                customer: sale.customer_name || 'Umum',
                subtotal: sale.subtotal,
                discount: sale.discount,
                total: sale.total,
                paid_amount: sale.paid_amount,
                change_amount: sale.change_amount,
                payment_method: sale.payment_methods || '-',
                entity: payload.entity || {},
                items: (payload.items || []).map(item => ({
                    name: item.name,
                    qty: item.qty,
                    price: item.unit_price,
                    selling_unit_code: item.selling_unit_code || '-'
                }))
            };
            bootstrap.Modal.getOrCreateInstance(document.getElementById('sale-detail-modal')).show();
        })
        .catch(error => alert(error.message));
    };

    
    document.getElementById('sale-detail-print').addEventListener('click', function () {
        printSaleReceipt(window.currentSaleDetail);
    });

    function printSaleReceipt(r) {
        if (!r) return;
        let w = window.open('', '_blank', 'width=420,height=700');
        if (!w) { alert('Popup diblokir browser. Izinkan popup untuk mencetak.'); return; }
        let money = n => 'Rp ' + Math.round(n || 0).toLocaleString('id-ID');
        let esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
        let rows = (r.items || []).map(x => {
            let qty = Number(x.qty).toLocaleString('id-ID');
            let price = Math.round(x.price || 0).toLocaleString('id-ID');
            let line = Math.round((+x.price || 0) * (+x.qty || 0)).toLocaleString('id-ID');
            return '<tr><td colspan="2"><b>' + esc(x.name) + '</b></td></tr><tr><td>' + qty + ' ' + esc(x.selling_unit_code || '-') + ' x ' + price + '</td><td class="right">' + line + '</td></tr>';
        }).join('');
        let entity = r.entity || {};
        let address = entity.address ? '<div>' + esc(entity.address) + '</div>' : '';
        let phone = entity.phone ? '<div>Telp. ' + esc(entity.phone) + '</div>' : '';
        let discountRow = Number(r.discount || 0) > 0 ? '<div class="row"><span>Diskon</span><b>- ' + money(r.discount) + '</b></div>' : '';
        let html = '<!doctype html><html><head><meta charset="utf-8"><title>' + esc(r.invoice_no) + '</title><style>@page{size:80mm auto;margin:0}*{box-sizing:border-box}html,body{margin:0;padding:0;width:80mm}body{font-family:Arial,sans-serif;font-size:11px;line-height:1.35;padding:4mm 4mm 6mm;color:#000}.center{text-align:center}.kop{font-weight:700;font-size:16px;margin-bottom:2px}.meta{margin-top:5px}.line{border-top:1px dashed #000;margin:6px 0}.row{display:flex;justify-content:space-between;gap:8px}.right{text-align:right}.items{width:100%;border-collapse:collapse}.items td{padding:1px 0;vertical-align:top}.items .right{white-space:nowrap}.grand{font-size:14px;margin-top:4px}.footer{margin-top:10px;text-align:center}.small{font-size:10px}@media print{body{padding-bottom:2mm}}</style></head><body><div class="center"><div class="kop">' + esc(entity.name || 'MINI ERP') + '</div>' + address + phone + '<div class="small">TOKO BANGUNAN & PRODUKSI</div></div><div class="line"></div><div>No. Transaksi : ' + esc(r.invoice_no) + '</div><div>Tanggal/Jam : ' + esc(r.sale_date || '') + '</div><div>Kasir : ' + esc(r.cashier || '') + '</div><div>Shift : ' + esc(r.shift_id || '') + '</div><div>Customer : ' + esc(r.customer || 'Umum') + '</div><div class="line"></div><table class="items">' + rows + '</table><div class="line"></div><div class="row"><span>Subtotal</span><b>' + money(r.subtotal) + '</b></div>' + discountRow + '<div class="row grand"><span>TOTAL</span><b>' + money(r.total) + '</b></div><div class="line"></div><div class="row"><span>Pembayaran</span><b>' + esc(r.payment_method || '') + '</b></div><div class="row"><span>Dibayar</span><b>' + money(r.paid_amount) + '</b></div><div class="row"><span>Kembalian</span><b>' + money(r.change_amount) + '</b></div><div class="footer"><b>TERIMA KASIH</b><div>Selamat berbelanja</div><div class="small">Barang yang sudah dibeli tidak dapat dikembalikan tanpa bukti transaksi.</div><div class="line"></div><div class="small">Powered by MINI ERP</div></div><script>window.onload=function(){window.focus();window.print()}<\\/script></body></html>';
        w.document.open(); w.document.write(html); w.document.close();
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[char];
        });
    }

    document.getElementById('sales-period-filter').addEventListener('submit', function (e) {
        e.preventDefault();
        const start = document.getElementById('sales-start-date').value;
        const end = document.getElementById('sales-end-date').value;
        if (!start || !end || start > end) {
            alert('Periode tanggal tidak valid.');
            return;
        }
        table.ajax.reload(null, true);
    });
});
</script>
@endpush

<style>
    #sales-datatable tbody tr.sales-detail-row { cursor: pointer; }
    #sales-datatable tbody tr.sales-detail-row:hover { background-color: rgba(13, 110, 253, .06); }
</style>

<div class="modal fade" id="sale-detail-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="sale-detail-title">Detail Penjualan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div id="sale-detail-meta" class="row g-2 small mb-3"></div>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-3">
                        <thead><tr><th>#</th><th>SKU</th><th>Produk</th><th class="text-end">Qty</th><th class="text-end">Harga</th><th class="text-end">Diskon</th><th class="text-end">Total</th></tr></thead>
                        <tbody id="sale-detail-items"><tr><td colspan="7" class="text-center text-secondary">Tidak ada detail.</td></tr></tbody>
                    </table>
                </div>
                <div class="row justify-content-end small">
                    <div class="col-md-5">
                        <div class="d-flex justify-content-between"><span>Subtotal</span><strong id="sale-detail-subtotal">Rp 0</strong></div>
                        <div class="d-flex justify-content-between"><span>Diskon</span><strong id="sale-detail-discount">Rp 0</strong></div>
                        <div class="d-flex justify-content-between fs-6 border-top pt-2 mt-2"><span>Total</span><strong id="sale-detail-total">Rp 0</strong></div>
                        <div class="d-flex justify-content-between"><span>Dibayar</span><strong id="sale-detail-paid">Rp 0</strong></div>
                        <div class="d-flex justify-content-between"><span>Kembalian</span><strong id="sale-detail-change">Rp 0</strong></div>
                        <div class="d-flex justify-content-between"><span>Pembayaran</span><strong id="sale-detail-payment">-</strong></div>
                    </div>
                </div>
            </div>            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-primary" id="sale-detail-print">Cetak</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

@elseif($module === 'payments')
<div class="card shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Pembayaran</span>
        <button class="btn btn-primary btn-sm" disabled>+ Pembayaran</button>
    </div>
    <div class="card-body border-bottom py-2">
        <form id="payments-period-filter" class="d-flex align-items-end gap-2 flex-nowrap" style="white-space:nowrap;">
            <div>
                <label for="payments-start-date" class="form-label mb-1">Mulai tanggal</label>
                <input type="date" id="payments-start-date" class="form-control" value="{{ now()->startOfMonth()->toDateString() }}">
            </div>
            <div>
                <label for="payments-end-date" class="form-label mb-1">Sampai tanggal</label>
                <input type="date" id="payments-end-date" class="form-control" value="{{ now()->endOfMonth()->toDateString() }}">
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Tampilkan</button>
            </div>
            <div>
                <a id="payments-export" href="{{ route('pos.pembayaran.export-excel') }}" class="btn btn-success">Export Excel</a>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table id="payments-datatable" class="table table-hover align-middle w-100 mb-0">
            <thead><tr>
                <th>No Pembayaran</th><th>Tanggal</th><th>Sumber</th><th>Referensi</th><th>Customer</th><th class="text-end">Jumlah</th><th>Metode</th><th>Kas/Bank</th><th>User</th><th>Status</th>
            </tr></thead>
        </table>
    </div>
</div>
<div class="modal fade" id="payment-detail-modal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title" id="payment-detail-title">Detail Pembayaran</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body" id="payment-detail-body"></div>
<div class="modal-footer"><button type="button" class="btn btn-primary" id="payment-print">Cetak Kwitansi</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button></div>
</div></div></div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const table = new DataTable('#payments-datatable', {
        processing:true, serverSide:true, pageLength:15, lengthMenu:[[15,25,50,100],[15,25,50,100]],
        ajax:{url:@json(route('pos.pembayaran.data')),data:function(d){d.start_date=document.getElementById('payments-start-date').value;d.end_date=document.getElementById('payments-end-date').value;}},
        order:[[1,'desc']],
        rowCallback:function(row,data){row.style.cursor='pointer';row.onclick=function(){fetch(@json(url('/pos/pembayaran')).replace('/pos/pembayaran','/pos/pembayaran/'+data.id+'/detail')).then(r=>r.json()).then(showPayment);};},
        columns:[
            {data:'payment_no',className:'fw-semibold'}, {data:'payment_date',render:d=>d?new Date(d.replace(' ','T')).toLocaleString('id-ID'):'-'},
            {data:'source_name'}, {data:'reference_no'}, {data:'customer_name'}, {data:'amount',className:'text-end',render:d=>Number(d||0).toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2})},
            {data:'method'}, {data:'method',render:d=>d==='Tunai'?'Kas':'Bank'}, {data:'user_name'}, {data:'payment_status',render:d=>'<span class="badge text-bg-success">'+String(d||'posted')+'</span>'}
        ]
    });
    document.getElementById('payments-period-filter').addEventListener('submit',function(e){e.preventDefault();const s=document.getElementById('payments-start-date').value,e2=document.getElementById('payments-end-date').value;if(!s||!e2||s>e2){alert('Periode tanggal tidak valid.');return;}table.ajax.reload(null,true);});
    document.getElementById('payments-export').addEventListener('click',function(e){e.preventDefault();const u=new URL(this.href,window.location.href);u.searchParams.set('start_date',document.getElementById('payments-start-date').value);u.searchParams.set('end_date',document.getElementById('payments-end-date').value);window.location.href=u.toString();});
    let paymentData=null;
    function showPayment(r){paymentData=r;const p=r.payment;document.getElementById('payment-detail-title').textContent='Pembayaran '+p.payment_no;document.getElementById('payment-detail-body').innerHTML='<div class="row g-3"><div class="col-md-6"><div><b>No Pembayaran</b><br>'+p.payment_no+'</div><div class="mt-2"><b>Tanggal</b><br>'+p.payment_date+'</div><div class="mt-2"><b>Sumber</b><br>'+(p.sale_id?'POS / Penjualan':'Lainnya')+'</div><div class="mt-2"><b>Referensi</b><br>'+(p.invoice_no||'-')+'</div></div><div class="col-md-6"><div><b>Customer</b><br>'+(p.customer_name||'Umum')+'</div><div class="mt-2"><b>Jumlah</b><br>Rp '+Number(p.amount||0).toLocaleString('id-ID',{minimumFractionDigits:2})+'</div><div class="mt-2"><b>Metode</b><br>'+p.method+'</div><div class="mt-2"><b>User</b><br>'+(p.user_name||'-')+'</div></div></div>';new bootstrap.Modal(document.getElementById('payment-detail-modal')).show();}
    document.getElementById('payment-print').addEventListener('click',function(){if(!paymentData)return;const p=paymentData.payment,e=paymentData.entity;const w=window.open('','_blank','width=420,height=700');if(!w)return;const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));w.document.write('<!doctype html><html><head><meta charset="utf-8"><title>Kwitansi '+esc(p.payment_no)+'</title><style>@page{size:80mm auto;margin:5mm}body{font-family:Arial,sans-serif;font-size:12px;margin:0}.receipt{width:70mm;margin:auto}.center{text-align:center}.line{border-top:1px dashed #000;margin:8px 0}.row{display:flex;justify-content:space-between;margin:4px 0}.amount{font-size:18px;font-weight:700;text-align:center;margin:12px 0}.sign{margin-top:35px;text-align:right}</style></head><body><div class="receipt"><div class="center"><b>'+esc(e?.name||'MINI ERP')+'</b><br>'+esc(e?.address||'')+'</div><div class="line"></div><div class="center"><b>KWITANSI PENERIMAAN</b></div><div class="line"></div><div class="row"><span>No</span><b>'+esc(p.payment_no)+'</b></div><div class="row"><span>Tanggal</span><span>'+esc(p.payment_date)+'</span></div><div class="row"><span>Dari</span><span>'+esc(p.customer_name||'Umum')+'</span></div><div class="row"><span>Referensi</span><span>'+esc(p.invoice_no||'-')+'</span></div><div class="row"><span>Metode</span><span>'+esc(p.method)+'</span></div><div class="amount">Rp '+Number(p.amount||0).toLocaleString('id-ID',{minimumFractionDigits:2})+'</div><div class="line"></div><div class="center">Terima kasih</div><div class="sign">Penerima<br><br><b>'+esc(p.user_name||'')+'</b></div></div><script>window.onload=function(){window.focus();window.print()}<\/script></body></html>');w.document.close();});
});
</script>
@endpush
@elseif($module === 'profit-loss')
@php
    $allCoa = collect($report['coa_hierarchy'] ?? []);
    $linesByCode = collect($report['lines'] ?? [])->keyBy('code');
    $revenue = $allCoa->where('type','revenue');
    $cogs = $allCoa->where('type','cogs');
    $expense = $allCoa->where('type','expense');
    $money = fn($v) => number_format((float)$v, 2, ',', '.');

    $renderGroup = function ($accounts) use ($linesByCode, $money) {
        foreach ($accounts as $account) {
            $level = (int)($account['level'] ?? 3);
            $line = $linesByCode->get($account['code']);
            $amount = $line['amount'] ?? 0;
            echo '<tr>';
            echo '<td style="padding-left:' . (0.75 + max(0, $level - 1) * 1.5) . 'rem;">' . e($account['code']) . '</td>';
            echo '<td class="' . ($level < 3 ? 'fw-semibold' : '') . '">' . e($account['name']) . '</td>';
            echo '<td class="text-end">' . ($level === 3 ? e($money($amount)) : '') . '</td>';
            echo '</tr>';
        }
    };
@endphp
<div class="card shadow-sm">
    <div class="card-header fw-semibold">Laba Rugi</div>
    <div class="card-body border-bottom py-2">
        <form method="GET" class="d-flex align-items-end gap-2 flex-nowrap" style="white-space:nowrap;">
            <div><label class="form-label">Mulai tanggal</label><input type="date" name="start_date" class="form-control" value="{{ request('start_date', now()->startOfMonth()->toDateString()) }}"></div>
            <div><label class="form-label">Sampai tanggal</label><input type="date" name="end_date" class="form-control" value="{{ request('end_date', now()->endOfMonth()->toDateString()) }}"></div>
            <button class="btn btn-primary">Tampilkan</button>
            <a href="{{ route('akuntansi.laba-rugi.export-excel', request()->only(['start_date','end_date'])) }}" class="btn btn-success text-nowrap" title="Export Excel">⬇ Export Excel</a>
        </form>
    </div>
    <div class="card-body">
        <div class="text-center mb-4">
            <h5 class="mb-1">{{ $entity->name ?? 'Entitas Utama' }}</h5>
            <div class="fw-semibold">LAPORAN LABA RUGI</div>
            <small class="text-secondary">Periode {{ request('start_date', now()->startOfMonth()->toDateString()) }} s/d {{ request('end_date', now()->endOfMonth()->toDateString()) }}</small>
        </div>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead class="table-light"><tr><th>Kode</th><th>Nama Akun</th><th class="text-end">Jumlah</th></tr></thead>
                <tbody>
                    <tr class="fw-bold table-light"><td colspan="2">PENDAPATAN</td><td></td></tr>
                    @php $renderGroup($revenue); @endphp
                    <tr class="fw-bold border-top"><td colspan="2">TOTAL PENDAPATAN</td><td class="text-end">{{ $money($report['revenue'] ?? 0) }}</td></tr>

                    <tr class="fw-bold table-light"><td colspan="2">HPP</td><td></td></tr>
                    @php $renderGroup($cogs); @endphp
                    <tr class="fw-bold border-top"><td colspan="2">TOTAL HPP</td><td class="text-end">{{ $money($report['cogs'] ?? 0) }}</td></tr>
                    <tr class="fw-bold border-top"><td colspan="2">LABA KOTOR</td><td class="text-end">{{ $money($report['gross_profit'] ?? 0) }}</td></tr>

                    <tr class="fw-bold table-light"><td colspan="2">BIAYA</td><td></td></tr>
                    @php $renderGroup($expense); @endphp
                    <tr class="fw-bold border-top"><td colspan="2">TOTAL BIAYA</td><td class="text-end">{{ $money($report['expense'] ?? 0) }}</td></tr>
                    <tr class="fw-bold border-top table-light"><td colspan="2">LABA / (RUGI) BERSIH</td><td class="text-end">{{ $money($report['net_profit'] ?? 0) }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@elseif(in_array($module,['purchases','receipts','payables','shifts','movements','opname','bom','production','production-results','material-usage','production-cost','fleet','deliveries','operations','fleet-costs','journals'],true))
<div class="card shadow-sm"><div class="card-header fw-semibold">Data {{ $title }}</div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>ID</th><th>Referensi</th><th>Tanggal</th><th>Status</th><th class="text-end">Nilai</th></tr></thead><tbody>@forelse($rows as $r)<tr><td>{{ $r->id }}</td><td>{{ $r->invoice_no ?? $r->purchase_no ?? $r->delivery_no ?? $r->production_no ?? $r->journal_no ?? ($r->code ?? '-') }}</td><td>{{ $r->sale_date ?? $r->purchase_date ?? $r->payment_date ?? $r->operation_date ?? $r->cost_date ?? $r->journal_date ?? $r->created_at ?? '-' }}</td><td><span class="badge text-bg-secondary">{{ $r->status ?? $r->method ?? $r->movement_type ?? 'data' }}</span></td><td class="text-end">Rp {{ number_format($r->total ?? $r->amount ?? $r->total_cost ?? 0,0,',','.') }}</td></tr>@empty<tr><td colspan="5" class="text-center text-secondary py-4">Belum ada data.</td></tr>@endforelse</tbody></table></div>@if(is_object($rows) && method_exists($rows,'links'))<div class="card-footer">{{ $rows->links() }}</div>@endif</div>
@endif
@endsection