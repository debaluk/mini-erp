@extends('layouts.app')

@section('title', 'Executive Dashboard')

@section('content')
@php
    $money = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $arTotal = array_sum($arBuckets);
    $apTotal = array_sum($apBuckets);
    $agingLabels = ['current' => 'Belum jatuh tempo', '1_30' => 'Lewat 1–30 hari', '31_60' => 'Lewat 31–60 hari', 'above_60' => 'Lewat >60 hari'];
@endphp
<style>
    .executive-dashboard { --dash-border: #e5eaf0; color: #172b4d; }
    .executive-dashboard .dash-card { border: 1px solid var(--dash-border); border-radius: 12px; box-shadow: 0 2px 7px rgba(16,24,40,.035); }
    .executive-dashboard .muted { color: #667085; }
    .executive-dashboard .kpi-label { font-size: .78rem; color: #667085; text-transform: uppercase; font-weight: 700; letter-spacing: .035em; }
    .executive-dashboard .kpi-value { font-size: clamp(1.15rem, 2vw, 1.7rem); font-weight: 750; letter-spacing: -.025em; overflow-wrap: anywhere; }
    .executive-dashboard .kpi-icon { display:inline-flex; width:42px; height:42px; align-items:center; justify-content:center; border-radius:11px; font-size:1.2rem; }
    .executive-dashboard .section-heading { font-size: 1rem; font-weight: 750; margin-bottom: .8rem; }
    .executive-dashboard .table { --bs-table-bg: transparent; }
    .executive-dashboard .small-label { font-size: .78rem; }
</style>

<div class="executive-dashboard container-fluid py-2">
    <div class="dash-card bg-white p-3 mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <span class="kpi-icon bg-primary-subtle text-primary"><i class="bi bi-speedometer2"></i></span>
                <div>
                    <h4 class="fw-bold mb-1">Executive Dashboard</h4>
                    <div class="muted small">Ringkasan operasional dan keuangan berdasarkan transaksi tercatat.</div>
                    <div class="muted small mt-1">Periode {{ \Carbon\Carbon::parse($startDate)->translatedFormat('F Y') }} · Cut-off {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</div>
                </div>
            </div>
            <form method="GET" action="{{ route('dashboard') }}" class="d-flex flex-wrap align-items-end gap-2">
                <div>
                    <label for="filter_unit_id" class="form-label small mb-1">Unit bisnis</label>
                    <select name="business_unit_id" id="filter_unit_id" class="form-select form-select-sm">
                        <option value="">Semua unit (konsolidasi)</option>
                        @foreach($businessUnits as $unit)
                            <option value="{{ $unit->id }}" @selected((string) $businessUnitId === (string) $unit->id)>{{ $unit->code }} — {{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="filter_period" class="form-label small mb-1">Periode</label>
                    <input type="month" id="filter_period" name="period" class="form-control form-control-sm" value="{{ $period }}">
                </div>
                <button class="btn btn-sm btn-primary px-3"><i class="bi bi-arrow-repeat me-1"></i>Terapkan</button>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3"><div class="dash-card bg-white h-100 p-3 border-start border-4 border-primary">
            <div class="d-flex justify-content-between gap-2"><div><div class="kpi-label mb-2">Penjualan Bersih</div><div class="kpi-value text-primary">{{ $money($netSales) }}</div><div class="small-label muted mt-2">Dari jurnal GL posted</div></div><span class="kpi-icon bg-primary-subtle text-primary"><i class="bi bi-cart-check"></i></span></div>
        </div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="dash-card bg-white h-100 p-3 border-start border-4 border-success">
            <div class="d-flex justify-content-between gap-2"><div><div class="kpi-label mb-2">Laba Kotor</div><div class="kpi-value text-success">{{ $money($grossProfit) }}</div><div class="small-label muted mt-2">Margin {{ number_format($grossMargin, 1, ',', '.') }}%</div></div><span class="kpi-icon bg-success-subtle text-success"><i class="bi bi-graph-up-arrow"></i></span></div>
        </div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="dash-card bg-white h-100 p-3 border-start border-4 border-info">
            <div class="d-flex justify-content-between gap-2"><div><div class="kpi-label mb-2">Modal Persediaan</div><div class="kpi-value text-info">{{ $money($stockValue) }}</div><div class="small-label muted mt-2">Qty × moving average cost</div></div><span class="kpi-icon bg-info-subtle text-info"><i class="bi bi-boxes"></i></span></div>
        </div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="dash-card bg-white h-100 p-3 border-start border-4 border-warning">
            <div class="d-flex justify-content-between gap-2"><div><div class="kpi-label mb-2">Saldo Kas &amp; Bank</div><div class="kpi-value">{{ $money($cashBankBalance) }}</div><div class="small-label muted mt-2">Mutasi kas periode: {{ $money($cashMovement) }}</div></div><span class="kpi-icon bg-warning-subtle text-warning"><i class="bi bi-bank"></i></span></div>
        </div></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-8"><div class="dash-card bg-white p-3 h-100">
            <div class="section-heading"><i class="bi bi-bar-chart-line text-primary me-2"></i>Tren Penjualan Bersih &amp; Laba Kotor</div>
            <div style="height:280px"><canvas id="chartSalesTrend" aria-label="Tren penjualan bersih dan laba kotor"></canvas></div>
        </div></div>
        <div class="col-12 col-xl-4"><div class="dash-card bg-white p-3 h-100">
            <div class="section-heading"><i class="bi bi-pie-chart text-primary me-2"></i>Kontribusi Penjualan per Unit</div>
            <div style="height:245px"><canvas id="chartBuContribution" aria-label="Kontribusi penjualan per unit bisnis"></canvas></div>
            <div class="small muted mt-2">Berdasarkan faktur penjualan posted pada periode terpilih.</div>
        </div></div>
    </div>

    <div class="section-heading">Performa Unit Bisnis</div>
    <div class="row g-3 mb-4">
        @foreach($buCards as $bu)
        <div class="col-12 col-md-4"><div class="dash-card bg-white h-100">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center gap-2"><div class="fw-bold">{{ $bu->code }} — {{ $bu->name }}</div><span class="badge text-bg-light border">Penjualan posted</span></div>
            <div class="p-3"><div class="kpi-label mb-1">Total penjualan periode ini</div><div class="kpi-value">{{ $money($bu->sales) }}</div>
                @if(strtoupper((string) $bu->code) === 'RET')
                    <div class="small muted mt-2">Retail · margin dan peringatan erosi margin perlu divalidasi per item terhadap HPP aktual.</div>
                @elseif(strtoupper((string) $bu->code) === 'PROD')
                    <div class="small muted mt-2">Produksi · output bagus, afkir, dan rincian BUASO belum ditampilkan sebelum sumber datanya diverifikasi.</div>
                @elseif(strtoupper((string) $bu->code) === 'JASA')
                    <div class="small muted mt-2">Armada · pendapatan sewa dan biaya BBM/maintenance menunggu pemetaan transaksi biaya yang tervalidasi.</div>
                @endif
            </div>
        </div></div>
        @endforeach
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-4"><div class="dash-card bg-white p-3 h-100">
            <div class="section-heading"><i class="bi bi-clipboard-check text-warning me-2"></i>Kontrol Persediaan</div>
            <div class="d-flex justify-content-between gap-2 mb-3"><span class="muted">Nilai penyesuaian stok posted</span><strong>{{ $money($stockAdjustments) }}</strong></div>
            <div class="d-flex justify-content-between gap-2 mb-3"><span class="muted">Jumlah item master</span><strong>{{ number_format((int) $products, 0, ',', '.') }}</strong></div>
            <div class="d-flex justify-content-between gap-2"><span class="muted">Nilai persediaan</span><strong>{{ $money($stockValue) }}</strong></div>
            <div class="small muted mt-3">Reorder point dan erosi margin belum diberi angka sampai field minimum stok dan riwayat harga jual/HPP terverifikasi.</div>
        </div></div>
        <div class="col-12 col-lg-4"><div class="dash-card bg-white p-3 h-100">
            <div class="section-heading"><i class="bi bi-person-lines-fill text-danger me-2"></i>Piutang Pelanggan (AR)</div>
            <div class="d-flex justify-content-between mb-3"><span class="muted">Total piutang tersisa</span><strong>{{ $money($arTotal) }}</strong></div>
            @foreach($agingLabels as $key => $label)<div class="d-flex justify-content-between gap-2 py-2 border-top small"><span>{{ $label }}</span><strong>{{ $money($arBuckets[$key]) }}</strong></div>@endforeach
            <div class="small muted mt-2">Credit limit per pelanggan perlu dibandingkan dengan saldo piutang masing-masing.</div>
        </div></div>
        <div class="col-12 col-lg-4"><div class="dash-card bg-white p-3 h-100">
            <div class="section-heading"><i class="bi bi-truck text-secondary me-2"></i>Hutang Supplier (AP)</div>
            <div class="d-flex justify-content-between mb-3"><span class="muted">Total hutang tersisa</span><strong>{{ $money($apTotal) }}</strong></div>
            @foreach($agingLabels as $key => $label)<div class="d-flex justify-content-between gap-2 py-2 border-top small"><span>{{ $label }}</span><strong>{{ $money($apBuckets[$key]) }}</strong></div>@endforeach
            <div class="small muted mt-2">Faktur posted dikurangi alokasi pembayaran supplier posted sampai tanggal cut-off.</div>
        </div></div>
    </div>

    <div class="dash-card bg-white p-3">
        <div class="section-heading"><i class="bi bi-clock-history text-info me-2"></i>Jurnal Terakhir pada Periode Terpilih</div>
        <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Tanggal</th><th>No. Jurnal</th><th>Sumber</th><th>Unit</th><th>Deskripsi</th></tr></thead>
            <tbody>
            @forelse($recentTransactions as $transaction)
                <tr><td class="text-nowrap">{{ \Carbon\Carbon::parse($transaction->journal_date)->format('d/m/Y') }}</td><td class="fw-semibold">{{ $transaction->journal_no ?? ('JRN-' . $transaction->id) }}</td><td>{{ $transaction->source_type ?: 'Umum' }}</td><td><span class="badge text-bg-light border">{{ $transaction->bu_code ?? '—' }}</span></td><td>{{ $transaction->description ?: '—' }}</td></tr>
            @empty
                <tr><td colspan="5" class="text-center muted py-4">Belum ada jurnal posted pada periode ini.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const labels = @json($trendLabels);
    const sales = @json($trendSales);
    const profit = @json($trendProfit);
    const buLabels = @json($buCards->map(fn ($bu) => $bu->code . ' — ' . $bu->name)->values());
    const buSales = @json($buCards->pluck('sales')->values());
    const rupiah = value => 'Rp ' + new Intl.NumberFormat('id-ID').format(value || 0);

    const trendCanvas = document.getElementById('chartSalesTrend');
    if (trendCanvas && window.Chart) new Chart(trendCanvas, {
        data: { labels, datasets: [
            { type: 'bar', label: 'Penjualan Bersih', data: sales, backgroundColor: 'rgba(13,110,253,.65)', borderColor: '#0d6efd', borderWidth: 1 },
            { type: 'line', label: 'Laba Kotor', data: profit, borderColor: '#198754', backgroundColor: '#198754', borderWidth: 2, tension: .25 }
        ]},
        options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: context => context.dataset.label + ': ' + rupiah(context.raw) } } },
            scales: { y: { beginAtZero: true, ticks: { callback: value => 'Rp ' + new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 }).format(value) } } }
        }
    });

    const buCanvas = document.getElementById('chartBuContribution');
    if (buCanvas && window.Chart) new Chart(buCanvas, {
        type: 'doughnut', data: { labels: buLabels, datasets: [{ data: buSales, backgroundColor: ['#0d6efd', '#198754', '#343a40', '#6f42c1', '#fd7e14'], borderWidth: 2 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: context => context.label + ': ' + rupiah(context.raw) } } } }
    });
});
</script>
@endpush
