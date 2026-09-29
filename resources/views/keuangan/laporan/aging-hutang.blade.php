@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Header & Action Buttons -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold text-dark">Laporan Aging Hutang Supplier (AP Aging Analysis)</h3>
            <div class="text-secondary small">Analisis Umur Hutang Usaha, Struktur Jatuh Tempo Pembelian & Matriks Kewajiban Vendor</div>
        </div>
        <div class="d-flex gap-2">
            <button type="button" id="btn-export-excel" class="btn btn-success btn-sm px-3 fw-semibold shadow-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> Cetak Laporan Excel
            </button>
        </div>
    </div>

    <!-- 5 Summary KPI Cards Matrix -->
    <div class="row g-3 mb-4">
        <div class="col-md">
            <div class="p-3 bg-white rounded border shadow-sm border-start border-4" style="border-color: #7e22ce !important;">
                <div class="text-muted small fw-semibold">Total Sisa Hutang</div>
                <div class="fw-bold fs-5" style="color: #6b21a8;">Rp {{ number_format($totalAp, 2, ',', '.') }}</div>
                <div class="small text-muted">Seluruh Tagihan Active</div>
            </div>
        </div>
        <div class="col-md">
            <div class="p-3 bg-white rounded border shadow-sm border-start border-success border-4">
                <div class="text-muted small fw-semibold">Current (Belum Jatuh Tempo)</div>
                <div class="fw-bold fs-5 text-success">Rp {{ number_format($totalCurrent, 2, ',', '.') }}</div>
                <div class="small text-muted">Hutang Lancar</div>
            </div>
        </div>
        <div class="col-md">
            <div class="p-3 bg-white rounded border shadow-sm border-start border-warning border-4">
                <div class="text-muted small fw-semibold">Overdue 1 – 30 Hari</div>
                <div class="fw-bold fs-5 text-warning text-dark">Rp {{ number_format($totalAging1to30, 2, ',', '.') }}</div>
                <div class="small text-muted">Mulai Jatuh Tempo</div>
            </div>
        </div>
        <div class="col-md">
            <div class="p-3 bg-white rounded border shadow-sm border-start border-danger border-4">
                <div class="text-muted small fw-semibold">Overdue 31 – 60 Hari</div>
                <div class="fw-bold fs-5 text-danger">Rp {{ number_format($totalAging31to60, 2, ',', '.') }}</div>
                <div class="small text-muted">Perlu Penjadwalan Bayar</div>
            </div>
        </div>
        <div class="col-md">
            <div class="p-3 bg-dark text-white rounded border shadow-sm border-start border-danger border-4">
                <div class="text-white-50 small fw-semibold">Overdue > 60 Hari</div>
                <div class="fw-bold fs-5 text-danger">Rp {{ number_format($totalAgingAbove60, 2, ',', '.') }}</div>
                <div class="small text-white-50">Keterlambatan Tinggi</div>
            </div>
        </div>
    </div>

    <!-- Filter Form Bar -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <form id="form-filter-aging" method="GET" action="{{ route('akuntansi.aging-hutang.index') }}" class="row g-2 align-items-end">

                <div class="col-md-2">
                    <label class="form-label mb-1 small fw-bold">Per Tanggal (As of)</label>
                    <input type="date" name="as_of_date" class="form-control form-control-sm" value="{{ $asOfDate }}">
                </div>

                <div class="col-md-3">
                    <label class="form-label mb-1 small fw-bold">Unit Bisnis</label>
                    <select name="business_unit_id" class="form-select form-select-sm">
                        <option value="">Semua Unit Bisnis</option>
                        @foreach($businessUnits as $bu)
                            <option value="{{ $bu->id }}" {{ (string) $businessUnitId === (string) $bu->id ? 'selected' : '' }}>
                                {{ $bu->code }} - {{ $bu->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label mb-1 small fw-bold">Supplier</label>
                    <select name="supplier_id" class="form-select form-select-sm">
                        <option value="">Semua Supplier</option>
                        @foreach($suppliers as $s)
                            <option value="{{ $s->id }}" {{ (string) $supplierId === (string) $s->id ? 'selected' : '' }}>
                                {{ $s->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label mb-1 small fw-bold">Filter Bucket Umur</label>
                    <select name="bucket" class="form-select form-select-sm">
                        <option value="">Semua Bucket</option>
                        <option value="current" {{ $bucketFilter === 'current' ? 'selected' : '' }}>Current (Lancar)</option>
                        <option value="1_30" {{ $bucketFilter === '1_30' ? 'selected' : '' }}>1 - 30 Hari</option>
                        <option value="31_60" {{ $bucketFilter === '31_60' ? 'selected' : '' }}>31 - 60 Hari</option>
                        <option value="above_60" {{ $bucketFilter === 'above_60' ? 'selected' : '' }}>&gt; 60 Hari</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-search me-1"></i> Filter
                    </button>
                    <a href="{{ route('akuntansi.aging-hutang.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>

            </form>
        </div>
    </div>

    <!-- Nav Tabs View Mode -->
    <ul class="nav nav-tabs mb-3" id="agingTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-semibold" id="tab-detail-pur" data-bs-toggle="tab" data-bs-target="#content-detail-pur" type="button" role="tab">
                <i class="bi bi-list-columns me-1"></i> Detail Aging per Faktur
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold" id="tab-summary-supp" data-bs-toggle="tab" data-bs-target="#content-summary-supp" type="button" role="tab">
                <i class="bi bi-truck me-1"></i> Matriks Rekap per Supplier
            </button>
        </li>
    </ul>

    <div class="tab-content" id="agingTabsContent">

        <!-- TAB 1: DETAIL AGING PER FAKTUR -->
        <div class="tab-pane fade show active" id="content-detail-pur" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.85rem;">
                            <thead class="table-dark text-center">
                                <tr>
                                    <th style="width: 90px;">Tgl. Faktur</th>
                                    <th style="width: 140px;">No. Pembelian</th>
                                    <th class="text-start">Supplier</th>
                                    <th style="width: 120px;">Unit Bisnis</th>
                                    <th style="width: 90px;">Jatuh Tempo</th>
                                    <th style="width: 100px;">Status / Umur</th>
                                    <th style="width: 110px;">Current (Rp)</th>
                                    <th style="width: 110px;">1 - 30 Hr (Rp)</th>
                                    <th style="width: 110px;">31 - 60 Hr (Rp)</th>
                                    <th style="width: 110px;">&gt; 60 Hr (Rp)</th>
                                    <th style="width: 130px;" class="text-end">Total Hutang</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($filteredList as $pur)
                                    <tr>
                                        <td class="text-center">{{ \Carbon\Carbon::parse($pur->purchase_date)->format('d/m/Y') }}</td>
                                        <td class="text-center font-monospace fw-semibold text-primary">{{ $pur->purchase_no }}</td>
                                        <td>
                                            <div class="fw-semibold text-dark">{{ $pur->supplier_name }}</div>
                                            <div class="text-muted small" style="font-size: 0.75rem;">{{ $pur->supplier_phone }}</div>
                                        </td>
                                        <td class="text-center">{{ $pur->business_unit_name ?? '-' }}</td>
                                        <td class="text-center">{{ $pur->due_date ? \Carbon\Carbon::parse($pur->due_date)->format('d/m/Y') : '-' }}</td>
                                        <td class="text-center">
                                            @if($pur->bucket === 'current')
                                                <span class="badge bg-success">LANCAR</span>
                                            @elseif($pur->bucket === '1_30')
                                                <span class="badge bg-warning text-dark">{{ $pur->days }} HARI</span>
                                            @elseif($pur->bucket === '31_60')
                                                <span class="badge bg-danger">{{ $pur->days }} HARI</span>
                                            @else
                                                <span class="badge bg-dark text-danger border border-danger">{{ $pur->days }} HARI</span>
                                            @endif
                                        </td>
                                        <td class="text-end text-success">{{ $pur->val_current > 0 ? number_format($pur->val_current, 0, ',', '.') : '-' }}</td>
                                        <td class="text-end text-warning fw-semibold">{{ $pur->val_1_30 > 0 ? number_format($pur->val_1_30, 0, ',', '.') : '-' }}</td>
                                        <td class="text-end text-danger fw-semibold">{{ $pur->val_31_60 > 0 ? number_format($pur->val_31_60, 0, ',', '.') : '-' }}</td>
                                        <td class="text-end fw-bold text-danger">{{ $pur->val_above_60 > 0 ? number_format($pur->val_above_60, 0, ',', '.') : '-' }}</td>
                                        <td class="text-end fw-bold text-dark">Rp {{ number_format($pur->remaining_amount, 2, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="text-center py-4 text-muted">
                                            <i class="bi bi-check-circle fs-3 d-block text-success mb-1"></i>
                                            Tidak ada hutang tertunggak pada filter umur ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot class="table-light fw-bold text-end">
                                <tr>
                                    <td colspan="6" class="text-center">TOTAL KESELURUHAN</td>
                                    <td class="text-success">Rp {{ number_format($totalCurrent, 0, ',', '.') }}</td>
                                    <td class="text-warning">Rp {{ number_format($totalAging1to30, 0, ',', '.') }}</td>
                                    <td class="text-danger">Rp {{ number_format($totalAging31to60, 0, ',', '.') }}</td>
                                    <td class="text-danger">Rp {{ number_format($totalAgingAbove60, 0, ',', '.') }}</td>
                                    <td class="text-dark fs-6">Rp {{ number_format($totalAp, 2, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: MATRIKS REKAP PER SUPPLIER -->
        <div class="tab-pane fade" id="content-summary-supp" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle mb-0" style="font-size: 0.88rem;">
                            <thead class="table-secondary text-center">
                                <tr>
                                    <th class="text-start">Nama Supplier</th>
                                    <th style="width: 140px;">Current / Lancar</th>
                                    <th style="width: 140px;">1 - 30 Hari</th>
                                    <th style="width: 140px;">31 - 60 Hari</th>
                                    <th style="width: 140px;">&gt; 60 Hari</th>
                                    <th style="width: 160px;" class="text-end">Total Hutang</th>
                                    <th style="width: 130px;">Tingkat Risiko</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($supplierSummary as $s)
                                    @php
                                        $hasHighRisk = ($s->aging_31_60 > 0 || $s->aging_above_60 > 0);
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $s->supplier_name }}</div>
                                            <div class="text-muted small" style="font-size: 0.75rem;">Telepon: {{ $s->phone }}</div>
                                        </td>
                                        <td class="text-end text-success">{{ $s->current > 0 ? number_format($s->current, 0, ',', '.') : '-' }}</td>
                                        <td class="text-end text-warning fw-semibold">{{ $s->aging_1_30 > 0 ? number_format($s->aging_1_30, 0, ',', '.') : '-' }}</td>
                                        <td class="text-end text-danger fw-semibold">{{ $s->aging_31_60 > 0 ? number_format($s->aging_31_60, 0, ',', '.') : '-' }}</td>
                                        <td class="text-end fw-bold text-danger">{{ $s->aging_above_60 > 0 ? number_format($s->aging_above_60, 0, ',', '.') : '-' }}</td>
                                        <td class="text-end fw-bold text-dark">Rp {{ number_format($s->total_ap, 2, ',', '.') }}</td>
                                        <td class="text-center">
                                            @if($hasHighRisk)
                                                <span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i> TINGGI</span>
                                            @elseif($s->aging_1_30 > 0)
                                                <span class="badge bg-warning text-dark"><i class="bi bi-info-circle me-1"></i> SEDANG</span>
                                            @else
                                                <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i> AMAN</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            Tidak ada data ringkasan hutang supplier.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Action Ekspor Excel Aging Hutang
    document.getElementById('btn-export-excel')?.addEventListener('click', function () {
        const form     = document.getElementById('form-filter-aging');
        const formData = new FormData(form);
        const params   = new URLSearchParams(formData).toString();

        window.location.href = "{{ route('akuntansi.aging-hutang.export') }}?" + params;
    });
});
</script>
@endsection
