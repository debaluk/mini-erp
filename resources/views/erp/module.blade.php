@extends('layouts.app')
<style>
    /* Mini ERP compact form UI */
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
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h3 class="mb-1">{{ $title }}</h3><div class="text-secondary">Mini ERP · {{ ucfirst(str_replace('-', ' ', $module)) }}</div></div>
    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
</div>

@if($module === 'pos')
<div class="erp-compact">
<div class="card shadow-sm mb-4">
<div class="card-header d-flex justify-content-between align-items-center"><span class="fw-semibold">POS Retail</span>@if($openShift)<span class="badge text-bg-success">Shift Aktif</span>@else<span class="badge text-bg-warning">Shift Belum Dibuka</span>@endif</div>
<div class="card-body">
<form method="POST" action="{{ route('erp.pos.add') }}" class="row g-2 mb-3" id="posAddForm">@csrf
<div class="col-md-5">
<label class="form-label">Cari / Pilih Barang</label>
<input id="posProductSearch" type="text" class="form-control" list="posProductList" placeholder="Ketik nama atau barcode..." autocomplete="off" required>
<datalist id="posProductList">
@foreach($products as $p)
@if((float)$p->stock_qty > 0)
<option value="{{ $p->name }}" data-id="{{ $p->id }}">{{ $p->name }} — {{ $p->sku }}{{ $p->barcode ? ' · '.$p->barcode : '' }}</option>
<option value="{{ $p->barcode }}" data-id="{{ $p->id }}">{{ $p->name }} — {{ $p->barcode }}</option>
@endif
@endforeach
</datalist>
<input type="hidden" id="posProduct" name="product_id">
</div>
<div class="col-md-2">
<label class="form-label">Satuan Jual</label>
<input id="posUnit" type="text" class="form-control" value="-" readonly>
</div>
<div class="col-md-2">
<label class="form-label">Qty</label>
<input id="posQty" name="qty" type="number" min="0.001" step="0.001" class="form-control" value="1" required>
</div>
<div class="col-md-3 d-flex align-items-end">
<button id="posAddButton" class="btn btn-primary w-100" @disabled(!$openShift)>+ Tambah Barang</button>
</div>
<div class="col-12"><div id="posStockInfo" class="small text-secondary">Ketik nama barang atau barcode untuk memilih barang.</div></div>
</form>
<div class="table-responsive border rounded"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Kode</th><th>Nama Barang</th><th class="text-end">Harga</th><th class="text-end">Qty</th><th>Satuan</th><th class="text-end">Diskon</th><th class="text-end">Sub Total</th></tr></thead><tbody>
@php
    $posSubtotal = 0;
@endphp
@forelse($posCart as $key=>$item)
@php
    $line = (float) $item['price'] * (float) $item['qty'];
    $posSubtotal += $line;
@endphp
<tr data-bs-toggle="modal" data-bs-target="#posEditModal{{ $key }}" style="cursor:pointer"><td>{{ $item['code'] }}</td><td>{{ $item['name'] }}</td><td class="text-end">Rp {{ number_format($item['price'],0,',','.') }}</td><td class="text-end">{{ rtrim(rtrim(number_format($item['qty'],3,',','.'),'0'),',') }}</td><td>{{ $item['selling_unit_code'] ?? '-' }}</td><td class="text-end">—</td><td class="text-end fw-semibold">Rp {{ number_format($line,0,',','.') }}</td></tr>
@empty<tr><td colspan="7" class="text-center text-secondary py-5">Belum ada barang. Pilih barang lalu klik Tambah Barang.</td></tr>@endforelse
</tbody></table></div>
@foreach($posCart as $key=>$item)
<div class="modal fade" id="posEditModal{{ $key }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Barang</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="POST" action="{{ route('erp.pos.update',$key) }}">@csrf @method('PUT')
<div class="modal-body"><div class="small text-secondary mb-3">{{ $item['code'] }} · {{ $item['name'] }}</div><label class="form-label">Harga Transaksi</label><input name="price" type="number" min="0" step="0.01" class="form-control mb-3" value="{{ $item['price'] }}" required><label class="form-label">Qty</label><input name="qty" type="number" min="0.001" step="0.001" class="form-control" value="{{ $item['qty'] }}" required></div>
<div class="modal-footer justify-content-between"><button type="submit" formaction="{{ route('erp.pos.remove',$key) }}" formmethod="POST" class="btn btn-outline-danger" onclick="return confirm('Hapus barang ini dari transaksi?')">Hapus</button><button class="btn btn-primary">Simpan</button></div></form></div></div></div>
@endforeach
@php
    $posDiscount = old('discount', 0);
    $posTotal = max(0, $posSubtotal - (float) $posDiscount);
@endphp
<div class="row justify-content-end mt-3"><div class="col-lg-5"><div class="border rounded p-3 bg-light">
<div class="d-flex justify-content-between mb-2"><span>Subtotal</span><strong>Rp {{ number_format($posSubtotal,0,',','.') }}</strong></div>
<form method="POST" action="{{ route('erp.pos.store') }}">@csrf
<label class="form-label">Diskon Global</label><input id="posDiscount" name="discount" type="number" min="0" step="0.01" class="form-control text-end mb-2" value="{{ $posDiscount }}">
<div class="d-flex justify-content-between fs-5 border-top pt-2 mb-3"><span>Total</span><strong id="posTotal">Rp {{ number_format($posTotal,0,',','.') }}</strong></div>
<label class="form-label">Pembayaran</label><select name="payment_method" class="form-select mb-2"><option>Tunai</option><option>Transfer</option><option>QRIS</option></select>
<label class="form-label">Nominal Bayar</label><input id="posPayment" name="payment_amount" type="number" min="0" step="0.01" class="form-control text-end mb-3" value="{{ $posTotal }}" required>
<div class="d-flex gap-2"><button formaction="{{ route('erp.pos.clear') }}" formmethod="POST" class="btn btn-outline-secondary flex-fill" @disabled(empty($posCart))>Kosongkan</button><button class="btn btn-success flex-fill" @disabled(empty($posCart)||!$openShift)>Bayar & Posting</button></div>
</form>
@if(!$openShift)<div class="small text-danger mt-2">Buka Shift Kasir sebelum transaksi diposting.</div>@endif
</div></div></div>
</div></div></div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const d=document.getElementById('posDiscount'),t=document.getElementById('posTotal'),p=document.getElementById('posPayment');
 const search=document.getElementById('posProductSearch'),product=document.getElementById('posProduct'),unit=document.getElementById('posUnit'),stockInfo=document.getElementById('posStockInfo'),addForm=document.getElementById('posAddForm');
 const products=@json($products->map(fn($x)=>['id'=>$x->id,'name'=>$x->name,'sku'=>$x->sku,'barcode'=>$x->barcode,'unit'=>$x->selling_unit_code ?? '-','stock'=>(float)$x->stock_qty])->values());
 const s={{ $posSubtotal }};
 if(d&&t)d.addEventListener('input',()=>{const v=Math.min(Math.max(parseFloat(d.value)||0,0),s);t.textContent='Rp '+Math.round(s-v).toLocaleString('id-ID');if(p)p.value=Math.round(s-v);});
 function selectProduct(){
   const value=(search?.value||'').trim().toLowerCase();
   const item=products.find(x=>String(x.name).toLowerCase()===value||String(x.barcode||'').toLowerCase()===value||String(x.sku||'').toLowerCase()===value);
   if(!item){product.value='';unit.value='-';stockInfo.textContent='Barang tidak ditemukan. Pilih nama barang atau barcode dari hasil pencarian.';return false;}
   product.value=item.id;unit.value=item.unit||'-';
   if(item.stock<=0){stockInfo.textContent='STOK HABIS — barang tidak dapat ditambahkan.';product.value='';return false;}
   stockInfo.textContent='Stok tersedia: '+item.stock.toLocaleString('id-ID')+' '+(item.unit||'');
   return true;
 }
 if(search){search.addEventListener('input',selectProduct);search.addEventListener('change',selectProduct);search.addEventListener('keydown',e=>{if(e.key==='Enter'&&!selectProduct())e.preventDefault();});}
 if(addForm)addForm.addEventListener('submit',e=>{if(!selectProduct()){e.preventDefault();search?.focus();}});
});
</script>
@elseif($module === 'shifts')
<div class="row g-3 mb-4"><div class="col-lg-6"><div class="card shadow-sm h-100"><div class="card-header fw-semibold">Buka Shift</div><div class="card-body"><form method="POST" action="{{ route('erp.shift.store') }}" class="row g-3">@csrf<input type="hidden" name="action" value="open"><div class="col-8"><label class="form-label">Kas Awal</label><input name="opening_cash" type="number" step="0.01" min="0" class="form-control" value="0"></div><div class="col-4 d-flex align-items-end"><button class="btn btn-primary w-100" @if($openShift) disabled @endif>Buka Shift</button></div></form>@if($openShift)<div class="alert alert-success mt-3 mb-0">Shift aktif sejak {{ $openShift->opened_at }}.</div>@endif</div></div></div><div class="col-lg-6"><div class="card shadow-sm h-100"><div class="card-header fw-semibold">Tutup Shift</div><div class="card-body"><form method="POST" action="{{ route('erp.shift.store') }}" class="row g-3">@csrf<input type="hidden" name="action" value="close"><div class="col-8"><label class="form-label">Kas Akhir</label><input name="closing_cash" type="number" step="0.01" min="0" class="form-control" value="0"></div><div class="col-4 d-flex align-items-end"><button class="btn btn-warning w-100" @if(!$openShift) disabled @endif>Tutup Shift</button></div></form></div></div></div></div>
@elseif($module === 'purchases')
<div class="card shadow-sm mb-4"><div class="card-header fw-semibold">Pembelian + Penerimaan Barang</div><div class="card-body"><form method="POST" action="{{ route('erp.purchase.store') }}" class="row g-3">@csrf
<div class="col-lg-3"><label class="form-label">Supplier</label><select name="supplier_id" class="form-select" required><option value="">Pilih supplier</option>@foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->code }} — {{ $s->name }}</option>@endforeach</select></div><div class="col-lg-3"><label class="form-label">Produk</label><select name="product_id" class="form-select" required><option value="">Pilih produk</option>@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div><div class="col-lg-3"><label class="form-label">Gudang</label><select name="warehouse_id" class="form-select" required>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div><div class="col-sm-6 col-lg-2"><label class="form-label">Qty</label><input name="qty" type="number" step="0.001" min="0.001" class="form-control" required></div><div class="col-sm-6 col-lg-2"><label class="form-label">Harga Beli</label><input name="unit_cost" type="number" step="0.01" min="0" class="form-control" required></div><div class="col-lg-1 d-flex align-items-end"><button class="btn btn-primary w-100">Simpan</button></div></form></div></div>
@elseif($module === 'movements')
<div class="card shadow-sm mb-4"><div class="card-header fw-semibold">Mutasi / Penyesuaian Stok</div><div class="card-body"><form method="POST" action="{{ route('erp.movement.store') }}" class="row g-3">@csrf
<div class="col-lg-4"><label class="form-label">Produk</label><select name="product_id" class="form-select" required>@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div><div class="col-lg-3"><label class="form-label">Gudang</label><select name="warehouse_id" class="form-select" required>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div><div class="col-lg-2"><label class="form-label">Jenis</label><select name="movement_type" class="form-select"><option value="adjustment_in">Masuk</option><option value="adjustment_out">Keluar</option></select></div><div class="col-lg-2"><label class="form-label">Qty</label><input name="qty" type="number" min="0.001" step="0.001" class="form-control" required></div><div class="col-lg-1 d-flex align-items-end"><button class="btn btn-primary w-100">Post</button></div></form></div></div>
@elseif($module === 'opname')
<div class="card shadow-sm mb-4"><div class="card-header fw-semibold">Stock Opname</div><div class="card-body"><form method="POST" action="{{ route('erp.opname.store') }}" class="row g-3">@csrf
<div class="col-lg-4"><label class="form-label">Gudang</label><select name="warehouse_id" class="form-select" required>@foreach($warehouses as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select></div><div class="col-lg-4"><label class="form-label">Produk</label><select name="product_id" class="form-select" required>@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div><div class="col-lg-3"><label class="form-label">Qty Aktual</label><input name="actual_qty" type="number" min="0" step="0.001" class="form-control" required></div><div class="col-lg-1 d-flex align-items-end"><button class="btn btn-primary w-100">Post</button></div></form></div></div>
@elseif($module === 'bom')
<div class="card shadow-sm mb-4"><div class="card-header fw-semibold">Buat Formula / BOM</div><div class="card-body"><form method="POST" action="{{ route('erp.bom.store') }}" class="row g-3">@csrf
<div class="col-lg-3"><label class="form-label">Produk Jadi</label><select name="product_id" class="form-select" required>@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div><div class="col-lg-2"><label class="form-label">Kode BOM</label><input name="code" class="form-control" required></div><div class="col-lg-3"><label class="form-label">Nama Formula</label><input name="name" class="form-control" required></div><div class="col-lg-2"><label class="form-label">Output</label><input name="output_qty" type="number" min="0.001" step="0.001" class="form-control" required></div><div class="col-lg-3"><label class="form-label">Bahan Utama</label><select name="material_product_id" class="form-select" required>@foreach($products as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div><div class="col-lg-2"><label class="form-label">Qty Bahan</label><input name="material_qty" type="number" min="0.001" step="0.001" class="form-control" required></div><div class="col-lg-2 d-flex align-items-end"><button class="btn btn-primary">Simpan BOM</button></div></form></div></div>
@elseif($module === 'production')
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
@elseif(in_array($module,['sales','payments','purchases','receipts','payables','shifts','movements','opname','bom','production','production-results','material-usage','production-cost','fleet','deliveries','operations','fleet-costs','journals'],true))
<div class="card shadow-sm"><div class="card-header fw-semibold">Data {{ $title }}</div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>ID</th><th>Referensi</th><th>Tanggal</th><th>Status</th><th class="text-end">Nilai</th></tr></thead><tbody>@forelse($rows as $r)<tr><td>{{ $r->id }}</td><td>{{ $r->invoice_no ?? $r->purchase_no ?? $r->delivery_no ?? $r->production_no ?? $r->journal_no ?? ($r->code ?? '-') }}</td><td>{{ $r->sale_date ?? $r->purchase_date ?? $r->payment_date ?? $r->operation_date ?? $r->cost_date ?? $r->journal_date ?? $r->created_at ?? '-' }}</td><td><span class="badge text-bg-secondary">{{ $r->status ?? $r->method ?? $r->movement_type ?? 'data' }}</span></td><td class="text-end">Rp {{ number_format($r->total ?? $r->amount ?? $r->total_cost ?? 0,0,',','.') }}</td></tr>@empty<tr><td colspan="5" class="text-center text-secondary py-4">Belum ada data.</td></tr>@endforelse</tbody></table></div>@if(is_object($rows) && method_exists($rows,'links'))<div class="card-footer">{{ $rows->links() }}</div>@endif</div>
@endif
@endsection
