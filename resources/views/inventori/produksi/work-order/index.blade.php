@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Work Order / SPK</h4>
        <div class="text-secondary small">Perintah produksi dan penetapan tenaga kerja.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('produksi.work-order.export', request()->query()) }}" class="btn btn-outline-success">
            <i class="bi bi-file-earmark-excel"></i> Export Excel
        </a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createWoModal">
            <i class="bi bi-plus-lg"></i> Buat SPK
        </button>
    </div>
</div>

@if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if($errors->any())
<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-2"><label class="form-label small">Dari</label><input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}"></div>
            <div class="col-md-2"><label class="form-label small">Sampai</label><input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}"></div>
            <div class="col-md-2">
                <label class="form-label small">Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua</option>
                    @foreach(['draft'=>'Draft','open'=>'Open','in_progress'=>'Proses','completed'=>'Selesai','cancelled'=>'Batal'] as $key => $label)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto"><button class="btn btn-outline-primary">Terapkan</button> <a href="{{ route('produksi.work-order') }}" class="btn btn-outline-secondary">Reset</a></div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr>
                <th>No. SPK</th><th>Tanggal</th><th>Produk</th><th>BOM</th><th>Gudang</th>
                <th class="text-end">Batch</th><th class="text-end">Target</th><th class="text-center">Pekerja</th><th>Status</th><th class="text-end">Aksi</th>
            </tr></thead>
            <tbody>
            @forelse($rows as $row)
                @php
                    $statusLabels=['draft'=>'Draft','open'=>'Open','in_progress'=>'Proses','completed'=>'Selesai','cancelled'=>'Batal'];
                    $statusClasses=['draft'=>'secondary','open'=>'primary','in_progress'=>'warning','completed'=>'success','cancelled'=>'danger'];
                @endphp
                <tr>
                    <td class="fw-semibold">{{ $row->wo_no }}</td>
                    <td>{{ \Carbon\Carbon::parse($row->wo_date)->format('d/m/Y') }}</td>
                    <td>{{ $row->product_name }}</td><td>{{ $row->bom_code }}</td><td>{{ $row->warehouse_name }}</td>
                    <td class="text-end">{{ number_format($row->batch_qty,3,',','.') }}</td>
                    <td class="text-end">{{ number_format($row->target_output_qty,3,',','.') }}</td>
                    <td class="text-center">{{ $workerCounts[$row->id] ?? 0 }}</td>
                    <td><span class="badge text-bg-{{ $statusClasses[$row->status] ?? 'secondary' }}">{{ $statusLabels[$row->status] ?? $row->status }}</span></td>
                    <td class="text-end"><a href="{{ route('produksi.work-order.print',$row->id) }}" target="_blank" class="btn btn-sm btn-outline-dark"><i class="bi bi-printer"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-center text-secondary py-5">Belum ada SPK.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($rows->hasPages()) <div class="card-footer bg-white">{{ $rows->links() }}</div> @endif
</div>

<div class="modal fade" id="createWoModal" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
<form method="POST" action="{{ route('produksi.work-order.store') }}">
@csrf
<div class="modal-header">
    <div><h5 class="modal-title">Buat Work Order / SPK</h5><div class="small text-secondary">SPK adalah perintah produksi. Biaya aktual dicatat saat penyelesaian produksi.</div></div>
    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <div class="row g-3">
        <div class="col-md-3"><label class="form-label">Tanggal SPK</label><input type="date" name="wo_date" class="form-control" value="{{ old('wo_date',date('Y-m-d')) }}" required></div>
        <div class="col-md-4"><label class="form-label">Business Unit</label>
            <select name="business_unit_id" id="wo_bu" class="form-select" required><option value="">Pilih BU</option>
                @foreach($businessUnits as $bu)<option value="{{ $bu->id }}" @selected(old('business_unit_id') == $bu->id)>{{ $bu->code }} — {{ $bu->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-5"><label class="form-label">Gudang Produksi</label>
            <select name="warehouse_id" id="wo_warehouse" class="form-select" required><option value="">Pilih gudang</option>
                @foreach($warehouses as $warehouse)<option value="{{ $warehouse->id }}" data-bu="{{ $warehouse->business_unit_id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-7"><label class="form-label">BOM / Formula</label>
            <select name="bom_id" id="wo_bom" class="form-select" required><option value="">Pilih BOM</option>
                @foreach($boms as $bom)<option value="{{ $bom->id }}" data-bu="{{ $bom->business_unit_id }}" data-output="{{ $bom->output_qty }}">{{ $bom->code }} — {{ $bom->name }} ({{ $bom->product_name }})</option>@endforeach
            </select>
        </div>
        <div class="col-md-5"><label class="form-label">Jumlah Batch</label><input type="number" name="batch_qty" id="wo_batch" class="form-control" min="0.001" step="0.001" value="1" required><div class="form-text">Target output: <strong id="wo_target">0</strong> base unit.</div></div>
    </div>

    <hr class="my-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div><h6 class="mb-0">Tenaga Kerja / Pekerja</h6><div class="small text-secondary">Bisa lebih dari satu. Pengakuan Upah (U) dilakukan saat produksi selesai.</div></div>
        <button type="button" class="btn btn-outline-primary btn-sm" id="add-worker">+ Tambah Pekerja</button>
    </div>
    <div id="worker-rows">
        <div class="row g-2 mb-2 worker-row">
            <div class="col-md-5"><select name="worker_id[]" class="form-select worker-select" required><option value="">Pilih pekerja</option>
                @foreach($workers as $worker)<option value="{{ $worker->id }}">{{ $worker->code }} — {{ $worker->name }}</option>@endforeach
            </select></div>
            <div class="col-md-5"><input type="text" name="worker_role[]" class="form-control" placeholder="Peran / pekerjaan (opsional)"></div>
            <div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-worker">Hapus</button></div>
        </div>
    </div>
    <div class="mt-3"><label class="form-label">Catatan SPK</label><textarea name="notes" class="form-control" rows="3" placeholder="Instruksi atau catatan produksi...">{{ old('notes') }}</textarea></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary">Simpan & Terbitkan SPK</button></div>
</form>
</div></div></div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',function(){
    const bu=document.getElementById('wo_bu'), warehouse=document.getElementById('wo_warehouse'), bom=document.getElementById('wo_bom'), batch=document.getElementById('wo_batch'), target=document.getElementById('wo_target'), workerRows=document.getElementById('worker-rows');
    function filterByBu(select,id){[...select.options].forEach(o=>{if(o.value)o.hidden=!!id&&o.dataset.bu!==id;});if(select.selectedOptions[0]?.hidden)select.value='';}
    function recalc(){const out=parseFloat(bom.selectedOptions[0]?.dataset.output||0);target.textContent=(out*parseFloat(batch.value||0)).toLocaleString('id-ID',{maximumFractionDigits:3});}
    bu.addEventListener('change',()=>{filterByBu(warehouse,bu.value);filterByBu(bom,bu.value);recalc();});
    bom.addEventListener('change',recalc); batch.addEventListener('input',recalc);
    document.getElementById('add-worker').addEventListener('click',()=>{const row=workerRows.querySelector('.worker-row').cloneNode(true);row.querySelector('.worker-select').value='';row.querySelector('input').value='';workerRows.appendChild(row);});
    workerRows.addEventListener('click',e=>{if(e.target.classList.contains('remove-worker')&&workerRows.querySelectorAll('.worker-row').length>1)e.target.closest('.worker-row').remove();});
    recalc();
    @if($errors->any()) new bootstrap.Modal(document.getElementById('createWoModal')).show(); @endif
});
</script>
@endpush
