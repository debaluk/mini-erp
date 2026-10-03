@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Work Order / SPK</h4>
        <div class="text-secondary small">{{ $wo->wo_no }}</div>
    </div>
    <div class="d-flex gap-2">
        @if($wo->status === 'in_progress')
        <a href="{{ route('produksi.pemakaian-bahan.create', $wo->id) }}" class="btn btn-primary">
            <i class="bi bi-box-arrow-right me-1"></i> Pemakaian Bahan
        </a>
        @endif
        <a href="{{ route('produksi.work-order.print', $wo->id) }}" target="_blank" class="btn btn-outline-dark">
            <i class="bi bi-printer me-1"></i> Print SPK
        </a>
        <a href="{{ route('produksi.work-order') }}" class="btn btn-outline-secondary">Kembali</a>
    </div>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabInformasi" type="button">Informasi</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabHasilProduksi" type="button">Hasil Produksi</button></li>
</ul>
<div class="tab-content">
<div class="tab-pane fade show active" id="tabInformasi">
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-primary bg-opacity-10 text-primary fw-semibold">Informasi SPK</div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3"><div class="text-secondary small">Nomor SPK</div><div class="fw-semibold">{{ $wo->wo_no }}</div></div>
            <div class="col-md-3"><div class="text-secondary small">Tanggal SPK</div><div>{{ \Carbon\Carbon::parse($wo->wo_date)->format('d/m/Y') }}</div></div>
            <div class="col-md-3"><div class="text-secondary small">Tanggal Mulai Kerja</div><div>{{ $wo->started_at ? \Carbon\Carbon::parse($wo->started_at)->format('d/m/Y H:i') : '-' }}</div></div>
            <div class="col-md-3"><div class="text-secondary small">Status</div><div><span class="badge text-bg-{{ $wo->status === 'in_progress' ? 'warning' : ($wo->status === 'completed' ? 'success' : 'primary') }}">{{ $wo->status === 'in_progress' ? 'On Progress' : ($wo->status === 'completed' ? 'Selesai' : 'Open') }}</span></div></div>
            <div class="col-md-4"><div class="text-secondary small">Produk</div><div class="fw-semibold">{{ $wo->product_name }}</div></div>
            <div class="col-md-4"><div class="text-secondary small">BOM</div><div>{{ $wo->bom_code }} — {{ $wo->bom_name }}</div></div>
            <div class="col-md-4"><div class="text-secondary small">Gudang</div><div>{{ $wo->warehouse_name }}</div></div>
            <div class="col-md-4"><div class="text-secondary small">Target</div><div>{{ \App\Helpers\FormatHelper::indo((float) $wo->target_output_qty, 2) }}</div></div>
            <div class="col-md-8"><div class="text-secondary small">Pekerja</div><div>{{ $workers->pluck('name')->join(', ') ?: '-' }}</div></div>
        </div>
    </div>
</div>

</div>
</div>
<div class="tab-pane fade" id="tabHasilProduksi">
    @include('inventori.produksi.work-order.hasil-produksi')
</div>
</div>


@if($wo->status === 'in_progress')
<div class="card border-0 shadow-sm">
    <div class="card-header bg-warning bg-opacity-10 fw-semibold">Rencana Bahan Berdasarkan BOM</div>
    <div class="card-body">
        <div class="alert alert-info mb-3">
            Rencana bahan ditampilkan untuk proses pengeluaran. Belum ada perubahan stok maupun jurnal.
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>SKU</th>
                        <th>Material</th>
                        <th class="text-end">Qty BOM</th>
                        <th>Satuan</th>
                        <th class="text-end">Qty Dasar</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($materials as $material)
                    <tr>
                        <td>{{ $material['sku'] }}</td>
                        <td>{{ $material['name'] }}</td>
                        <td class="text-end">{{ \App\Helpers\FormatHelper::indo($material['qty'], 2) }}</td>
                        <td>{{ $material['unit'] }}</td>
                        <td class="text-end">{{ \App\Helpers\FormatHelper::indo($material['base_qty'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">BOM belum memiliki bahan.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endif
@endsection