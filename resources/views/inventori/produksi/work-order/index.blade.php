@extends('layouts.app')

@section('content')
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>
@endif
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button></div>
@endif
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Work Order / SPK</h4>
        <div class="text-secondary small">Perintah produksi berdasarkan BOM dan penetapan biaya produksi.</div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('produksi.work-order.hasil-report') }}" class="btn btn-outline-primary"><i class="bi bi-clipboard-data me-1"></i> Laporan Hasil</a>
        <a href="{{ route('produksi.work-order.export', request()->query()) }}" class="btn btn-outline-success">
            <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
        </a>
        <button type="button" class="btn btn-primary" id="btnCreateWorkOrder"><i class="bi bi-plus-lg me-1"></i> Buat SPK</button>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-2"><label class="form-label small">Dari</label><input type="date" name="date_from" class="form-control" value="{{ $dateFrom }}"></div>
            <div class="col-md-2"><label class="form-label small">Sampai</label><input type="date" name="date_to" class="form-control" value="{{ $dateTo }}"></div>
            <div class="col-md-2">
                <label class="form-label small">Status</label>
                <select name="status" class="form-select">
                    <option value="">Semua</option>
                    @foreach(['open'=>'Draft','in_progress'=>'Diproses','completed'=>'Selesai'] as $key => $label)
                        <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-auto"><button class="btn btn-primary">Tampilkan</button> <a href="{{ route('produksi.work-order') }}" class="btn btn-outline-secondary">Reset</a></div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div id="workOrderControls" class="d-flex justify-content-between align-items-center flex-nowrap gap-3 mb-3">
            <div class="d-flex align-items-center gap-2 flex-nowrap">
                <label for="workOrderPageLength" class="mb-0 text-nowrap">Tampilkan</label>
                <select id="workOrderPageLength" class="form-select form-select-sm" style="width:80px;">
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span class="text-nowrap">baris</span>
            </div>
            <div class="d-flex align-items-center gap-2 flex-nowrap ms-auto">
                <label for="workOrderSearch" class="mb-0 text-nowrap">Cari:</label>
                <input type="search" id="workOrderSearch" class="form-control form-control-sm" style="width:240px;" placeholder="Cari...">
            </div>
        </div>
        <div class="table-responsive">
            <table id="workOrderTable" class="table table-hover align-middle mb-0 w-100">
                <thead class="table-primary">
                    <tr>
                        <th>No. SPK</th><th>Tanggal</th><th>Produk</th><th>BOM</th><th>Gudang</th>
                        <th class="text-end">Target</th><th class="text-center">% Bahan</th><th class="text-end">Hasil Produksi</th><th class="text-center">Pekerja</th><th>Status</th><th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($rows as $row)
                    @php
                        $statusLabels=['open'=>'Draft','in_progress'=>'Diproses','completed'=>'Selesai'];
                        $statusClasses=['open'=>'primary','in_progress'=>'warning','completed'=>'success'];
                    @endphp
                    <tr id="wo-row-{{ $row->id }}">
                        <td class="fw-semibold">{{ $row->wo_no }}</td>
                        <td data-order="{{ $row->wo_date }}">{{ \Carbon\Carbon::parse($row->wo_date)->format('d/m/Y') }}</td>
                        <td>{{ $row->product_name }}</td>
                        <td><span class="badge text-bg-light border">{{ $row->bom_code }}</span></td>
                        <td>{{ $row->warehouse_name }}</td>
                        <td class="text-end">{{ \App\Helpers\FormatHelper::indo((float) $row->target_output_qty, 2) }}</td>
                        <td class="text-center">
                            <span class="badge text-bg-{{ $row->material_percent >= 100 ? 'success' : ($row->material_percent > 0 ? 'warning' : 'light border text-dark') }}">{{ \App\Helpers\FormatHelper::indo((float) $row->material_percent, 1) }}%</span>
                        </td>
                        <td class="text-end">{{ $row->good_output_qty > 0 ? \App\Helpers\FormatHelper::indo((float) $row->good_output_qty, 2) : '-' }}</td>
                        <td class="text-center">{{ $workerCounts[$row->id] ?? 0 }}</td>
                        <td><span class="badge text-bg-{{ $statusClasses[$row->status] ?? 'secondary' }}">{{ $statusLabels[$row->status] ?? $row->status }}</span></td>
                        <td class="text-end text-nowrap">
                            @if($row->status === 'open')
                                <form method="POST" action="{{ route('produksi.work-order.start', $row->id) }}" class="d-inline js-start-work-order" data-no="{{ $row->wo_no }}">
                                    @csrf
                                    <input type="hidden" name="started_at" value="{{ now()->toDateString() }}">
                                    <button type="submit" class="btn btn-sm btn-success" title="Mulai Produksi"><i class="bi bi-play-fill me-1"></i>Mulai</button>
                                </form>
                                <button type="button" class="btn btn-sm btn-outline-primary btn-edit-wo" title="Edit" data-id="{{ $row->id }}"><i class="bi bi-pencil"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-delete-wo" title="Hapus" data-id="{{ $row->id }}" data-no="{{ $row->wo_no }}"><i class="bi bi-trash"></i></button>
                                <a href="{{ route('produksi.work-order.print', $row->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Cetak SPK"><i class="bi bi-printer"></i></a>
                            @elseif($row->status === 'in_progress')
                                <button type="button" class="btn btn-sm btn-primary btn-input-production-result" data-id="{{ $row->id }}" data-no="{{ $row->wo_no }}" title="Input hasil produksi"><i class="bi bi-clipboard-check me-1"></i>Hasil</button>
                                <a href="{{ route('produksi.work-order.show',$row->id) }}" class="btn btn-sm btn-outline-dark" title="Detail"><i class="bi bi-eye"></i></a>
                            @else
                                <a href="{{ route('produksi.work-order.show',$row->id) }}" class="btn btn-sm btn-outline-dark" title="Detail"><i class="bi bi-eye"></i></a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal Create/Edit WO --}}
<div class="modal fade" id="workOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form method="POST" action="{{ route('produksi.work-order.store') }}" id="workOrderForm">
                @csrf
                <input type="hidden" name="_method" id="woMethod" value="">
                <input type="hidden" name="batch_qty" id="woBatchQty" value="1">
                <div class="modal-header">
                    <h5 class="modal-title" id="workOrderModalTitle">Buat Work Order / SPK</h5>
                    <button type="button" class="btn-close" aria-label="Tutup" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label">Tanggal SPK</label>
                            <input type="date" name="wo_date" id="woDate" class="form-control" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="col-md-3">

                            <label class="form-label">Business Unit</label>
                            <select name="business_unit_id" id="woModalBu" class="form-select" required>
                                <option value="">Pilih BU</option>
                                @foreach($businessUnits as $bu)
                                    <option value="{{ $bu->id }}">{{ $bu->code }} — {{ $bu->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Gudang Produksi</label>
                            <select name="warehouse_id" id="woModalWarehouse" class="form-select" required>
                                <option value="">Pilih gudang</option>
                                @foreach($warehouses as $warehouse)
                                    <option value="{{ $warehouse->id }}" data-bu="{{ $warehouse->business_unit_id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">BOM / Formula</label>
                            <select name="bom_id" id="woModalBom" class="form-select" required>
                                <option value="">Pilih BOM</option>
                                @foreach($boms as $bom)
                                    <option value="{{ $bom->id }}" data-bu="{{ $bom->business_unit_id }}">{{ $bom->code }} — {{ $bom->name }} ({{ $bom->product_name }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div id="woBomInfo" class="card border-primary mt-2">
                        <div class="card-body p-2">
                            <div class="row g-2 mb-2">
                                <div class="col-md-4"><div class="small text-secondary">Produk</div><div class="fw-semibold" id="woBomProduct">-</div></div>
                                <div class="col-md-4"><div class="small text-secondary">Kode BOM</div><div class="fw-semibold" id="woBomCode">-</div></div>
                                <div class="col-md-4"><div class="small text-secondary">Target Produksi</div><div class="fw-bold text-primary fs-5" id="woBomTarget">0</div></div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-primary"><tr><th>Material</th><th class="text-end">Qty</th><th>Satuan</th><th class="text-end">Harga</th><th class="text-end">Subtotal</th></tr></thead>
                                    <tbody id="woBomRows"><tr><td colspan="5" class="text-center text-secondary">Pilih BOM.</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    @foreach(['U'=>['Tenaga','Pilih pekerja dan isi estimasi biaya tenaga.'],'A'=>['Equipment','Nama alat dan estimasi biaya.'],'S'=>['Rent','Nama sewa dan estimasi biaya.'],'O'=>['Overhead','Nama overhead dan estimasi biaya.']] as $group => [$title,$help])
                    <div class="card border-0 shadow-sm mt-2 cost-section-modal" data-group="{{ $group }}">
                        <div class="card-header bg-primary bg-opacity-10 text-primary d-flex justify-content-between align-items-center">
                            <div><div class="fw-semibold">{{ $title }}</div><div class="small text-secondary">{{ $help }}</div></div>
                            <button type="button" class="btn btn-outline-primary btn-sm add-modal-cost"><i class="bi bi-plus-lg me-1"></i>Tambah</button>
                        </div>
                        <div class="card-body p-2 modal-cost-rows"></div>
                    </div>
                    @endforeach

                    <span id="woMaterialTotal" class="d-none">Rp 0</span>

                    <div class="card border-success mt-2">
                        <div class="card-body p-2 d-flex justify-content-between align-items-center">
                            <div><div class="small text-secondary">TOTAL ESTIMASI BIAYA WO/SPK</div><div class="small text-secondary">Material + Tenaga + Equipment + Rent + Overhead</div></div>
                            <div class="fw-bold text-success fs-4" id="woModalTotal">Rp 0</div>
                        </div>
                    </div>

                    <div class="mt-2">
                        <label class="form-label mb-1">Catatan SPK</label>
                        <textarea name="notes" id="woNotes" class="form-control" rows="3" placeholder="Instruksi atau catatan produksi..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i><span id="woSubmitText">Simpan Draft WO</span></button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal input hasil produksi langsung dari list SPK --}}
<div class="modal fade" id="productionResultModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" id="productionResultForm" action="{{ url('/produksi/work-order') }}">
                @csrf
                <input type="hidden" name="production_date" id="productionResultDate" value="{{ now()->toDateString() }}">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1">Input Hasil Produksi</h5>
                        <div class="small text-secondary" id="productionResultWorkOrder"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label for="productionResultDateVisible" class="form-label">Tanggal Produksi</label>
                            <input type="date" class="form-control" id="productionResultDateVisible" value="{{ now()->toDateString() }}" required>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr><th>Pekerja</th><th>Dasar Upah</th><th class="text-end">Tarif / Borongan</th><th style="min-width:110px" class="text-end">Hasil Bagus</th><th class="text-end">Biaya Bagus</th><th style="min-width:110px" class="text-end">Reject</th><th class="text-end">Biaya Reject</th></tr>
                            </thead>
                            <tbody id="productionResultWorkerRows">
                                <tr><td colspan="7" class="text-center text-secondary py-3">Pilih SPK.</td></tr>
                            </tbody>
                            <tfoot class="table-light fw-semibold">
                                <tr><td colspan="3" class="text-end">Total</td><td class="text-end" id="productionResultGoodTotal">0</td><td class="text-end" id="productionResultGoodCostTotal">Rp 0,00</td><td class="text-end" id="productionResultRejectTotal">0</td><td class="text-end" id="productionResultRejectCostTotal">Rp 0,00</td></tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="alert alert-info small mt-3 mb-0">Simpan saja belum mengubah stok atau membuat jurnal. Closing memproses seluruh hasil draft SPK sekaligus.</div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <div class="d-flex gap-2">
                        <button type="submit" name="action" value="save" class="btn btn-outline-primary"><i class="bi bi-save me-1"></i>Simpan Saja</button>
                        <button type="submit" name="action" value="close" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>Closing SPK</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal konfirmasi hapus --}}
<div class="modal fade" id="woDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content">
        <form method="POST" id="woDeleteForm">
            @csrf
            @method('DELETE')
            <div class="modal-header"><h5 class="modal-title">Hapus SPK</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">Hapus SPK <strong id="woDeleteNo"></strong>?</div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-danger">Hapus</button></div>
        </form>
    </div></div>
</div>

@endsection

@push('styles')
<style>
    #workOrderModal .modal-dialog {
        width: min(1140px, calc(100vw - 24px));
        max-width: 1140px;
        margin: 12px auto;
    }
    #workOrderModal .modal-content {
        max-height: calc(100vh - 24px);
        overflow: hidden;
    }
    #workOrderModal #workOrderForm {
        display: flex;
        flex-direction: column;
        min-height: 0;
        max-height: calc(100vh - 24px);
        overflow: hidden;
    }
    #workOrderModal .modal-header,
    #workOrderModal .modal-footer {
        flex: 0 0 auto;
        padding: .6rem .9rem;
        background: #fff;
    }
    #workOrderModal .modal-body {
        min-height: 0;
        overflow-y: auto !important;
        overflow-x: hidden;
        padding: .75rem;
        -webkit-overflow-scrolling: touch;
    }
    #workOrderModal .form-label { margin-bottom: .2rem; }
    #workOrderModal .cost-section-modal { margin-top: .4rem !important; }
    #workOrderModal .cost-section-modal .card-header {
        padding: .3rem .6rem;
        min-height: 0;
        gap: .5rem;
    }
    #workOrderModal .cost-section-modal .card-header .small { line-height: 1.2; }
    #workOrderModal .cost-section-modal .card-header .btn {
        padding: .2rem .45rem;
        line-height: 1.2;
        white-space: nowrap;
        flex: 0 0 auto;
    }
    #workOrderModal .cost-section-modal .card-body { padding: .45rem .6rem !important; }
    #workOrderModal .modal-cost-row { margin-bottom: .2rem !important; }
    #workOrderModal .modal-cost-row .form-select,
    #workOrderModal .modal-cost-row .form-control,
    #workOrderModal .modal-cost-row .btn { min-height: 34px; }
    #workOrderModal .modal-cost-row .worker-cost-preview { line-height: 1.2; margin-top: -.15rem; }
    #woMessagePopup {
        display:none;
        position:fixed !important;
        inset:0 !important;
        z-index:2000 !important;
        width:100vw !important;
        height:100vh !important;
        margin:0 !important;
        padding:1rem !important;
        background:rgba(0,0,0,.5);
        align-items:center;
        justify-content:center;
    }
    #woMessagePopup.show { display:flex !important; }
    #woMessagePopup .wo-message-box {
        width:min(400px,calc(100vw - 30px));
        max-height:calc(100vh - 30px);
        background:#fff;
        border-radius:.5rem;
        box-shadow:0 .5rem 1rem rgba(0,0,0,.25);
        overflow:hidden;
        flex:0 0 auto;
    }
    #woMessagePopup .wo-message-header,
    #woMessagePopup .wo-message-footer { padding:.75rem 1rem; }
    #woMessagePopup .wo-message-header {
        display:flex;
        align-items:center;
        justify-content:space-between;
        border-bottom:1px solid #dee2e6;
    }
    #woMessagePopup .wo-message-body { padding:1rem; }
    #woMessagePopup .wo-message-footer {
        display:flex;
        justify-content:flex-end;
        border-top:1px solid #dee2e6;
    }
    #workOrderControls { width:100%; }
    #workOrderControls > div { min-width:0; }
    #workOrderTable_wrapper .dataTables_filter,
    #workOrderTable_wrapper .dataTables_length { display:none !important; }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal=bootstrap.Modal.getOrCreateInstance(document.getElementById('workOrderModal'),{backdrop:'static',keyboard:false});
    const deleteModal=bootstrap.Modal.getOrCreateInstance(document.getElementById('woDeleteModal'));
    const form=document.getElementById('workOrderForm');
    const workers=@json($workers);
    const costsByWo=@json($woCosts->groupBy('production_work_order_id'));
    const rows=@json($rows->keyBy('id'));
    const money=v=>'Rp '+Number(v||0).toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2});
    const parseMoney=v=>{const r=String(v??'').trim().replace(/[^0-9,.-]/g,'');if(!r)return 0;return r.includes(',')?Number(r.replace(/\./g,'').replace(',','.'))||0:Number(r)||0};
    const fields={bu:document.getElementById('woModalBu'),warehouse:document.getElementById('woModalWarehouse'),bom:document.getElementById('woModalBom'),date:document.getElementById('woDate'),batch:document.getElementById('woBatchQty'),notes:document.getElementById('woNotes'),total:document.getElementById('woModalTotal'),material:document.getElementById('woMaterialTotal'),bomRows:document.getElementById('woBomRows')};

    const filterSelect=(select,bu)=>{[...select.options].forEach(o=>{if(o.value)o.hidden=!!bu&&o.dataset.bu!==String(bu)});if(select.selectedOptions[0]?.hidden)select.value=''};

    function costRow(group,data={}){
        const wrap=document.createElement('div');
        wrap.className='row g-2 mb-2 modal-cost-row';
        if(group==='U'){
            const payType=data.pay_type||'borongan';
            const rate=(data.unit_rate!==null&&data.unit_rate!==undefined)?Number(data.unit_rate):Number(data.amount||0);
            wrap.innerHTML='<div class="col-md-4"><select name="worker_id[]" class="form-select"><option value="">Pilih pekerja</option>'+workers.map(w=>'<option value="'+w.id+'">'+w.code+' — '+w.name+'</option>').join('')+'</select></div><div class="col-md-3"><select name="worker_pay_type[]" class="form-select worker-pay-type"><option value="borongan">Borongan</option><option value="satuan">Satuan</option></select></div><div class="col-md-3"><input type="text" name="worker_rate[]" class="form-control cost-input worker-rate text-end" inputmode="decimal" placeholder="Total upah"></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-modal-cost"><i class="bi bi-trash"></i></button></div><div class="col-12 small text-secondary worker-cost-preview"></div>';
            wrap.querySelector('select[name="worker_id[]"]').value=data.worker_id||'';
            wrap.querySelector('.worker-pay-type').value=payType;
            wrap.querySelector('.worker-rate').value=rate?rate.toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2}):'';
            wrap.querySelector('.worker-rate').placeholder=payType==='satuan'?'Harga per unit':'Total upah borongan';
        }else{
            wrap.innerHTML='<div class="col-md-7"><input type="text" name="cost_description[]" class="form-control" placeholder="Keterangan"></div><div class="col-md-3"><input type="text" name="cost_amount[]" class="form-control cost-input text-end" inputmode="decimal" placeholder="Estimasi biaya"></div><div class="col-md-2"><button type="button" class="btn btn-outline-danger w-100 remove-modal-cost"><i class="bi bi-trash"></i></button></div><input type="hidden" name="cost_group[]" value="'+group+'">';
            wrap.querySelector('input[name="cost_description[]"]').value=data.description||'';
            wrap.querySelector('input[name="cost_amount[]"]').value=data.amount?Number(data.amount).toLocaleString('id-ID',{minimumFractionDigits:2,maximumFractionDigits:2}):'';
        }
        return wrap;
    }

    function resetCosts(data={}){
        document.querySelectorAll('.cost-section-modal').forEach(sec=>{
            const group=sec.dataset.group,box=sec.querySelector('.modal-cost-rows');
            box.innerHTML='';
            const items=data[group]||[];
            if(items.length)items.forEach(x=>box.appendChild(costRow(group,x)));
            else box.appendChild(costRow(group));
        });
        recalc();
    }

    function recalc(){
        let total=parseMoney(fields.material.dataset.value||0);
        document.querySelectorAll('#workOrderForm .cost-section-modal').forEach(sec=>{
            if(sec.dataset.group==='U'){
                sec.querySelectorAll('.modal-cost-row').forEach(row=>{
                    const type=row.querySelector('.worker-pay-type')?.value||'borongan';
                    const rate=parseMoney(row.querySelector('.worker-rate')?.value||0);
                    const target=Number(fields.bomRows.dataset.targetQty||0);
                    const amount=type==='satuan'?rate*target:rate;
                    total+=amount;
                    const preview=row.querySelector('.worker-cost-preview');
                    if(preview)preview.textContent=type==='satuan'?'Estimasi: '+money(rate)+' × '+Number(target).toLocaleString('id-ID',{maximumFractionDigits:3})+' unit = '+money(amount):'Total upah: '+money(amount);
                });
            }else sec.querySelectorAll('.cost-input').forEach(i=>total+=parseMoney(i.value));
        });
        fields.total.textContent=money(total);
    }

    async function loadBom(){
        if(!fields.bom.value){
            document.getElementById('woBomProduct').textContent='-';
            document.getElementById('woBomCode').textContent='-';
            document.getElementById('woBomTarget').textContent='0';
            fields.bomRows.dataset.targetQty=0;
            fields.material.dataset.value=0;
            fields.material.textContent='Rp 0';
            fields.bomRows.innerHTML='<tr><td colspan="5" class="text-center text-secondary">Pilih BOM.</td></tr>';
            recalc();
            return;
        }
        const q=new URLSearchParams({warehouse_id:fields.warehouse.value,batch_qty:fields.batch.value||1});
        const r=await fetch('{{ url('/produksi/work-order/bom') }}/'+fields.bom.value+'/info?'+q,{headers:{Accept:'application/json'}});
        if(!r.ok)return;
        const d=await r.json();
        document.getElementById('woBomProduct').textContent=(d.bom.product_sku?d.bom.product_sku+' — ':'')+d.bom.product_name;
        document.getElementById('woBomCode').textContent=d.bom.code+' — '+d.bom.name;
        document.getElementById('woBomTarget').textContent=Number(d.bom.target_output_qty||0).toLocaleString('id-ID',{maximumFractionDigits:3})+' '+(d.bom.output_unit||'');
        fields.bomRows.dataset.targetQty=Number(d.bom.target_output_qty||0);
        fields.material.dataset.value=d.material_cost||0;
        fields.material.textContent=money(d.material_cost);
        fields.bomRows.innerHTML=(d.materials||[]).map(i=>'<tr><td>'+i.sku+' — '+i.name+'</td><td class="text-end">'+Number(i.base_qty||0).toLocaleString('id-ID',{maximumFractionDigits:3})+'</td><td>'+i.unit+'</td><td class="text-end">'+money(i.unit_cost)+'</td><td class="text-end">'+money(i.line_cost)+'</td></tr>').join('')||'<tr><td colspan="5" class="text-center text-secondary">BOM belum memiliki material.</td></tr>';
        recalc();
    }

    function openCreate(){
        form.action='{{ route('produksi.work-order.store') }}';
        document.getElementById('woMethod').value='';
        document.getElementById('workOrderModalTitle').textContent='Buat Work Order / SPK';
        document.getElementById('woSubmitText').textContent='Simpan Draft WO';
        fields.date.value='{{ now()->toDateString() }}';
        fields.batch.value='1';
                fields.bu.value='';
        fields.warehouse.value='';
        fields.bom.value='';
        fields.notes.value='';
        filterSelect(fields.warehouse,'');
        filterSelect(fields.bom,'');
        fields.material.dataset.value=0;
        resetCosts();
        loadBom();
        modal.show();
    }

    function openEdit(id){
        const row=rows[id];
        if(!row)return;
        form.action='{{ url('/produksi/work-order') }}/'+id;
        document.getElementById('woMethod').value='PUT';
        document.getElementById('workOrderModalTitle').textContent='Edit Work Order / SPK';
        document.getElementById('woSubmitText').textContent='Simpan Perubahan';
        fields.date.value=row.wo_date;
        fields.batch.value=row.batch_qty;
                fields.bu.value=row.business_unit_id;
        filterSelect(fields.warehouse,row.business_unit_id);
        filterSelect(fields.bom,row.business_unit_id);
        fields.warehouse.value=row.warehouse_id;
        fields.bom.value=row.bom_id;
        fields.notes.value=row.notes||'';
        const grouped={U:[],A:[],S:[],O:[]};
        (costsByWo[id]||[]).forEach(c=>{if(grouped[c.cost_group])grouped[c.cost_group].push(c)});
        resetCosts(grouped);
        loadBom();
        modal.show();
    }

    document.getElementById('btnCreateWorkOrder').addEventListener('click',openCreate);
    document.querySelectorAll('.btn-edit-wo').forEach(b=>b.addEventListener('click',()=>openEdit(b.dataset.id)));
    fields.bu.addEventListener('change',()=>{filterSelect(fields.warehouse,fields.bu.value);filterSelect(fields.bom,fields.bu.value);loadBom()});
    fields.warehouse.addEventListener('change',loadBom);
    fields.bom.addEventListener('change',loadBom);

    document.addEventListener('click',function(e){
        const add=e.target.closest('#workOrderModal .add-modal-cost');
        if(add){
            e.preventDefault();
            const sec=add.closest('.cost-section-modal');
            if(sec){
                const box=sec.querySelector('.modal-cost-rows');
                box.appendChild(costRow(sec.dataset.group));
                recalc();
            }
            return;
        }
        const rm=e.target.closest('#workOrderModal .remove-modal-cost');
        if(rm){
            e.preventDefault();
            const box=rm.closest('.modal-cost-rows');
            if(box && box.querySelectorAll('.modal-cost-row').length>1)rm.closest('.modal-cost-row').remove();
            recalc();
            return;
        }
        const del=e.target.closest('.btn-delete-wo');
        if(del){
            document.getElementById('woDeleteNo').textContent=del.dataset.no;
            document.getElementById('woDeleteForm').action='{{ url('/produksi/work-order') }}/'+del.dataset.id;
            deleteModal.show();
        }
    });

    document.addEventListener('input',e=>{if(e.target.classList.contains('cost-input'))recalc()});
    document.addEventListener('change',e=>{
        if(e.target.classList.contains('worker-pay-type')){
            const row=e.target.closest('.modal-cost-row');
            const input=row?.querySelector('.worker-rate');
            if(input)input.placeholder=e.target.value==='satuan'?'Harga per unit':'Total upah borongan';
            recalc();
        }
    });

    $(document).on('submit', '.js-start-work-order', function (e) {
        e.preventDefault();
        const startForm = this;

        Swal.fire({
            icon: 'question',
            title: 'Konfirmasi',
            text: 'Mulai proses produksi untuk SPK ' + startForm.dataset.no + '?',
            showCancelButton: true,
            confirmButtonText: 'Ya, mulai',
            cancelButtonText: 'Batal',
            reverseButtons: true
        }).then(result => {
            if (!result.isConfirmed) return;

            $.ajax({
                url: startForm.action,
                type: 'POST',
                data: $(startForm).serialize(),
                headers: { 'Accept': 'application/json' },
                success: function (res) {
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil',
                            text: res.message,
                            timer: 1200,
                            showConfirmButton: false
                        }).then(() => window.location.reload());
                    }
                },
                error: function (xhr) {
                    const response = xhr.responseJSON || {};
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: response.message || 'SPK gagal dimulai.'
                    });
                }
            });
        });
    });

    $('#woDeleteForm').on('submit', function (e) {
        e.preventDefault();

        const deleteForm = this;

        $.ajax({
            url: deleteForm.action,
            type: 'POST',
            data: $(deleteForm).serialize(),
            headers: { 'Accept': 'application/json' },
            success: function (res) {
                if (res.success) {
                    deleteModal.hide();
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: res.message,
                        timer: 1200,
                        showConfirmButton: false
                    }).then(() => window.location.reload());
                }
            },
            error: function (xhr) {
                const response = xhr.responseJSON || {};
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: response.message || 'Gagal menghapus SPK.'
                });
            }
        });
    });

    function upsertWorkOrderRow(row) {
        const statusLabels = {open: 'Draft', in_progress: 'Diproses', completed: 'Selesai'};
        const statusClasses = {open: 'primary', in_progress: 'warning', completed: 'success'};
        const statusLabel = statusLabels[row.status] || row.status;
        const statusClass = statusClasses[row.status] || 'secondary';

        const cells = [
            '<span class="fw-semibold">'+row.wo_no+'</span>',
            '<span data-order="'+row.wo_date+'">'+row.wo_date_display+'</span>',
            row.product_name,
            '<span class="badge text-bg-light border">'+row.bom_code+'</span>',
            row.warehouse_name,
            '<span class="d-block text-end">'+Number(row.target_output_qty || 0).toLocaleString('id-ID',{minimumFractionDigits:0,maximumFractionDigits:2})+'</span>',
            '<span class="badge text-bg-'+(Number(row.material_percent||0)>=100?'success':(Number(row.material_percent||0)>0?'warning':'light border text-dark'))+'">'+Number(row.material_percent||0).toLocaleString('id-ID',{minimumFractionDigits:1,maximumFractionDigits:1})+'%</span>',
            '<span class="d-block text-end">'+(Number(row.good_output_qty||0)>0?Number(row.good_output_qty).toLocaleString('id-ID',{minimumFractionDigits:0,maximumFractionDigits:2}):'-')+'</span>',
            '<span class="d-block text-center">'+row.worker_count+'</span>',
            '<span class="badge text-bg-'+statusClass+'">'+statusLabel+'</span>',
            row.status === 'open'
                ? '<div class="text-end text-nowrap"><button type="button" class="btn btn-sm btn-outline-primary btn-edit-wo" title="Edit" data-id="'+row.id+'"><i class="bi bi-pencil"></i></button> <button type="button" class="btn btn-sm btn-outline-danger btn-delete-wo" title="Hapus" data-id="'+row.id+'" data-no="'+row.wo_no+'"><i class="bi bi-trash"></i></button> <a href="'+row.print_url+'" target="_blank" class="btn btn-sm btn-outline-secondary" title="Cetak SPK"><i class="bi bi-printer"></i></a></div>'
                : '<div class="text-end text-nowrap"><a href="'+row.show_url+'" class="btn btn-sm btn-outline-dark" title="View"><i class="bi bi-eye"></i></a></div>'
        ];

        const existing = document.getElementById('wo-row-'+row.id);
        if (existing) {
            table.row(existing).data(cells).draw(false);
        } else {
            const tr = document.createElement('tr');
            tr.id = 'wo-row-'+row.id;
            table.row.add(tr);
            table.row(tr).data(cells).draw(false);
        }
    }

    $('#workOrderForm').on('submit', function (e) {
        e.preventDefault();

        const formElement = this;
        const isEdit = document.getElementById('woMethod').value === 'PUT';
        const url = formElement.action;
        const type = isEdit ? 'PUT' : 'POST';

        $.ajax({
            url: url,
            type: type,
            data: $(formElement).serialize(),
            headers: { 'Accept': 'application/json' },
            success: function (res) {
                if (res.success) {
                    modal.hide();
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil',
                        text: res.message,
                        timer: 1200,
                        showConfirmButton: false
                    }).then(() => window.location.reload());
                }
            },
            error: function (xhr) {
                const response = xhr.responseJSON || {};
                let message = response.message || 'Terjadi kesalahan sistem';

                if (response.errors) {
                    message = Object.values(response.errors).flat().join('<br>');
                }

                Swal.fire({
                    icon: 'error',
                    title: 'Gagal!',
                    html: message
                });
            }
        });
    });

    const table=new DataTable('#workOrderTable',{
        dom:'t<"d-flex justify-content-between align-items-center px-3 py-3"i p>',
        pageLength:15,
        lengthMenu:[[15,25,50,100],[15,25,50,100]],
        autoWidth:false,
        order:[[1,'desc']],
        language:{info:'Menampilkan _START_–_END_ dari _TOTAL_ SPK',infoEmpty:'Tidak ada SPK',zeroRecords:'Data tidak ditemukan',paginate:{previous:'‹',next:'›'}},
        columnDefs:[{targets:[5,6,7,9,10],orderable:false}]
    });
    document.getElementById('workOrderPageLength').addEventListener('change',function(){table.page.len(this.value).draw()});
    document.getElementById('workOrderSearch').addEventListener('input',function(){table.search(this.value).draw()});
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const resultModalEl = document.getElementById('productionResultModal');
    if (!resultModalEl) return;
    const resultModal = bootstrap.Modal.getOrCreateInstance(resultModalEl);
    const resultForm = document.getElementById('productionResultForm');
    const workerRows = document.getElementById('productionResultWorkerRows');
    const woWorkers = @json($woWorkers->groupBy('production_work_order_id'));
    const costsByWo = @json($woCosts->groupBy('production_work_order_id'));
    const resultDraftLines = @json($resultDraftLines->groupBy('production_work_order_id'));
    const money = value => 'Rp ' + Number(value || 0).toLocaleString('id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const number = value => Number(value || 0).toLocaleString('id-ID', {maximumFractionDigits: 3});
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
    let activeSavedDrafts = [];

    function recalculateResult() {
        let goodTotal = 0, rejectTotal = 0, goodCostTotal = 0, rejectCostTotal = 0;
        workerRows.querySelectorAll('tr[data-worker-id]').forEach(row => {
            const good = Math.max(0, Number(row.querySelector('.result-good')?.value || 0));
            const reject = Math.max(0, Number(row.querySelector('.result-reject')?.value || 0));
            const rate = Number(row.dataset.rate || 0);
            const payType = row.dataset.payType;
            const goodCost = payType === 'satuan' ? good * rate : 0;
            const rejectCost = payType === 'satuan' ? reject * rate : 0;
            row.querySelector('.result-good-cost').textContent = payType === 'satuan' ? number(good) + ' × ' + money(rate) + ' = ' + money(goodCost) : 'Borongan: ' + money(rate);
            row.querySelector('.result-reject-cost').textContent = payType === 'satuan' ? number(reject) + ' × ' + money(rate) + ' = ' + money(rejectCost) : '-';
            goodTotal += good; rejectTotal += reject; goodCostTotal += goodCost; rejectCostTotal += rejectCost;
        });
        document.getElementById('productionResultGoodTotal').textContent = number(goodTotal);
        document.getElementById('productionResultRejectTotal').textContent = number(rejectTotal);
        document.getElementById('productionResultGoodCostTotal').textContent = money(goodCostTotal);
        document.getElementById('productionResultRejectCostTotal').textContent = money(rejectCostTotal);
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('.btn-input-production-result');
        if (!button) return;
        const id = button.dataset.id;
        const workers = woWorkers[id] || [];
        const costs = (costsByWo[id] || []).filter(cost => cost.cost_group === 'U');
        const costMap = new Map(costs.map(cost => [String(cost.worker_id), cost]));
        document.getElementById('productionResultWorkOrder').textContent = 'SPK ' + button.dataset.no;
        resultForm.action = '{{ url('/produksi/work-order') }}/' + id + '/hasil-produksi';
        const savedDrafts = resultDraftLines[id] || [];
        activeSavedDrafts = savedDrafts;
        const draftDate = savedDrafts.length ? savedDrafts[0].production_date : '{{ now()->toDateString() }}';
        document.getElementById('productionResultDateVisible').value = draftDate;
        document.getElementById('productionResultDate').value = draftDate;
        const savedForDate = new Map(savedDrafts.filter(line => line.production_date === draftDate).map(line => [String(line.worker_id), line]));
        workerRows.innerHTML = '';
        if (!workers.length) {
            workerRows.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-3">Setup pekerja SPK tidak ditemukan.</td></tr>';
        } else {
            workers.forEach((worker, index) => {
                const cost = costMap.get(String(worker.worker_id)) || {};
                const payType = String(cost.pay_type || 'borongan').toLowerCase() === 'satuan' ? 'satuan' : 'borongan';
                const rate = Number(payType === 'satuan' ? (cost.unit_rate || 0) : (cost.amount || 0));
                const saved = savedForDate.get(String(worker.worker_id)) || {};
                const row = document.createElement('tr');
                row.dataset.workerId = worker.worker_id;
                row.dataset.payType = payType;
                row.dataset.rate = rate;
                row.innerHTML = '<td><span class="fw-semibold">'+escapeHtml(worker.worker_name)+'</span><input type="hidden" name="worker_id[]" value="'+Number(worker.worker_id)+'"></td>'
                    + '<td>'+ (payType === 'satuan' ? 'Satuan' : 'Borongan') + '</td>'
                    + '<td class="text-end">'+money(rate)+'</td>'
                    + '<td><input type="number" name="good_qty[]" class="form-control form-control-sm text-end result-good" min="0" step="0.001" value="'+Number(saved.good_qty || 0)+'" required></td>'
                    + '<td class="text-end result-good-cost">Rp 0,00</td>'
                    + '<td><input type="number" name="reject_by_worker[]" class="form-control form-control-sm text-end result-reject" min="0" step="0.001" value="'+Number(saved.reject_qty || 0)+'" required></td>'
                    + '<td class="text-end result-reject-cost">Rp 0,00</td>';
                workerRows.appendChild(row);
            });
        }
        resultModal.show();
        recalculateResult();
    });

    document.getElementById('productionResultDateVisible').addEventListener('change', function () {
        document.getElementById('productionResultDate').value = this.value;
        const savedForDate = new Map(activeSavedDrafts.filter(line => line.production_date === this.value).map(line => [String(line.worker_id), line]));
        workerRows.querySelectorAll('tr[data-worker-id]').forEach(row => {
            const saved = savedForDate.get(String(row.dataset.workerId)) || {};
            row.querySelector('.result-good').value = Number(saved.good_qty || 0);
            row.querySelector('.result-reject').value = Number(saved.reject_qty || 0);
        });
        recalculateResult();
    });
    workerRows.addEventListener('input', recalculateResult);
    resultForm.addEventListener('submit', function (event) {
        const submitter = event.submitter;
        if (submitter && submitter.value === 'close' && !confirm('Closing SPK akan memposting seluruh hasil draft, stok, HPP, dan jurnal. Lanjutkan?')) {
            event.preventDefault();
            return;
        }
        document.getElementById('productionResultDate').value = document.getElementById('productionResultDateVisible').value;
    });
});
</script>
@endpush