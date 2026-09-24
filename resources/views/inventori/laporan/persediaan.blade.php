@extends('layouts.app')
@section('content')
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div><div class="fw-semibold">STOK</div><div class="small text-secondary">Saldo persediaan per gudang dan item</div></div>
        <button type="button" class="btn btn-success btn-sm" id="btn-export-stock">Export Excel</button>
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
    <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2"><div class="small text-secondary">@if($rows->total() > 0) Menampilkan {{ $rows->firstItem() }}–{{ $rows->lastItem() }} dari {{ $rows->total() }} item @else Tidak ada stok @endif</div>@if($rows->lastPage() > 1)<nav aria-label="Pagination Stok"><ul class="pagination pagination-sm mb-0"><li class="page-item {{ $rows->onFirstPage() ? "disabled" : "" }}"><a class="page-link" href="{{ $rows->previousPageUrl() ?? "#" }}">‹</a></li>@foreach($rows->getUrlRange(max(1,$rows->currentPage()-2),min($rows->lastPage(),$rows->currentPage()+2)) as $page=>$url)<li class="page-item {{ $page==$rows->currentPage() ? "active" : "" }}"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>@endforeach<li class="page-item {{ $rows->currentPage()>=$rows->lastPage() ? "disabled" : "" }}"><a class="page-link" href="{{ $rows->nextPageUrl() ?? "#" }}">›</a></li></ul></nav>@endif</div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js"></script>
<script>
document.getElementById('btn-export-stock')?.addEventListener('click', async function () {
    if (typeof XLSX === 'undefined') { alert('Library Excel belum termuat. Silakan refresh halaman lalu coba lagi.'); return; }
    const btn=this; btn.disabled=true;
    const params=new URLSearchParams({warehouse_id:document.querySelector('[name="warehouse_id"]')?.value||'',search:document.querySelector('[name="search"]')?.value||''});
    try {
        const res=await fetch('{{ route('inventori.stok.export') }}?'+params.toString(),{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
        const payload=await res.json(); if(!res.ok) throw new Error(payload.message||'Export gagal.');
        const today=new Intl.DateTimeFormat('id-ID',{day:'2-digit',month:'2-digit',year:'numeric'}).format(new Date());
        const data=payload.rows.map(r=>[String(r.code||r.sku||''),String(r.product_name||''),String(r.unit_name||r.unit_code||'-'),String(r.warehouse_name||''),Number(r.qty||0),Number(r.avg_cost||0),Number(r.stock_value||0)]);
        const ws=XLSX.utils.aoa_to_sheet([[payload.entity_name||'NAMA ENTITAS'],['LAPORAN STOK'],['Tgl Export : '+today],[],['Kode Item','Item','Satuan','Gudang','Qty','HPP Rata-rata','Nilai'],...data]);
        ws['!merges']=[{s:{r:0,c:0},e:{r:0,c:6}},{s:{r:1,c:0},e:{r:1,c:6}},{s:{r:2,c:0},e:{r:2,c:6}}];
        ws['!cols']=[{wch:18},{wch:30},{wch:14},{wch:22},{wch:14},{wch:18},{wch:20}];
        data.forEach((row,i)=>{const n=i+6; ws['E'+n].z='#,##0.###'; ws['F'+n].z='#,##0'; ws['G'+n].z='#,##0';});
        const wb=XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb,ws,'Stok'); XLSX.writeFile(wb,'laporan-stok-'+new Date().toISOString().slice(0,10)+'.xlsx');
    } catch(e){alert(e.message||'Export gagal.');} finally{btn.disabled=false;}
});
</script>
@endpush
