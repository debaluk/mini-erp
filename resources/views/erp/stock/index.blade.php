@extends('layouts.app')
@section('content')
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><div class="fw-semibold">STOK</div><div class="small text-secondary">Saldo persediaan per gudang dan item</div></div>
    </div>
    <div class="card-body border-bottom py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label mb-1">Gudang</label><select name="warehouse_id" class="form-select"><option value="">Semua Gudang</option>@foreach($warehouses as $w)<option value="{{$w->id}}" @selected(request('warehouse_id')==$w->id)>{{$w->name}}</option>@endforeach</select></div>
            <div class="col-md-5"><label class="form-label mb-1">Cari Item</label><input name="search" class="form-control" value="{{request('search')}}" placeholder="Kode / SKU / nama item"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Cari</button></div>
            <div class="col-md-2"><a href="{{route('inventori.stok')}}" class="btn btn-outline-secondary w-100">Reset</a></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Kode Item</th><th>Item</th><th>Satuan</th><th>Gudang</th><th class="text-end">Qty</th><th class="text-end">HPP Rata-rata</th><th class="text-end">Nilai</th><th class="text-center">Aksi</th></tr></thead>
            <tbody>
            @forelse($rows as $r)
                <tr>
                    <td class="fw-semibold">{{ $r->code ?: $r->sku }}</td><td>{{ $r->product_name }}</td><td>{{ $r->unit_name ?: $r->unit_code ?: '-' }}</td><td>{{ $r->warehouse_name }}</td>
                    <td class="text-end">{{ number_format($r->qty,3,',','.') }}</td><td class="text-end">Rp {{number_format($r->avg_cost,0,',','.')}}</td><td class="text-end">Rp {{number_format($r->stock_value,0,',','.')}}</td>
                    <td class="text-center"><a class="btn btn-sm btn-outline-primary" href="{{route('inventori.stok.detail',[$r->product_id,$r->warehouse_id])}}">Detail</a></td>
                </tr>
            @empty<tr><td colspan="8" class="text-center text-secondary py-4">Belum ada saldo stok.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    @if($rows->hasPages())<div class="card-footer">{{$rows->links()}}</div>@endif
</div>
@endsection
