<div class="card border-0 shadow-sm">
    <div class="card-header bg-primary bg-opacity-10 text-primary border-bottom py-3">
        <i class="bi bi-box-arrow-in-down me-1"></i> Hasil Produksi
    </div>
    <div class="card-body">
        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="text-secondary small">Target Produksi</div><div class="fw-semibold fs-5">{{ \App\Helpers\FormatHelper::indo((float) $wo->target_output_qty, 2) }} Biji</div></div>
            <div class="col-md-3"><div class="text-secondary small">Hasil Bagus</div><div class="fw-semibold fs-5 text-success">-</div></div>
            <div class="col-md-3"><div class="text-secondary small">Reject / Scrap</div><div class="fw-semibold fs-5 text-danger">-</div></div>
            <div class="col-md-3"><div class="text-secondary small">Status Hasil</div><span class="badge text-bg-secondary">Belum Diinput</span></div>
        </div>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div><div class="fw-semibold">Input Hasil Aktual</div><div class="text-secondary small">Hasil dicatat dari SPK ini.</div></div>
            <button type="button" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Input Hasil Produksi</button>
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