@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Header Page -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0">Neraca Saldo (Trial Balance)</h4>
            <p class="text-muted small mb-0">Rekapitulasi mutasi dan pengujian keseimbangan saldo Debit & Kredit seluruh akun COA</p>
        </div>
    </div>

    <!-- Card Filter -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <form id="form-filter-neraca-saldo" method="GET" action="{{ route('akuntansi.neraca-saldo') }}" class="row g-2 align-items-end">

                <!-- Filter 1: Business Unit -->
                <div class="col-md-3">
                    <label class="form-label mb-1 small fw-bold">Business Unit</label>
                    <select name="business_unit_id" class="form-select form-select-sm">
                        <option value="">Semua Business Unit</option>
                        @foreach($businessUnits as $bu)
                            <option value="{{ $bu->id }}" {{ (string) $businessUnitId === (string) $bu->id ? 'selected' : '' }}>
                                {{ $bu->code }} - {{ $bu->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filter 2: Mulai Tanggal -->
                <div class="col-md-3">
                    <label class="form-label mb-1 small fw-bold">Mulai Tanggal</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
                </div>

                <!-- Filter 3: Sampai Tanggal -->
                <div class="col-md-3">
                    <label class="form-label mb-1 small fw-bold">Sampai Tanggal</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
                </div>

                <!-- Action Buttons -->
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        Tampilkan
                    </button>

                    <button type="button" id="btn-export-excel" class="btn btn-success btn-sm px-3">
                        Excel
                    </button>
                </div>

            </form>
        </div>
    </div>

    <!-- Indicator Balance Alert -->
    <div class="alert {{ $isBalanced ? 'alert-success' : 'alert-danger' }} shadow-sm d-flex align-items-center justify-content-between mb-3 py-2 px-3">
        <div class="d-flex align-items-center gap-2">
            <i class="bi {{ $isBalanced ? 'bi-check-circle-fill fs-4 text-success' : 'bi-exclamation-triangle-fill fs-4 text-danger' }}"></i>
            <div>
                <strong class="d-block">{{ $isBalanced ? 'STATUS: BALANCE (SEIMBANG)' : 'STATUS: UNBALANCED (TIDAK SEIMBANG)' }}</strong>
                <span class="small">
                    {{ $isBalanced 
                        ? 'Total Saldo Akhir Debit dan Kredit seimbang 100%. Data pembukuan valid.' 
                        : 'Terdapat selisih antara Total Debit dan Kredit. Harap periksa jurnal penyesuaian!' }}
                </span>
            </div>
        </div>
        <div class="text-end fw-bold fs-6">
            Selisih: Rp {{ number_format(abs($totalEndingDebit - $totalEndingCredit), 2, ',', '.') }}
        </div>
    </div>

    <!-- Table Trial Balance -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.9rem;">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center" style="width: 120px;">Kode Akun</th>
                            <th>Nama Akun COA</th>
                            <th class="text-center" style="width: 120px;">Posisi Normal</th>
                            <th class="text-end" style="width: 150px;">Mutasi Debit (Rp)</th>
                            <th class="text-end" style="width: 150px;">Mutasi Kredit (Rp)</th>
                            <th class="text-end" style="width: 160px;">Saldo Akhir Debit (Rp)</th>
                            <th class="text-end" style="width: 160px;">Saldo Akhir Kredit (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($trialBalanceData as $row)
                            <tr>
                                <td class="text-center font-monospace fw-semibold">{{ $row->account_code }}</td>
                                <td>{{ $row->account_name }}</td>
                                <td class="text-center">
                                    <span class="badge {{ $row->normal_balance === 'debit' ? 'bg-primary' : 'bg-info text-dark' }}">
                                        {{ strtoupper($row->normal_balance) }}
                                    </span>
                                </td>
                                <td class="text-end">{{ $row->mutation_debit > 0 ? number_format($row->mutation_debit, 2, ',', '.') : '-' }}</td>
                                <td class="text-end">{{ $row->mutation_credit > 0 ? number_format($row->mutation_credit, 2, ',', '.') : '-' }}</td>
                                <td class="text-end fw-semibold text-primary">
                                    {{ $row->ending_debit > 0 ? 'Rp ' . number_format($row->ending_debit, 2, ',', '.') : '-' }}
                                </td>
                                <td class="text-end fw-semibold text-success">
                                    {{ $row->ending_credit > 0 ? 'Rp ' . number_format($row->ending_credit, 2, ',', '.') : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                                    Tidak ada mutasi transaksi pada periode filter yang dipilih.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="table-light border-top border-2">
                        <tr class="fw-bold">
                            <td colspan="3" class="text-end">TOTAL NERACA SALDO:</td>
                            <td class="text-end text-dark">Rp {{ number_format($totalMutationDebit, 2, ',', '.') }}</td>
                            <td class="text-end text-dark">Rp {{ number_format($totalMutationCredit, 2, ',', '.') }}</td>
                            <td class="text-end text-primary">Rp {{ number_format($totalEndingDebit, 2, ',', '.') }}</td>
                            <td class="text-end text-success">Rp {{ number_format($totalEndingCredit, 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- JavaScript Export Handler -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('btn-export-excel')?.addEventListener('click', function () {
        const form     = document.getElementById('form-filter-neraca-saldo');
        const formData = new FormData(form);
        const params   = new URLSearchParams(formData).toString();

        window.location.href = "{{ route('akuntansi.neraca-saldo.export') }}?" + params;
    });
});
</script>
@endsection