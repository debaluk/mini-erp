@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Header Page -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0">Laporan Laba Rugi (Profit & Loss)</h4>
            <p class="text-muted small mb-0">Laporan kinerja keuangan segmental per Unit Bisnis maupun konsolidasi perusahaan</p>
        </div>
    </div>

    <!-- Card Filter -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <form id="form-filter-laba-rugi" method="GET" action="{{ route('akuntansi.laba-rugi') }}" class="row g-2 align-items-end">

                <!-- Filter 1: Business Unit -->
                <div class="col-md-3">
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

    <!-- Statement Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">

            <div class="table-responsive">
                <table class="table table-borderless align-middle mb-0" style="font-size: 0.95rem;">
                    <tbody>
                        <!-- 1. PENDAPATAN PENJUALAN KOTOR -->
                        <tr class="table-light fw-bold">
                            <td colspan="2" class="text-uppercase text-dark">I. PENDAPATAN PENJUALAN KOTOR</td>
                            <td></td>
                        </tr>
                        @forelse($grossSalesAccounts as $item)
                            <tr>
                                <td class="ps-4 font-monospace text-muted" style="width: 140px;">{{ $item->code }}</td>
                                <td>{{ $item->name }}</td>
                                <td class="text-end" style="width: 220px;">Rp {{ number_format($item->amount, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="ps-4 text-muted italic small">Tidak ada pendapatan penjualan</td>
                                <td class="text-end text-muted">Rp 0,00</td>
                            </tr>
                        @endforelse
                        <tr class="border-top fw-bold text-dark">
                            <td colspan="2" class="ps-4">TOTAL PENJUALAN KOTOR</td>
                            <td class="text-end">Rp {{ number_format($totalGrossSales, 2, ',', '.') }}</td>
                        </tr>

                        <!-- RETUR PENJUALAN -->
                        @if($returnSalesAccounts->count() > 0)
                            @foreach($returnSalesAccounts as $item)
                                <tr>
                                    <td class="ps-4 font-monospace text-muted">{{ $item->code }}</td>
                                    <td>{{ $item->name }}</td>
                                    <td class="text-end text-danger">(Rp {{ number_format($item->amount, 2, ',', '.') }})</td>
                                </tr>
                            @endforeach
                            <tr class="border-top fw-bold text-danger">
                                <td colspan="2" class="ps-4">TOTAL RETUR PENJUALAN</td>
                                <td class="text-end">(Rp {{ number_format($totalSalesReturns, 2, ',', '.') }})</td>
                            </tr>
                        @endif

                        <!-- PENJUALAN BERSIH -->
                        <tr class="table-primary fw-bold border-top border-bottom">
                            <td colspan="2" class="ps-3">PENJUALAN BERSIH (NET SALES)</td>
                            <td class="text-end text-primary fs-6">Rp {{ number_format($netSales, 2, ',', '.') }}</td>
                        </tr>

                        <tr><td colspan="3" class="py-2"></td></tr>

                        <!-- 2. BEBAN POKOK PENJUALAN (HPP) -->
                        <tr class="table-light fw-bold">
                            <td colspan="2" class="text-uppercase text-dark">II. BEBAN POKOK PENJUALAN (HPP)</td>
                            <td></td>
                        </tr>
                        @forelse($cogsAccounts as $item)
                            <tr>
                                <td class="ps-4 font-monospace text-muted">{{ $item->code }}</td>
                                <td>{{ $item->name }}</td>
                                <td class="text-end">Rp {{ number_format($item->amount, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="ps-4 text-muted italic small">Tidak ada Beban Pokok Penjualan</td>
                                <td class="text-end text-muted">Rp 0,00</td>
                            </tr>
                        @endforelse
                        <tr class="border-top fw-bold text-danger">
                            <td colspan="2" class="ps-4">TOTAL BEBAN POKOK PENJUALAN</td>
                            <td class="text-end">Rp {{ number_format($totalCogs, 2, ',', '.') }}</td>
                        </tr>

                        <!-- LABA KOTOR -->
                        <tr class="table-warning fw-bold border-top border-bottom border-2">
                            <td colspan="2" class="fs-6">LABA KOTOR (GROSS PROFIT)</td>
                            <td class="text-end fs-6 text-dark">Rp {{ number_format($grossProfit, 2, ',', '.') }}</td>
                        </tr>

                        <tr><td colspan="3" class="py-2"></td></tr>

                        <!-- 3. BEBAN OPERASIONAL & KERUGIAN AFKIR -->
                        <tr class="table-light fw-bold">
                            <td colspan="2" class="text-uppercase text-dark">III. BEBAN OPERASIONAL & KERUGIAN PERSEDIAAN</td>
                            <td></td>
                        </tr>
                        @forelse($expenseAccounts as $item)
                            <tr>
                                <td class="ps-4 font-monospace text-muted">{{ $item->code }}</td>
                                <td>{{ $item->name }}</td>
                                <td class="text-end">Rp {{ number_format($item->amount, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="ps-4 text-muted italic small">Tidak ada beban operasional / kerugian persediaan</td>
                                <td class="text-end text-muted">Rp 0,00</td>
                            </tr>
                        @endforelse
                        <tr class="border-top fw-bold text-danger">
                            <td colspan="2" class="ps-4">TOTAL BEBAN OPERASIONAL</td>
                            <td class="text-end">Rp {{ number_format($totalExpense, 2, ',', '.') }}</td>
                        </tr>

                        <!-- LABA OPERASIONAL -->
                        <tr class="table-info fw-bold border-top border-bottom border-2">
                            <td colspan="2" class="fs-6">LABA OPERASIONAL (OPERATING PROFIT)</td>
                            <td class="text-end fs-6 text-dark">Rp {{ number_format($operatingProfit, 2, ',', '.') }}</td>
                        </tr>

                        <tr><td colspan="3" class="py-2"></td></tr>

                        <!-- 4. PENDAPATAN & BEBAN LAIN-LAIN -->
                        @if($otherIncomeAccounts->count() > 0 || $otherExpenseAccounts->count() > 0)
                            <tr class="table-light fw-bold">
                                <td colspan="2" class="text-uppercase text-dark">IV. PENDAPATAN & BEBAN LAIN-LAIN</td>
                                <td></td>
                            </tr>
                            @foreach($otherIncomeAccounts as $item)
                                <tr>
                                    <td class="ps-4 font-monospace text-muted">{{ $item->code }}</td>
                                    <td>{{ $item->name }}</td>
                                    <td class="text-end">Rp {{ number_format($item->amount, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                            @foreach($otherExpenseAccounts as $item)
                                <tr>
                                    <td class="ps-4 font-monospace text-muted">{{ $item->code }}</td>
                                    <td>{{ $item->name }}</td>
                                    <td class="text-end text-danger">(Rp {{ number_format($item->amount, 2, ',', '.') }})</td>
                                </tr>
                            @endforeach
                            <tr class="border-top fw-bold text-dark">
                                <td colspan="2" class="ps-4">TOTAL PENDAPATAN / (BEBAN) LAIN-LAIN</td>
                                <td class="text-end">Rp {{ number_format($netOtherIncome, 2, ',', '.') }}</td>
                            </tr>
                        @endif

                        <!-- LABA BERSIH -->
                        <tr class="{{ $netProfit >= 0 ? 'bg-success text-white' : 'bg-danger text-white' }} fw-bold fs-5 border-top border-2">
                            <td colspan="2" class="ps-3 py-3">LABA BERSIH SEBELUM PAJAK (NET PROFIT)</td>
                            <td class="text-end pe-3 py-3">Rp {{ number_format($netProfit, 2, ',', '.') }}</td>
                        </tr>

                    </tbody>
                </table>
            </div>

        </div>
    </div>

</div>

<!-- JavaScript Export Handler -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('btn-export-excel')?.addEventListener('click', function () {
        const form     = document.getElementById('form-filter-laba-rugi');
        const formData = new FormData(form);
        const params   = new URLSearchParams(formData).toString();

        window.location.href = "{{ route('akuntansi.laba-rugi.export') }}?" + params;
    });
});
</script>
@endsection