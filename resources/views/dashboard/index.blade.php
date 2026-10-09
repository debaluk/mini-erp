@extends('layouts.app')

@section('content')
<style>
    .dashboard-page { --dash-border: #e5eaf0; }
    .dashboard-page .dashboard-heading { color: #172b4d; letter-spacing: -.025em; }
    .dashboard-page .dashboard-subtitle { color: #667085; }
    .dashboard-page .metric-card {
        border: 1px solid var(--dash-border);
        border-radius: 12px;
        box-shadow: 0 2px 7px rgba(16, 24, 40, .035);
        transition: border-color .15s ease, transform .15s ease;
    }
    .dashboard-page .metric-card:hover { border-color: #cbd5e1; transform: translateY(-1px); }
    .dashboard-page .metric-label { color: #667085; font-size: .84rem; }
    .dashboard-page .metric-value { color: #172b4d; font-size: clamp(1.35rem, 2vw, 1.8rem); font-weight: 700; letter-spacing: -.025em; overflow-wrap: anywhere; }
    .dashboard-page .metric-icon {
        display: inline-flex; align-items: center; justify-content: center;
        width: 42px; height: 42px; border-radius: 10px;
        background: #f1f5f9; color: #475569; font-size: 1.15rem;
    }
    .dashboard-page .section-title { color: #24364b; font-size: 1rem; font-weight: 700; }
    .dashboard-page .summary-panel { background: #fff; border: 1px solid var(--dash-border); border-radius: 12px; }
    .dashboard-page .summary-row { padding: .9rem 1rem; }
    .dashboard-page .summary-row + .summary-row { border-top: 1px solid #edf0f4; }
</style>

<div class="dashboard-page">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <div class="small text-uppercase fw-semibold text-secondary mb-1">Ringkasan usaha</div>
            <h2 class="dashboard-heading fw-bold mb-1">Dashboard</h2>
            <div class="dashboard-subtitle">Pantau aktivitas utama perusahaan dalam satu halaman.</div>
        </div>
        <span class="badge rounded-pill text-bg-light border px-3 py-2 text-dark">
            <i class="bi bi-person-circle me-1"></i>{{ ucfirst(auth()->user()->role) }}
        </span>
    </div>

    <div class="section-title mb-3">Aktivitas hari ini</div>
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card metric-card h-100"><div class="card-body p-3 p-lg-4">
                <div class="d-flex align-items-start justify-content-between gap-3">
                    <div><div class="metric-label mb-2">Penjualan hari ini</div><div class="metric-value">Rp {{ number_format((float) $salesToday, 2, ',', '.') }}</div></div>
                    <span class="metric-icon"><i class="bi bi-receipt"></i></span>
                </div>
            </div></div>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card metric-card h-100"><div class="card-body p-3 p-lg-4">
                <div class="d-flex align-items-start justify-content-between gap-3">
                    <div><div class="metric-label mb-2">Pembelian hari ini</div><div class="metric-value">Rp {{ number_format((float) $purchasesToday, 2, ',', '.') }}</div></div>
                    <span class="metric-icon"><i class="bi bi-cart3"></i></span>
                </div>
            </div></div>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card metric-card h-100"><div class="card-body p-3 p-lg-4">
                <div class="d-flex align-items-start justify-content-between gap-3">
                    <div><div class="metric-label mb-2">Nilai persediaan</div><div class="metric-value">Rp {{ number_format((float) $stockValue, 2, ',', '.') }}</div></div>
                    <span class="metric-icon"><i class="bi bi-box-seam"></i></span>
                </div>
            </div></div>
        </div>
    </div>

    <div class="section-title mb-3">Data master</div>
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-4">
            <div class="card metric-card h-100"><div class="card-body p-3 d-flex align-items-center gap-3">
                <span class="metric-icon"><i class="bi bi-boxes"></i></span>
                <div><div class="metric-label">Produk / item</div><div class="metric-value">{{ number_format((int) $products, 0, ',', '.') }}</div></div>
            </div></div>
        </div>
        <div class="col-12 col-sm-4">
            <div class="card metric-card h-100"><div class="card-body p-3 d-flex align-items-center gap-3">
                <span class="metric-icon"><i class="bi bi-people"></i></span>
                <div><div class="metric-label">Pelanggan</div><div class="metric-value">{{ number_format((int) $customers, 0, ',', '.') }}</div></div>
            </div></div>
        </div>
        <div class="col-12 col-sm-4">
            <div class="card metric-card h-100"><div class="card-body p-3 d-flex align-items-center gap-3">
                <span class="metric-icon"><i class="bi bi-truck"></i></span>
                <div><div class="metric-label">Supplier</div><div class="metric-value">{{ number_format((int) $suppliers, 0, ',', '.') }}</div></div>
            </div></div>
        </div>
    </div>

    <div class="summary-panel">
        <div class="summary-row d-flex align-items-center gap-3">
            <span class="metric-icon"><i class="bi bi-diagram-3"></i></span>
            <div>
                <div class="fw-semibold text-dark">Alur operasional terintegrasi</div>
                <div class="small text-secondary">Pembelian, persediaan, produksi, penjualan, dan akuntansi tercatat melalui modul masing-masing.</div>
            </div>
        </div>
    </div>
</div>
@endsection
