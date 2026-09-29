@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Page Header (Judul & Tombol Export) -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold text-dark">Laporan Arus Kas</h3>
            <div class="text-secondary small">Monitoring Arus Kas Masuk & Keluar (Metode Langsung)</div>
        </div>
        <div>
            <button type="button" id="btn-export-excel" class="btn btn-success btn-sm px-3 fw-semibold">
                <i class="bi bi-file-earmark-excel me-1"></i> Cetak Excel
            </button>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="p-3 bg-white rounded border shadow-sm">
                <div class="text-muted small">Saldo Awal Kas & Bank</div>
                <div class="fw-bold fs-6 text-dark">Rp {{ number_format($initialBalance, 2, ',', '.') }}</div>
                <div class="small text-muted">Sebelum {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="p-3 bg-white rounded border shadow-sm">
                <div class="text-muted small">Arus Kas Operasional</div>
                <div class="fw-bold fs-6 {{ $netOperating >= 0 ? 'text-success' : 'text-danger' }}">
                    Rp {{ number_format($netOperating, 2, ',', '.') }}
                </div>
                <div class="small text-muted">Penerimaan & Beban Usaha</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="p-3 bg-white rounded border shadow-sm">
                <div class="text-muted small">Kenaikan / Penurunan Bersih</div>
                <div class="fw-bold fs-6 {{ $netCashChange >= 0 ? 'text-primary' : 'text-danger' }}">
                    Rp {{ number_format($netCashChange, 2, ',', '.') }}
                </div>
                <div class="small text-muted">Selisih Kas Periode Ini</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="p-3 bg-white rounded border shadow-sm bg-light">
                <div class="text-muted small">Saldo Akhir Kas & Bank</div>
                <div class="fw-bold fs-6 text-success">Rp {{ number_format($endingBalance, 2, ',', '.') }}</div>
                <div class="small text-muted">Per {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</div>
            </div>
        </div>
    </div>

    <!-- Card Filter List -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <form id="form-filter-arus-kas" method="GET" action="{{ route('akuntansi.arus-kas') }}" class="row g-2 align-items-end">

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
                    <label class="form-label mb-1 small fw-bold">Akun Kas / Bank</label>
                    <select name="cash_account_id" class="form-select form-select-sm">
                        <option value="">Semua Kas & Bank</option>
                        @foreach($cashAccounts as $acc)
                            <option value="{{ $acc->id }}" {{ (string) $cashAccountId === (string) $acc->id ? 'selected' : '' }}>
                                [{{ $acc->code }}] {{ $acc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label mb-1 small fw-bold">Mulai Tanggal</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
                </div>

                <div class="col-md-2">
                    <label class="form-label mb-1 small fw-bold">Sampai Tanggal</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <a href="{{ route('akuntansi.arus-kas') }}" class="btn btn-outline-secondary btn-sm w-50">Reset</a>
                    <button type="submit" class="btn btn-primary btn-sm w-50">Tampilkan</button>
                </div>

            </form>
        </div>
    </div>

    <!-- Table Statement Format Laporan Arus Kas -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-4">Uraian Aktivitas Arus Kas</th>
                            <th class="text-end pe-4" style="width: 250px;">Nominal (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>

                        <!-- 1. SALDO AWAL -->
                        <tr class="table-secondary fw-bold">
                            <td class="ps-4">SALDO AWAL KAS & BANK</td>
                            <td class="text-end pe-4">Rp {{ number_format($initialBalance, 2, ',', '.') }}</td>
                        </tr>

                        <!-- 2. AKTIVITAS OPERASIONAL -->
                        <tr class="table-light fw-bold text-primary">
                            <td colspan="2" class="ps-4">1. ARUS KAS DARI AKTIVITAS OPERASIONAL</td>
                        </tr>
                        
                        <!-- Penerimaan Operasional -->
                        <tr>
                            <td class="ps-5 text-muted fw-semibold">Penerimaan Kas Operasional:</td>
                            <td></td>
                        </tr>
                        @forelse($operatingInFlows as $accountName => $amount)
                            <tr>
                                <td class="ps-5">&nbsp;&nbsp;&nbsp;&nbsp;• {{ $accountName }}</td>
                                <td class="text-end pe-4 text-success">Rp {{ number_format($amount, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="ps-5 text-muted small">&nbsp;&nbsp;&nbsp;&nbsp; (Tidak ada penerimaan operasional)</td>
                                <td class="text-end pe-4">Rp 0,00</td>
                            </tr>
                        @endforelse

                        <!-- Pengeluaran Operasional -->
                        <tr>
                            <td class="ps-5 text-muted fw-semibold">Pengeluaran Kas Operasional:</td>
                            <td></td>
                        </tr>
                        @forelse($operatingOutFlows as $accountName => $amount)
                            <tr>
                                <td class="ps-5">&nbsp;&nbsp;&nbsp;&nbsp;• {{ $accountName }}</td>
                                <td class="text-end pe-4 text-danger">(Rp {{ number_format($amount, 2, ',', '.') }})</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="ps-5 text-muted small">&nbsp;&nbsp;&nbsp;&nbsp; (Tidak ada pengeluaran operasional)</td>
                                <td class="text-end pe-4">Rp 0,00</td>
                            </tr>
                        @endforelse

                        <tr class="fw-bold border-top border-bottom">
                            <td class="ps-4">Arus Kas Bersih dari Aktivitas Operasional</td>
                            <td class="text-end pe-4 {{ $netOperating >= 0 ? 'text-success' : 'text-danger' }}">
                                Rp {{ number_format($netOperating, 2, ',', '.') }}
                            </td>
                        </tr>

                        <!-- 3. AKTIVITAS INVESTASI -->
                        <tr class="table-light fw-bold text-primary">
                            <td colspan="2" class="ps-4">2. ARUS KAS DARI AKTIVITAS INVESTASI</td>
                        </tr>
                        @forelse($investingInFlows as $accountName => $amount)
                            <tr>
                                <td class="ps-5">&nbsp;&nbsp;&nbsp;&nbsp;• Penerimaan: {{ $accountName }}</td>
                                <td class="text-end pe-4 text-success">Rp {{ number_format($amount, 2, ',', '.') }}</td>
                            </tr>
                        @empty @endforelse

                        @forelse($investingOutFlows as $accountName => $amount)
                            <tr>
                                <td class="ps-5">&nbsp;&nbsp;&nbsp;&nbsp;• Pengeluaran: {{ $accountName }}</td>
                                <td class="text-end pe-4 text-danger">(Rp {{ number_format($amount, 2, ',', '.') }})</td>
                            </tr>
                        @empty 
                            @if(count($investingInFlows) == 0)
                                <tr>
                                    <td class="ps-5 text-muted small">&nbsp;&nbsp;&nbsp;&nbsp; (Tidak ada aktivitas investasi)</td>
                                    <td class="text-end pe-4">Rp 0,00</td>
                                </tr>
                            @endif
                        @endforelse

                        <tr class="fw-bold border-top border-bottom">
                            <td class="ps-4">Arus Kas Bersih dari Aktivitas Investasi</td>
                            <td class="text-end pe-4 {{ $netInvesting >= 0 ? 'text-success' : 'text-danger' }}">
                                Rp {{ number_format($netInvesting, 2, ',', '.') }}
                            </td>
                        </tr>

                        <!-- 4. AKTIVITAS PENDANAAN -->
                        <tr class="table-light fw-bold text-primary">
                            <td colspan="2" class="ps-4">3. ARUS KAS DARI AKTIVITAS PENDANAAN</td>
                        </tr>
                        @forelse($financingInFlows as $accountName => $amount)
                            <tr>
                                <td class="ps-5">&nbsp;&nbsp;&nbsp;&nbsp;• Setoran / Pinjaman: {{ $accountName }}</td>
                                <td class="text-end pe-4 text-success">Rp {{ number_format($amount, 2, ',', '.') }}</td>
                            </tr>
                        @empty @endforelse

                        @forelse($financingOutFlows as $accountName => $amount)
                            <tr>
                                <td class="ps-5">&nbsp;&nbsp;&nbsp;&nbsp;• Prive / Angsuran: {{ $accountName }}</td>
                                <td class="text-end pe-4 text-danger">(Rp {{ number_format($amount, 2, ',', '.') }})</td>
                            </tr>
                        @empty
                            @if(count($financingInFlows) == 0)
                                <tr>
                                    <td class="ps-5 text-muted small">&nbsp;&nbsp;&nbsp;&nbsp; (Tidak ada aktivitas pendanaan)</td>
                                    <td class="text-end pe-4">Rp 0,00</td>
                                </tr>
                            @endif
                        @endforelse

                        <tr class="fw-bold border-top border-bottom">
                            <td class="ps-4">Arus Kas Bersih dari Aktivitas Pendanaan</td>
                            <td class="text-end pe-4 {{ $netFinancing >= 0 ? 'text-success' : 'text-danger' }}">
                                Rp {{ number_format($netFinancing, 2, ',', '.') }}
                            </td>
                        </tr>

                        <!-- 5. REKAPITULASI AKHIR -->
                        <tr class="table-warning fw-bold">
                            <td class="ps-4">KENAIKAN / (PENURUNAN) BERSIH KAS & BANK</td>
                            <td class="text-end pe-4 {{ $netCashChange >= 0 ? 'text-primary' : 'text-danger' }}">
                                Rp {{ number_format($netCashChange, 2, ',', '.') }}
                            </td>
                        </tr>

                        <tr class="table-success fw-bold fs-6">
                            <td class="ps-4">SALDO AKHIR KAS & BANK</td>
                            <td class="text-end pe-4 text-success">
                                Rp {{ number_format($endingBalance, 2, ',', '.') }}
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- JavaScript Integration -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('btn-export-excel')?.addEventListener('click', function () {
        const form     = document.getElementById('form-filter-arus-kas');
        const formData = new FormData(form);
        const params   = new URLSearchParams(formData).toString();

        window.location.href = "{{ route('akuntansi.arus-kas.export') }}?" + params;
    });
});
</script>
@endsection