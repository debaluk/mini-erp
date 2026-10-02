@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Produksi</h4>
        <div class="text-secondary small">Produksi memakai bahan aktual dan HPP aktual BUASO.</div>
    </div>
    <a href="{{ route('produksi.hpp') }}" class="btn btn-outline-primary btn-sm">Lihat HPP</a>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if($errors->any())
<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">Posting Produksi</div>
    <div class="card-body">
        <form method="POST" action="{{ route('produksi.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">BOM / Formula</label>
                    <select name="bom_id" id="bom_id" class="form-select" required>
                        <option value="">Pilih BOM</option>
                        @foreach($boms as $bom)
                            <option value="{{ $bom->id }}" data-output="{{ $bom->output_qty }}">{{ $bom->code }} — {{ $bom->name }} ({{ $bom->product_name }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Gudang Produksi</label>
                    <select name="warehouse_id" class="form-select" required>
                        <option value="">Pilih Gudang</option>
                        @foreach($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}">{{ $warehouse->code }} — {{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Qty Batch</label>
                    <input type="number" name="qty" id="batch_qty" class="form-control" min="0.001" step="0.001" value="1" required>
                    <div class="form-text">Output teoritis: <span id="theoretical_output">0</span> base unit.</div>
                </div>
            </div>

            <hr class="my-4">

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Hasil Bagus (Good Output)</label>
                    <input type="number" name="good_output_qty" id="good_output_qty" class="form-control" min="0.001" step="0.001">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Reject / Scrap</label>
                    <input type="number" name="reject_qty" id="reject_qty" class="form-control" min="0" step="0.001" value="0">
                </div>
                <div class="col-md-4">
                    <div class="alert alert-light border mb-0 small">
                        HPP final = seluruh biaya aktual ÷ hasil bagus.
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="fw-semibold">BUASO — Biaya Aktual Produksi <span class="text-secondary small fw-normal">(Bahan/B dihitung otomatis dari pemakaian material)</span></div>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="add-cost">+ Tambah Biaya</button>
            </div>
            <div id="cost-rows">
                <div class="row g-2 mb-2 cost-row">
                    <div class="col-md-2">
                        <select name="cost_group[]" class="form-select">
                            @foreach($costGroups as $key => $label)
                                @if($key !== 'B')<option value="{{ $key }}">{{ $key }} — {{ $label }}</option>@endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-7"><input type="text" name="cost_description[]" class="form-control" placeholder="Keterangan biaya aktual"></div>
                    <div class="col-md-2"><input type="number" name="cost_amount[]" class="form-control" min="0" step="0.01" placeholder="Nominal"></div>
                    <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-cost">×</button></div>
                </div>
            </div>

            <div class="text-end mt-3">
                <button class="btn btn-primary">Posting Produksi</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-semibold">Riwayat Produksi</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>No. Produksi</th><th>Tanggal</th><th>Produk</th><th>BOM</th>
                    <th class="text-end">Batch</th><th class="text-end">Bagus</th><th class="text-end">Reject</th>
                    <th class="text-end">Bahan</th><th class="text-end">BUASO</th><th class="text-end">Total HPP</th><th class="text-end">HPP/Unit</th>
                </tr>
            </thead>
            <tbody>
            @forelse($rows as $row)
                @php $total = (float)$row->total_cost; $good = (float)$row->good_output_qty; @endphp
                <tr>
                    <td>{{ $row->production_no }}</td>
                    <td>{{ CarbonCarbon::parse($row->production_date)->format('d/m/Y H:i') }}</td>
                    <td>{{ $row->product_name }}</td>
                    <td>{{ $row->bom_code }}</td>
                    <td class="text-end">{{ number_format($row->qty,3,',','.') }}</td>
                    <td class="text-end">{{ number_format($good,3,',','.') }}</td>
                    <td class="text-end">{{ number_format($row->reject_qty,3,',','.') }}</td>
                    <td class="text-end">Rp {{ number_format($row->material_cost,0,',','.') }}</td>
                    <td class="text-end">Rp {{ number_format($row->additional_cost,0,',','.') }}</td>
                    <td class="text-end fw-semibold">Rp {{ number_format($total,0,',','.') }}</td>
                    <td class="text-end fw-semibold">Rp {{ number_format($good > 0 ? $total/$good : 0,2,',','.') }}</td>
                </tr>
            @empty
                <tr><td colspan="11" class="text-center text-secondary py-4">Belum ada produksi.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($rows->hasPages())
        <div class="card-footer bg-white">{{ $rows->links() }}</div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const bom = document.getElementById('bom_id');
    const qty = document.getElementById('batch_qty');
    const theoretical = document.getElementById('theoretical_output');
    const good = document.getElementById('good_output_qty');
    const reject = document.getElementById('reject_qty');
    const updateOutput = () => {
        const selected = bom.options[bom.selectedIndex];
        const output = parseFloat(selected?.dataset.output || 0);
        const total = output * parseFloat(qty.value || 0);
        theoretical.textContent = total.toLocaleString('id-ID', {maximumFractionDigits: 3});
        if (!good.dataset.touched) good.value = total > 0 ? total : '';
    };
    bom.addEventListener('change', updateOutput);
    qty.addEventListener('input', updateOutput);
    good.addEventListener('input', () => good.dataset.touched = '1');
    updateOutput();

    document.getElementById('add-cost').addEventListener('click', function () {
        const first = document.querySelector('.cost-row');
        const row = first.cloneNode(true);
        row.querySelectorAll('input').forEach(input => input.value = '');
        row.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
        document.getElementById('cost-rows').appendChild(row);
    });

    document.getElementById('cost-rows').addEventListener('click', function (event) {
        if (!event.target.classList.contains('remove-cost')) return;
        const rows = document.querySelectorAll('.cost-row');
        if (rows.length > 1) event.target.closest('.cost-row').remove();
    });
});
</script>
@endsection
