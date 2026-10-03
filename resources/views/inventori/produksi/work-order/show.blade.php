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
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-primary bg-opacity-10 text-primary border-bottom py-3"><i class="bi bi-box-arrow-in-down me-1"></i> Hasil Produksi</div>
        <div class="card-body">
            <div class="row g-3 mb-4">
                <div class="col-md-3"><div class="text-secondary small">Target Produksi</div><div class="fw-semibold fs-5">{{ \App\Helpers\FormatHelper::indo((float) $wo->target_output_qty, 2) }} Biji</div></div>
                <div class="col-md-3"><div class="text-secondary small">Hasil Bagus</div><div class="fw-semibold fs-5 text-success">-</div></div>
                <div class="col-md-3"><div class="text-secondary small">Reject / Scrap</div><div class="fw-semibold fs-5 text-danger">-</div></div>
                <div class="col-md-3"><div class="text-secondary small">Status Hasil</div><span class="badge text-bg-secondary">Belum Diinput</span></div>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div><div class="fw-semibold">Input Hasil Aktual</div><div class="text-secondary small">Hasil dicatat dari SPK ini.</div></div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#hasilProduksiModal"><i class="bi bi-plus-lg me-1"></i> Input Hasil Produksi</button>
            </div>
            <div class="table-responsive mb-4">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-primary"><tr><th>Komponen Biaya</th><th class="text-end">Rencana</th><th class="text-end">Aktual</th><th class="text-end">Selisih</th></tr></thead>
                    <tbody>
                        <tr><td>Bahan Baku</td><td class="text-end">-</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                        <tr><td>Tenaga Kerja</td><td class="text-end">-</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                        <tr><td>Alat</td><td class="text-end">-</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                        <tr><td>Sewa</td><td class="text-end">-</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                        <tr><td>Overhead</td><td class="text-end">-</td><td class="text-end">-</td><td class="text-end">-</td></tr>
                    </tbody>
                    <tfoot><tr class="fw-semibold"><td>Total Biaya</td><td class="text-end">-</td><td class="text-end">-</td><td class="text-end">-</td></tr></tfoot>
                </table>
            </div>
            <div class="fw-semibold mb-2">Hasil Produksi Oleh</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>No</th><th>Nama</th><th>Peran</th><th>Mulai</th><th>Selesai</th></tr></thead>
                    <tbody>
                    @forelse($workers as $worker)
                        <tr><td>{{ $loop->iteration }}</td><td>{{ $worker->name }}</td><td>Operator Produksi</td><td>-</td><td>-</td></tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">Belum ada pekerja.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="hasilProduksiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-box-arrow-in-down me-2"></i>Input Hasil Produksi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="alert alert-info">Produk dan target mengikuti SPK ini. Tahap ini UI saja; belum ada proses penyimpanan atau posting.</div>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Hasil Bagus</label><div class="input-group"><input type="number" class="form-control" placeholder="0,00"><span class="input-group-text">Biji</span></div></div>
                <div class="col-md-6"><label class="form-label">Reject / Scrap</label><div class="input-group"><input type="number" class="form-control" placeholder="0,00"><span class="input-group-text">Biji</span></div></div>
                <div class="col-12"><label class="form-label">Catatan</label><textarea class="form-control" rows="3" placeholder="Catatan hasil produksi..."></textarea></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button><button type="button" class="btn btn-primary">Simpan Hasil Produksi</button></div>
    </div></div>
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
</div>
@endsection
