@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Edit Work Order / SPK</h4>
        <div class="text-secondary small">Perubahan hanya dapat dilakukan selama SPK masih Open.</div>
    </div>
    <a href="{{ route('produksi.work-order') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

@if($errors->any())
<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ route('produksi.work-order.update', $wo->id) }}">
@csrf
@method('PUT')
<input type="hidden" name="batch_qty" id="batch_qty" value="{{ old('batch_qty', $wo->batch_qty) }}">

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-primary bg-opacity-10 text-primary py-3 fw-semibold"><i class="bi bi-pencil-square me-2"></i>Informasi SPK</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">No. SPK</label>
                <input type="text" class="form-control" value="{{ $wo->wo_no }}" readonly>
            </div>
            <div class="col-md-3">
                <label class="form-label">Tanggal SPK</label>
                <x-date-input-id name="wo_date" :value="old('wo_date', $wo->wo_date)" required />
            </div>
            <div class="col-md-3">
                <label class="form-label">Jumlah Batch</label>
                <input type="number" step="0.001" min="0.001" class="form-control" id="batch_display" value="{{ old('batch_qty', $wo->batch_qty) }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Business Unit</label>
                <select name="business_unit_id" id="wo_bu" class="form-select" required>
                    @foreach($businessUnits as $bu)
                        <option value="{{ $bu->id }}" @selected(old('business_unit_id', $wo->business_unit_id) == $bu->id)>{{ $bu->code }} — {{ $bu->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Gudang Produksi</label>
                <select name="warehouse_id" id="wo_warehouse" class="form-select" required>
                    @foreach($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" data-bu="{{ $warehouse->business_unit_id }}" @selected(old('warehouse_id', $wo->warehouse_id) == $warehouse->id)>{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">BOM / Formula</label>
                <select name="bom_id" id="wo_bom" class="form-select" required>
                    @foreach($boms as $bom)
                        <option value="{{ $bom->id }}" data-bu="{{ $bom->business_unit_id }}" @selected(old('bom_id', $wo->bom_id) == $bom->id)>{{ $bom->code }} — {{ $bom->name }} ({{ $bom->product_name }})</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>

<div id="bom-info" class="card border-primary mb-3">
    <div class="card-header bg-primary bg-opacity-10 text-primary fw-semibold"><i class="bi bi-info-circle me-2"></i>Informasi BOM</div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-3"><div class="small text-secondary">Produk</div><div class="fw-semibold" id="bom-product">-</div></div>
            <div class="col-md-3"><div class="small text-secondary">Kode BOM</div><div class="fw-semibold" id="bom-code">-</div></div>
            <div class="col-md-3"><div class="small text-secondary">Target Produksi</div><div class="fw-bold text-primary fs-5" id="bom-target">0</div></div>
            <div class="col-md-3"><div class="small text-secondary">Estimasi Material</div><div class="fw-bold text-success fs-5" id="material-total">Rp 0</div></div>
        </div>
        <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead class="table-primary"><tr><th>Material</th><th class="text-end">Qty</th><th>Satuan</th><th class="text-end">Harga</th><th class="text-end">Subtotal</th></tr></thead><tbody id="bom-material-rows"><tr><td colspan="5" class="text-center text-secondary">Memuat...</td></tr></tbody></table></div>
    </div>
</div>

@php
    $workerCosts = $woCosts->where('cost_group', 'U');
    $otherCosts = $woCosts->whereIn('cost_group', ['A','S','O']);
    $groupTitles = ['A'=>'Equipment','S'=>'Rent','O'=>'Overhead'];
@endphp

<div class="card border-0 shadow-sm mb-3 cost-section" data-group="U">
    <div class="card-header bg-primary bg-opacity-10 text-primary d-flex justify-content-between align-items-center">
        <div><div class="fw-semibold">Tenaga</div><div class="small text-secondary">Pilih pekerja dan isi estimasi biaya tenaga.</div></div>
        <button type="button" class="btn btn-outline-primary btn-sm add-row"><i class="bi bi-plus-lg me-1"></i>Tambah</button>
    </div>
    <div class="card-body rows">
        @foreach($workerCosts as $cost)
        <div class="row g-2 mb-2 cost-row">
            <div class="col-md-7"><select name="worker_id[]" class="form-select"><option value="">Pilih pekerja</option>@foreach($workers as $worker)<option value="{{ $worker->id }}" @selected($worker->id == $cost->worker_id)>{{ $worker->code }} — {{ $worker->name }}</option>@endforeach</select></div>
            <div class="col-md-3"><input type="text" name="worker_amount[]" class="form-control cost-input text-end" value="{{ number_format($cost->amount,2,',','.') }}" inputmode="decimal"></div>
            <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-row"><i class="bi bi-trash"></i></button></div>
        </div>
        @endforeach
        @if($workerCosts->isEmpty())
        <div class="row g-2 mb-2 cost-row"><div class="col-md-7"><select name="worker_id[]" class="form-select"><option value="">Pilih pekerja</option>@foreach($workers as $worker)<option value="{{ $worker->id }}">{{ $worker->code }} — {{ $worker->name }}</option>@endforeach</select></div><div class="col-md-3"><input type="text" name="worker_amount[]" class="form-control cost-input text-end" inputmode="decimal" placeholder="Total biaya pekerja"></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-row"><i class="bi bi-trash"></i></button></div></div>
        @endif
    </div>
</div>

@foreach(['A','S','O'] as $group)
<div class="card border-0 shadow-sm mb-3 cost-section" data-group="{{ $group }}">
    <div class="card-header bg-primary bg-opacity-10 text-primary d-flex justify-content-between align-items-center">
        <div class="fw-semibold">{{ $groupTitles[$group] }}</div>
        <button type="button" class="btn btn-outline-primary btn-sm add-row"><i class="bi bi-plus-lg me-1"></i>Tambah</button>
    </div>
    <div class="card-body rows">
        @php $items = $otherCosts->where('cost_group', $group); @endphp
        @foreach($items as $cost)
        <div class="row g-2 mb-2 cost-row"><div class="col-md-7"><input type="text" name="cost_description[]" class="form-control" value="{{ $cost->description }}" placeholder="Keterangan"></div><div class="col-md-3"><input type="text" name="cost_amount[]" class="form-control cost-input text-end" value="{{ number_format($cost->amount,2,',','.') }}" inputmode="decimal"></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-row"><i class="bi bi-trash"></i></button></div><input type="hidden" name="cost_group[]" value="{{ $group }}"></div>
        @endforeach
        @if($items->isEmpty())
        <div class="row g-2 mb-2 cost-row"><div class="col-md-7"><input type="text" name="cost_description[]" class="form-control" placeholder="Keterangan"></div><div class="col-md-3"><input type="text" name="cost_amount[]" class="form-control cost-input text-end" inputmode="decimal" placeholder="Estimasi biaya"></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-row"><i class="bi bi-trash"></i></button></div><input type="hidden" name="cost_group[]" value="{{ $group }}"></div>
        @endif
    </div>
</div>
@endforeach

<div class="card border-success mb-3"><div class="card-body d-flex justify-content-between align-items-center"><div><div class="small text-secondary">TOTAL ESTIMASI BIAYA WO/SPK</div><div class="small text-secondary">Material + Tenaga + Equipment + Rent + Overhead</div></div><div class="fw-bold text-success fs-4" id="wo-total">Rp 0</div></div></div>

<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-light fw-semibold">Catatan SPK</div><div class="card-body"><textarea name="notes" class="form-control" rows="4" placeholder="Instruksi atau catatan produksi...">{{ old('notes', $wo->notes) }}</textarea></div></div>

<div class="d-flex justify-content-between align-items-center">
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#mulaiKerjaModal">
        <i class="bi bi-play-circle me-1"></i>Mulai Kerja
    </button>
    <div class="d-flex gap-2">
        <a href="{{ route('produksi.work-order') }}" class="btn btn-light">Batal</a>
        <button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Simpan Perubahan</button>
    </div>
</div>
</form>

<div class="modal fade" id="mulaiKerjaModal" tabindex="-1" aria-labelledby="mulaiKerjaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="{{ route('produksi.work-order.start', $wo->id) }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="mulaiKerjaModalLabel">Mulai Kerja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <label for="started_at" class="form-label">Tanggal Mulai Kerja</label>
                    <input type="date" name="started_at" id="started_at" class="form-control" value="{{ now()->toDateString() }}" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>OK</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const bu=document.getElementById('wo_bu'), warehouse=document.getElementById('wo_warehouse'), bom=document.getElementById('wo_bom');
    const batch=document.getElementById('batch_display'), batchHidden=document.getElementById('batch_qty');
    const mt=document.getElementById('material-total'), target=document.getElementById('bom-target'), rows=document.getElementById('bom-material-rows'), total=document.getElementById('wo-total');
    const money=v=>'Rp '+Number(v||0).toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2});
    const parseMoney=v=>{const r=String(v??'').trim().replace(/[^0-9,.-]/g,'');if(!r)return 0;return r.includes(',')?Number(r.replace(/\./g,'').replace(',','.'))||0:Number(r)||0};
    const syncBatch=()=>{batchHidden.value=batch.value||1};
    const recalc=()=>{let s=parseMoney(mt.dataset.value||0);document.querySelectorAll('.cost-input').forEach(i=>s+=parseMoney(i.value));total.textContent=money(s)};
    const filter=(select,id)=>{[...select.options].forEach(o=>{if(o.value)o.hidden=!!id&&o.dataset.bu!==id});if(select.selectedOptions[0]?.hidden)select.value=''};
    const load=async()=>{
        syncBatch();
        if(!bom.value)return;
        const q=new URLSearchParams({warehouse_id:warehouse.value,batch_qty:batch.value||1});
        const r=await fetch('{{ url('/produksi/work-order/bom') }}/'+bom.value+'/info?'+q,{headers:{Accept:'application/json'}});
        if(!r.ok)return;
        const d=await r.json();
        document.getElementById('bom-product').textContent=(d.bom.product_sku?d.bom.product_sku+' — ':'')+d.bom.product_name;
        document.getElementById('bom-code').textContent=d.bom.code+' — '+d.bom.name;
        target.textContent=Number(d.bom.target_output_qty||0).toLocaleString('id-ID',{maximumFractionDigits:3})+' '+(d.bom.output_unit||'');
        mt.dataset.value=d.material_cost||0; mt.textContent=money(d.material_cost);
        rows.innerHTML=(d.materials||[]).map(i=>'<tr><td>'+i.sku+' — '+i.name+'</td><td class="text-end">'+Number(i.base_qty||0).toLocaleString('id-ID',{maximumFractionDigits:3})+'</td><td>'+i.unit+'</td><td class="text-end">'+money(i.unit_cost)+'</td><td class="text-end">'+money(i.line_cost)+'</td></tr>').join('')||'<tr><td colspan="5" class="text-center text-secondary">BOM belum memiliki material.</td></tr>';
        recalc();
    };
    bu.addEventListener('change',()=>{filter(warehouse,bu.value);filter(bom,bu.value);load()});
    warehouse.addEventListener('change',load); bom.addEventListener('change',load); batch.addEventListener('input',load);
    document.querySelectorAll('.add-row').forEach(b=>b.addEventListener('click',()=>{const sec=b.closest('.cost-section'), row=sec.querySelector('.cost-row'), n=row.cloneNode(true);n.querySelectorAll('input:not([type="hidden"])').forEach(i=>i.value='');n.querySelectorAll('select').forEach(s=>s.value='');sec.querySelector('.rows').appendChild(n)}));
    document.addEventListener('click',e=>{const btn=e.target.closest('.remove-row');if(!btn)return;const sec=btn.closest('.cost-section');if(sec.querySelectorAll('.cost-row').length>1)btn.closest('.cost-row').remove();recalc()});
    document.addEventListener('input',e=>{if(e.target.classList.contains('cost-input'))recalc()});
    filter(warehouse,bu.value); filter(bom,bu.value); load(); recalc();
});
</script>
@endpush
