@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Header & Action Buttons -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold text-dark">Laporan Aging Piutang (AR Aging Analysis)</h3>
            <div class="text-secondary small">Analisis Umur Piutang, Struktur Keterlambatan Tagihan & Matriks Risiko Pelanggan</div>
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
            <div class="p-3 bg-white rounded border shadow-sm border-start border-primary border-4">
                <div class="text-muted small fw-semibold">Total Sisa Piutang</div>
                <div class="fw-bold fs-5 text-primary">Rp {{ number_format($totalAr, 2, ',', '.') }}</div>
                <div class="small text-muted">Seluruh Faktur Active</div>
            </div>
        </div>
        <div class="col-md">
            <div class="p-3 bg-white rounded border shadow-sm border-start border-success border-4">
                <div class="text-muted small fw-semibold">Current (Belum Jatuh Tempo)</div>
                <div class="fw-bold fs-5 text-success">Rp {{ number_format($totalCurrent, 2, ',', '.') }}</div>
                <div class="small text-muted">Piutang Lancar</div>
            </div>
        </div>
        <div class="col-md">
            <div class="p-3 bg-white rounded border shadow-sm border-start border-warning border-4">
                <div class="text-muted small fw-semibold">Overdue 1 – 30 Hari</div>
                <div class="fw-bold fs-5 text-warning text-dark">Rp {{ number_format($totalAging1to30, 2, ',', '.') }}</div>
                <div class="small text-muted">Peringatan Awal</div>
            </div>
        </div>
        <div class="col-md">
            <div class="p-3 bg-white rounded border shadow-sm border-start border-danger border-4">
                <div class="text-muted small fw-semibold">Overdue 31 – 60 Hari</div>
                <div class="fw-bold fs-5 text-danger">Rp {{ number_format($totalAging31to60, 2, ',', '.') }}</div>
                <div class="small text-muted">Perlu Penagihan Intensif</div>
            </div>
        </div>
        <div class="col-md">
            <div class="p-3 bg-dark text-white rounded border shadow-sm border-start border-danger border-4">
                <div class="text-white-50 small fw-semibold">Overdue > 60 Hari</div>
                <div class="fw-bold fs-5 text-danger">Rp {{ number_format($totalAgingAbove60, 2, ',', '.') }}</div>
                <div class="small text-white-50">Risiko Macet High</div>
            </div>
        </div>
    </div>

    <!-- Filter Form Bar -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <form id="form-filter-aging" method="GET" action="{{ route('akuntansi.aging-piutang.index') }}" class="row g-2 align-items-end">

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
                    <label class="form-label mb-1 small fw-bold">Pelanggan / Customer</label>
                    <select name="customer_id" class="form-select form-select-sm">
                        <option value="">Semua Pelanggan</option>
                        @foreach($customers as $c)
                            <option value="{{ $c->id }}" {{ (string) $customerId === (string) $c->id ? 'selected' : '' }}>
                                {{ $c->name }}
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
                    <a href="{{ route('akuntansi.aging-piutang.index') }}" class="btn btn-outline-secondary btn-sm">Reset</a>
                </div>

            </form>
        </div>
    </div>

    <!-- Nav Tabs View Mode -->
    <ul class="nav nav-tabs mb-3" id="agingTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-semibold" id="tab-detail-inv" data-bs-toggle="tab" data-bs-target="#content-detail-inv" type="button" role="tab">
                <i class="bi bi-list-columns me-1"></i> Detail Aging per Faktur
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold" id="tab-summary-cust" data-bs-toggle="tab" data-bs-target="#content-summary-cust" type="button" role="tab">
                <i class="bi bi-people-fill me-1"></i> Matriks Rekap per Pelanggan
            </button>
        </li>
    </ul>

    <div class="tab-content" id="agingTabsContent">

        <!-- TAB 1: DETAIL AGING PER FAKTUR -->
        <div class="tab-pane fade show active" id="content-detail-inv" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.85rem;">
                            <thead class="table-dark text-center">
                                <tr>
                                    <th style="width: 90px;">Tgl. Faktur</th>
                                    <th style="width: 130px;">No. Faktur</th>
                                    <th class="text-start">Pelanggan</th>
                                    <th style="width: 120px;">Unit Bisnis</th>
                                    <th style="width: 90px;">Jatuh Tempo</th>
                                    <th style="width: 100px;">Status / Umur</th>
                                    <th style="width: 110px;">Current (Rp)</th>
                                    <th style="width: 110px;">1 - 30 Hr (Rp)</th>
                                    <th style="width: 110px;">31 - 60 Hr (Rp)</th>
                                    <th style="width: 110px;">&gt; 60 Hr (Rp)</th>
                                    <th style="width: 130px;" class="text-end">Total Piutang</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($filteredList as $inv)
                                    <tr>
                                        <td class="text-center">{{ \Carbon\Carbon::parse($inv->sale_date)->format('d/m/Y') }}</td>
                                        <td class="text-center font-monospace fw-semibold text-primary">{{ $inv->invoice_no }}</td>
                                        <td>
                                            <div class="fw-semibold text-dark">{{ $inv->customer_name }}</div>
                                            <div class="text-muted small" style="font-size: 0.75rem;">{{ $inv->customer_phone }}</div>
                                        </td>
                                        <td class="text-center">{{ $inv->business_unit_name ?? '-' }}</td>
                                        <td class="text-center">{{ $inv->due_date ? \Carbon\Carbon::parse($inv->due_date)->format('d/m/Y') : '-' }}</td>
                                        <td class="text-center">
                                            @if($inv->bucket === 'current')
                                                <span class="badge bg-success">LANCAR</span>
                                            @elseif($inv->bucket === '1_30')
                                                <span class="badge bg-warning text-dark">{{ $inv->days }} HARI</span>
                                            @elseif($inv->bucket === '31_60')
                                                <span class="badge bg-danger">{{ $inv->days }} HARI</span>
                                            @else
                                                <span class="badge bg-dark text-danger border border-danger">{{ $inv->days }} HARI</span>
                                            @endif
                                        </td>
                                        <td class="text-end text-success">{{ $inv->val_current > 0 ? number_format($inv->val_current, 0, ',', '.') : '-' }}</td>
                                        <td class="text-end text-warning fw-semibold">{{ $inv->val_1_30 > 0 ? number_format($inv->val_1_30, 0, ',', '.') : '-' }}</td>
                                        <td class="text-end text-danger fw-semibold">{{ $inv->val_31_60 > 0 ? number_format($inv->val_31_60, 0, ',', '.') : '-' }}</td>
                                        <td class="text-end fw-bold text-danger">{{ $inv->val_above_60 > 0 ? number_format($inv->val_above_60, 0, ',', '.') : '-' }}</td>
                                        <td class="text-end fw-bold text-dark">Rp {{ number_format($inv->remaining_amount, 2, ',', '.') }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="text-center py-4 text-muted">
                                            <i class="bi bi-check-circle fs-3 d-block text-success mb-1"></i>
                                            Tidak ada piutang tertunggak pada filter umur ini.
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
                                    <td class="text-dark fs-6">Rp {{ number_format($totalAr, 2, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: MATRIKS REKAP PER PELANGGAN -->
        <div class="tab-pane fade" id="content-summary-cust" role="tabpanel">
            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered align-middle mb-0" style="font-size: 0.88rem;">
                            <thead class="table-secondary text-center">
                                <tr>
                                    <th class="text-start">Nama Pelanggan</th>
                                    <th style="width: 140px;">Limit Kredit (Rp)</th>
                                    <th style="width: 130px;">Current / Lancar</th>
                                    <th style="width: 130px;">1 - 30 Hari</th>
                                    <th style="width: 130px;">31 - 60 Hari</th>
                                    <th style="width: 130px;">&gt; 60 Hari</th>
                                    <th style="width: 150px;" class="text-end">Total Piutang</th>
                                    <th style="width: 120px;">Status Limit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($customerSummary as $c)
                                    @php
                                        $limitExceeded = ($c->credit_limit > 0 && $c->total_ar > $c->credit_limit);
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark">{{ $c->customer_name }}</div>
                                            <div class="text-muted small" style="font-size: 0.75rem;">Telepon: {{ $c->phone }}</div>
                                        </td>
                                        <td class="text-end fw-semibold">
                                            {{ $c->credit_limit > 0 ? 'Rp ' . number_format($c->credit_limit, 0, ',', '.') : 'No Limit' }}
                                        </td>
                                        <td class="text-end text-success">{{ $c->current > 0 ? number_format($c->current, 0, ',', '.') : '-' }}</td>
                                        <td class="text-end text-warning fw-semibold">{{ $c->aging_1_30 > 0 ? number_format($c->aging_1_30, 0, ',', '.') : '-' }}</td>
                                        <td class="text-end text-danger fw-semibold">{{ $c->aging_31_60 > 0 ? number_format($c->aging_31_60, 0, ',', '.') : '-' }}</td>
                                        <td class="text-end fw-bold text-danger">{{ $c->aging_above_60 > 0 ? number_format($c->aging_above_60, 0, ',', '.') : '-' }}</td>
                                        <td class="text-end fw-bold text-dark">Rp {{ number_format($c->total_ar, 2, ',', '.') }}</td>
                                        <td class="text-center">
                                            @if($limitExceeded)
                                                <span class="badge bg-danger"><i class="bi bi-exclamation-octagon me-1"></i> OVER LIMIT</span>
                                            @else
                                                <span class="badge bg-success"><i class="bi bi-shield-check me-1"></i> AMAN</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">
                                            Tidak ada data ringkasan piutang pelanggan.
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
    // Action Ekspor Excel Aging Piutang
    document.getElementById('btn-export-excel')?.addEventListener('click', function () {
        const form     = document.getElementById('form-filter-aging');
        const formData = new FormData(form);
        const params   = new URLSearchParams(formData).toString();

        window.location.href = "{{ route('akuntansi.aging-piutang.export') }}?" + params;
    });
});
</script>
@endsection