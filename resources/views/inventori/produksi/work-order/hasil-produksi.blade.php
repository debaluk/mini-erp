<div class="card border-0 shadow-sm">
    <div class="card-header bg-primary bg-opacity-10 text-primary border-bottom py-3">
        <i class="bi bi-box-arrow-in-down me-1"></i> Hasil Produksi
    </div>

    <div class="card-body">
        {{-- 1. Informasi Header SPK --}}
        <div class="card border mb-4">
            <div class="card-header bg-light fw-semibold">
                1. Informasi Header SPK
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="text-secondary small">No. SPK</div>
                        <div class="fw-semibold">{{ $wo->wo_no }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-secondary small">Tanggal SPK</div>
                        <div class="fw-semibold">{{ \Carbon\Carbon::parse($wo->wo_date)->format('d/m/Y') }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-secondary small">Tanggal Mulai Kerja</div>
                        <div class="fw-semibold">
                            {{ $wo->started_at ? \Carbon\Carbon::parse($wo->started_at)->format('d/m/Y H:i') : '-' }}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-secondary small">Status SPK</div>
                        <span class="badge text-bg-secondary">{{ ucfirst(str_replace('_', ' ', $wo->status)) }}</span>
                    </div>

                    <div class="col-md-3">
                        <div class="text-secondary small">Produk</div>
                        <div class="fw-semibold">{{ $wo->product_name }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-secondary small">BOM</div>
                        <div class="fw-semibold">{{ $wo->bom_code }} - {{ $wo->bom_name }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-secondary small">Gudang</div>
                        <div class="fw-semibold">{{ $wo->warehouse_name }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-secondary small">Target Produksi</div>
                        <div class="fw-semibold fs-5">{{ \App\Helpers\FormatHelper::indo((float) $wo->target_output_qty, 2) }} Biji</div>
                    </div>
                </div>

                <hr class="my-3">

                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">Hasil Produksi</div>
                        <div class="text-secondary small">Hasil aktual dari SPK ini.</div>
                    </div>
                    <button type="button" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i> Input Hasil Produksi
                    </button>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-md-4">
                        <div class="text-secondary small">Hasil Bagus</div>
                        <div class="fw-semibold fs-5 text-success">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-secondary small">Reject / Scrap</div>
                        <div class="fw-semibold fs-5 text-danger">-</div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-secondary small">Status Hasil</div>
                        <span class="badge text-bg-secondary">Belum Diinput</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. Informasi Bahan --}}
        <div class="card border mb-4">
            <div class="card-header bg-light fw-semibold">
                2. Informasi Bahan
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Kode</th>
                                <th>Nama Bahan</th>
                                <th class="text-end">Qty Rencana</th>
                                <th>Satuan</th>
                                <th class="text-end">Harga Satuan</th>
                                <th class="text-end">Estimasi Biaya</th>
                                <th class="text-end">Actual Qty</th>
                                <th class="text-end">Actual Biaya</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($materials as $material)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $material['sku'] }}</td>
                                <td>{{ $material['name'] }}</td>
                                <td class="text-end">{{ \App\Helpers\FormatHelper::indo((float) $material['qty'], 3) }}</td>
                                <td>{{ $material['unit'] }}</td>
                                <td class="text-end">Rp {{ number_format((float) $material['unit_cost'], 2, ',', '.') }}</td>
                                <td class="text-end">Rp {{ number_format((float) $material['line_cost'], 2, ',', '.') }}</td>
                                <td class="text-end">-</td>
                                <td class="text-end">-</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-3">Belum ada bahan.</td>
                            </tr>
                        @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold">
                                <td colspan="8" class="text-end">Total Estimasi Bahan</td>
                                <td class="text-end">Rp {{ number_format((float) $materialTotal, 2, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- 3. Informasi Upah Tenaga Kerja --}}
        <div class="card border">
            <div class="card-header bg-light fw-semibold">
                3. Informasi Upah Tenaga Kerja
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>No</th>
                                <th>Kode</th>
                                <th>Nama Pekerja</th>
                                <th class="text-end">Estimasi Upah</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($workers as $worker)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $worker->code }}</td>
                                <td>{{ $worker->name }}</td>
                                <td class="text-end">Rp {{ number_format((float) $worker->estimated_cost, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">Belum ada pekerja.</td>
                            </tr>
                        @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="fw-semibold">
                                <td colspan="3" class="text-end">Total Estimasi Upah</td>
                                <td class="text-end">Rp {{ number_format((float) $woCosts->where('cost_group', 'U')->sum('amount'), 2, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

        {{-- 4. Hasil Produksi --}}
        <div class="card border">
            <div class="card-header bg-light fw-semibold">
                4. Hasil Produksi
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Target Produksi</label>
                        <div class="form-control bg-light">
                            {{ \App\Helpers\FormatHelper::indo((float) $wo->target_output_qty, 2) }} Biji
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Pekerja</label>
                        <select class="form-select" disabled>
                            @forelse($workers as $worker)
                                <option>{{ $worker->name }}</option>
                            @empty
                                <option>Belum ada pekerja</option>
                            @endforelse
                        </select>
                        <div class="form-text">Pekerja yang melakukan input hasil produksi.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Hasil Produksi</label>
                        <div class="input-group">
                            <input type="number" class="form-control" min="0" step="0.01" placeholder="0" disabled>
                            <span class="input-group-text">Biji</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Reject</label>
                        <div class="input-group">
                            <input type="number" class="form-control" min="0" step="0.01" placeholder="0" disabled>
                            <span class="input-group-text">Biji</span>
                        </div>
                    </div>

                    <div class="col-12">
                        <button type="button" class="btn btn-primary" disabled>
                            <i class="bi bi-check-lg me-1"></i> Simpan Hasil Produksi
                        </button>
                    </div>
                </div>
            </div>
        </div>
