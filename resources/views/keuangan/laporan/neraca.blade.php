@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Header Page -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0">🏛️ Laporan Neraca Keuangan (Balance Sheet)</h4>
            <p class="text-muted small mb-0">Laporan posisi aset (aktiva) serta kewajiban dan ekuitas (pasiva) perusahaan</p>
        </div>
    </div>

    <!-- Card Filter -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <form id="form-filter-neraca" method="GET" action="{{ route('akuntansi.neraca') }}" class="row g-2 align-items-end">

                <!-- Filter 1: Business Unit -->
                <div class="col-md-4">
                    <label class="form-label mb-1 small fw-bold">Business Unit</label>
                    <select name="business_unit_id" class="form-select form-select-sm">
                        <option value="">Semua Business Unit (Konsolidasi)</option>
                        @foreach($businessUnits as $bu)
                            <option value="{{ $bu->id }}" {{ (string) $businessUnitId === (string) $bu->id ? 'selected' : '' }}>
                                {{ $bu->code }} - {{ $bu->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filter 2: Per Tanggal (As of Date) -->
                <div class="col-md-4">
                    <label class="form-label mb-1 small fw-bold">Per Tanggal (As of Date)</label>
                    <input type="date" name="as_of_date" class="form-control form-control-sm" value="{{ $asOfDate }}">
                </div>

                <!-- Action Buttons -->
                <div class="col-md-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm px-3 flex-fill">
                        <i class="bi bi-search me-1"></i> Tampilkan
                    </button>

                    <button type="button" id="btn-export-excel" class="btn btn-success btn-sm px-3">
                        <i class="bi bi-file-earmark-excel me-1"></i> Excel
                    </button>
                </div>

            </form>
        </div>
    </div>   

    <!-- Statement Grid 2 Kolom (Aktiva vs Pasiva) -->
    <div class="row g-4">

        <!-- KOLOM KIRI: ASET / AKTIVA -->
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-primary text-white fw-bold py-2">
                    ASET (AKTIVA)
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                            <tbody>
                                @forelse($assetAccounts as $item)
                                    <tr>
                                        <td class="ps-3 font-monospace text-muted" style="width: 120px;">{{ $item->code }}</td>
                                        <td>{{ $item->name }}</td>
                                        <td class="text-end pe-3 fw-semibold text-primary" style="width: 180px;">
                                            Rp {{ number_format($item->amount, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">Tidak ada data aset per tanggal ini.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light border-top border-2 fw-bold d-flex justify-content-between fs-6 py-3">
                    <span>TOTAL ASET (AKTIVA)</span>
                    <span class="text-primary">Rp {{ number_format($totalAssets, 2, ',', '.') }}</span>
                </div>
            </div>
        </div>

        <!-- KOLOM KANAN: KEWAJIBAN & EKUITAS / PASIVA -->
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-dark text-white fw-bold py-2">
                    KEWAJIBAN & EKUITAS (PASIVA)
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                            <tbody>
                                <!-- 1. KEWAJIBAN -->
                                <tr class="table-secondary fw-bold">
                                    <td colspan="3">I. KEWAJIBAN / UTANG</td>
                                </tr>
                                @forelse($liabilityAccounts as $item)
                                    <tr>
                                        <td class="ps-3 font-monospace text-muted" style="width: 120px;">{{ $item->code }}</td>
                                        <td>{{ $item->name }}</td>
                                        <td class="text-end pe-3 text-danger" style="width: 180px;">
                                            Rp {{ number_format($item->amount, 2, ',', '.') }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="ps-3 text-muted italic small">Tidak ada kewajiban / utang</td>
                                    </tr>
                                @endforelse
                                <tr class="fw-bold text-danger border-bottom">
                                    <td colspan="2" class="ps-3">TOTAL KEWAJIBAN</td>
                                    <td class="text-end pe-3">Rp {{ number_format($totalLiabilities, 2, ',', '.') }}</td>
                                </tr>

                                <!-- 2. EKUITAS -->
                                <tr class="table-secondary fw-bold">
                                    <td colspan="3">II. EKUITAS / MODAL</td>
                                </tr>
                                @foreach($equityAccounts as $item)
                                    <tr>
                                        <td class="ps-3 font-monospace text-muted">{{ $item->code }}</td>
                                        <td>{{ $item->name }}</td>
                                        <td class="text-end pe-3">Rp {{ number_format($item->amount, 2, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                                <tr class="table-warning">
                                    <td class="ps-3 font-monospace text-muted">-</td>
                                    <td class="fw-semibold">LABA / (RUGI) BERSIH PERIODE BERJALAN</td>
                                    <td class="text-end pe-3 fw-bold text-dark">
                                        Rp {{ number_format($currentNetProfit, 2, ',', '.') }}
                                    </td>
                                </tr>
                                <tr class="fw-bold text-success border-bottom">
                                    <td colspan="2" class="ps-3">TOTAL EKUITAS</td>
                                    <td class="text-end pe-3">Rp {{ number_format($grandTotalEquity, 2, ',', '.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light border-top border-2 fw-bold d-flex justify-content-between fs-6 py-3">
                    <span>TOTAL PASIVA (UTANG + MODAL)</span>
                    <span class="text-success">Rp {{ number_format($totalPasiva, 2, ',', '.') }}</span>
                </div>
            </div>
        </div>

    </div>
	</p>
	 <!-- Indicator Balance Alert -->
    <div class="alert {{ $isBalanced ? 'alert-success' : 'alert-danger' }} shadow-sm d-flex align-items-center justify-content-between mb-4 py-2 px-3">
        <div class="d-flex align-items-center gap-2">
            <i class="bi {{ $isBalanced ? 'bi-check-circle-fill fs-4 text-success' : 'bi-exclamation-triangle-fill fs-4 text-danger' }}"></i>
            <div>
                <strong class="d-block">{{ $isBalanced ? 'PERSAMAAN AKUN: BALANCE (SEIMBANG)' : 'PERSAMAAN AKUN: UNBALANCED (ADA SELISIH)' }}</strong>
                <span class="small">
                    {{ $isBalanced 
                        ? 'Total Aset (Aktiva) seimbang 100% dengan Total Kewajiban + Ekuitas (Pasiva).' 
                        : 'Terdapat selisih antara Aset dan Pasiva. Periksa kembali jurnal yang belum diposting!' }}
                </span>
            </div>
        </div>
        <div class="text-end fw-bold fs-6">
            Selisih: Rp {{ number_format(abs($totalAssets - $totalPasiva), 2, ',', '.') }}
        </div>
    </div>

</div>

<!-- JavaScript Export Handler -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('btn-export-excel')?.addEventListener('click', function () {
        const form     = document.getElementById('form-filter-neraca');
        const formData = new FormData(form);
        const params   = new URLSearchParams(formData).toString();

        window.location.href = "{{ route('akuntansi.neraca.export') }}?" + params;
    });
});
</script>
@endsection