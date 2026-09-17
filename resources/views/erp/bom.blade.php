@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1">Formula / BOM</h3>
        <div class="text-secondary">1 produk jadi dapat memiliki banyak bahan baku</div>
    </div>
    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Dashboard</a>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">Buat Formula / BOM</div>
    <div class="card-body">
        <form method="POST" action="{{ route('erp.bom.store') }}" id="bomForm">
            @csrf
            <div class="row g-3 mb-3">
                <div class="col-lg-3">
                    <label class="form-label">Produk Jadi</label>
                    <select name="product_id" class="form-select" required>
                        <option value="">Pilih produk jadi</option>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}">{{ $p->sku }} — {{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <label class="form-label">Kode BOM</label>
                    <input name="code" class="form-control" required>
                </div>
                <div class="col-lg-3">
                    <label class="form-label">Nama Formula</label>
                    <input name="name" class="form-control" required>
                </div>
                <div class="col-lg-2">
                    <label class="form-label">Output Qty</label>
                    <input name="output_qty" type="number" min="0.001" step="0.001" class="form-control" required>
                </div>
            </div>

            <div class="border rounded p-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="fw-semibold">Bahan Baku</div>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="addMaterial">+ Tambah Bahan</button>
                </div>

                <div id="materials">
                    <div class="row g-2 material-row mb-2">
                        <div class="col-md-7">
                            <select name="material_product_id[]" class="form-select" required>
                                <option value="">Pilih bahan baku</option>
                                @foreach($products as $p)
                                    <option value="{{ $p->id }}">{{ $p->sku }} — {{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input name="material_qty[]" type="number" min="0.001" step="0.001" class="form-control" placeholder="Qty" required>
                        </div>
                        <div class="col-md-2 d-grid">
                            <button type="button" class="btn btn-outline-danger remove-material">Hapus</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <button class="btn btn-primary">Simpan BOM</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-semibold">Daftar Formula / BOM</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Kode</th>
                    <th>Nama Formula</th>
                    <th>Produk Jadi</th>
                    <th class="text-end">Output</th>
                </tr>
            </thead>
            <tbody>
                @forelse($boms as $bom)
                    <tr>
                        <td>{{ $bom->id }}</td>
                        <td>{{ $bom->code }}</td>
                        <td>{{ $bom->name }}</td>
                        <td>{{ optional($products->firstWhere('id', $bom->product_id))->name ?? '-' }}</td>
                        <td class="text-end">{{ $bom->output_qty }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-secondary py-4">Belum ada formula.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $boms->links() }}</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('materials');
    const addButton = document.getElementById('addMaterial');

    addButton.addEventListener('click', function () {
        const first = container.querySelector('.material-row');
        const row = first.cloneNode(true);
        row.querySelector('select').selectedIndex = 0;
        row.querySelector('input').value = '';
        container.appendChild(row);
    });

    container.addEventListener('click', function (event) {
        if (!event.target.classList.contains('remove-material')) return;
        const rows = container.querySelectorAll('.material-row');
        if (rows.length === 1) {
            rows[0].querySelector('select').selectedIndex = 0;
            rows[0].querySelector('input').value = '';
            return;
        }
        event.target.closest('.material-row').remove();
    });
});
</script>
@endsection
