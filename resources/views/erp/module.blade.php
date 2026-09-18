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

@if(in_array($module,['ledger','receivables','cashbank','cogs','profit-loss','balance-sheet','cash-flow'],true))
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
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span>Register Penjualan</span>
        <span class="badge text-bg-light">Transaksi hasil posting POS</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>No. Invoice</th>
                    <th>Tanggal</th>
                    <th>Customer</th>
                    <th>Kasir</th>
                    <th>Shift</th>
                    <th>Pembayaran</th>
                    <th class="text-end">Subtotal</th>
                    <th class="text-end">Diskon</th>
                    <th class="text-end">Total</th>
                    <th>Status</th>
                    <th class="text-center">Detail</th>
                </tr>
            </thead>
            <tbody>
            @forelse($rows as $r)
                <tr>
                    <td class="fw-semibold">{{ $r->invoice_no }}</td>
                    <td>{{ \Carbon\Carbon::parse($r->sale_date)->format('d/m/Y H:i') }}</td>
                    <td>{{ $r->customer_name }}</td>
                    <td>{{ $r->cashier_name }}</td>
                    <td>#{{ $r->shift_id ?? '-' }}</td>
                    <td>{{ $r->payment_methods ?: '-' }}</td>
                    <td class="text-end">Rp {{ number_format($r->subtotal,0,',','.') }}</td>
                    <td class="text-end">{{ (float)$r->discount > 0 ? 'Rp '.number_format($r->discount,0,',','.') : '—' }}</td>
                    <td class="text-end fw-semibold">Rp {{ number_format($r->total,0,',','.') }}</td>
                    <td><span class="badge text-bg-success">{{ strtoupper($r->status) }}</span></td>
                    <td class="text-center">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#saleDetail{{ $r->id }}">Lihat</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="text-center text-secondary py-4">Belum ada transaksi penjualan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if(is_object($rows) && method_exists($rows,'links'))
        <div class="card-footer">{{ $rows->links() }}</div>
    @endif
</div>

@foreach($rows as $r)
<div class="modal fade" id="saleDetail{{ $r->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-1">Detail Penjualan</h5>
                    <div class="small text-secondary">{{ $r->invoice_no }}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-3">
                    <div class="col-md-4"><div class="text-secondary small">Tanggal</div><div class="fw-semibold">{{ \Carbon\Carbon::parse($r->sale_date)->format('d/m/Y H:i') }}</div></div>
                    <div class="col-md-4"><div class="text-secondary small">Customer</div><div class="fw-semibold">{{ $r->customer_name }}</div></div>
                    <div class="col-md-4"><div class="text-secondary small">Kasir</div><div class="fw-semibold">{{ $r->cashier_name }}</div></div>
                </div>
                <div class="table-responsive border rounded">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>SKU</th><th>Produk</th><th class="text-end">Qty</th><th class="text-end">Harga</th><th class="text-end">Diskon</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                        @forelse(($saleDetails[$r->id] ?? collect()) as $item)
                            <tr>
                                <td>{{ $item->sku ?: '-' }}</td>
                                <td>{{ $item->name }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($item->qty,3,',','.'),'0'),',') }}</td>
                                <td class="text-end">Rp {{ number_format($item->unit_price,0,',','.') }}</td>
                                <td class="text-end">{{ (float)$item->discount > 0 ? 'Rp '.number_format($item->discount,0,',','.') : '—' }}</td>
                                <td class="text-end">Rp {{ number_format($item->total,0,',','.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-secondary py-3">Detail barang tidak tersedia.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="row justify-content-end mt-3">
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between"><span>Subtotal</span><span>Rp {{ number_format($r->subtotal,0,',','.') }}</span></div>
                        <div class="d-flex justify-content-between"><span>Diskon</span><span>Rp {{ number_format($r->discount,0,',','.') }}</span></div>
                        <div class="d-flex justify-content-between fs-5 fw-bold border-top mt-2 pt-2"><span>TOTAL</span><span>Rp {{ number_format($r->total,0,',','.') }}</span></div>
                        <div class="d-flex justify-content-between mt-2"><span>Metode Pembayaran</span><span>{{ $r->payment_methods ?: '-' }}</span></div>
                        <div class="d-flex justify-content-between"><span>Dibayar</span><span>Rp {{ number_format($r->paid_amount,0,',','.') }}</span></div>
                        <div class="d-flex justify-content-between"><span>Kembalian</span><span>Rp {{ number_format($r->change_amount,0,',','.') }}</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endforeach

@elseif(in_array($module,['payments','purchases','receipts','payables','shifts','movements','opname','bom','production','production-results','material-usage','production-cost','fleet','deliveries','operations','fleet-costs','journals'],true))
<div class="card shadow-sm"><div class="card-header fw-semibold">Data {{ $title }}</div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>ID</th><th>Referensi</th><th>Tanggal</th><th>Status</th><th class="text-end">Nilai</th></tr></thead><tbody>@forelse($rows as $r)<tr><td>{{ $r->id }}</td><td>{{ $r->invoice_no ?? $r->purchase_no ?? $r->delivery_no ?? $r->production_no ?? $r->journal_no ?? ($r->code ?? '-') }}</td><td>{{ $r->sale_date ?? $r->purchase_date ?? $r->payment_date ?? $r->operation_date ?? $r->cost_date ?? $r->journal_date ?? $r->created_at ?? '-' }}</td><td><span class="badge text-bg-secondary">{{ $r->status ?? $r->method ?? $r->movement_type ?? 'data' }}</span></td><td class="text-end">Rp {{ number_format($r->total ?? $r->amount ?? $r->total_cost ?? 0,0,',','.') }}</td></tr>@empty<tr><td colspan="5" class="text-center text-secondary py-4">Belum ada data.</td></tr>@endforelse</tbody></table></div>@if(is_object($rows) && method_exists($rows,'links'))<div class="card-footer">{{ $rows->links() }}</div>@endif</div>
@endif
@endsection