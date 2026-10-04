@extends('layouts.app')

@section('content')
<style>
    .report-kpi {
        min-height: 118px;
    }

    .report-kpi .label {
        font-size: .8rem;
        color: #6c757d;
    }

    .report-kpi .value {
        font-size: 1.35rem;
        font-weight: 700;
    }

    .report-table th {
        white-space: nowrap;
        vertical-align: middle;
    }

    .report-table td {
        vertical-align: middle;
    }

    .trend-table td,
    .trend-table th {
        white-space: nowrap;
    }

    .sales-report-detail {
        min-width: 1080px;
    }

    .sales-report-detail th {
        white-space: normal;
        vertical-align: middle;
    }

    .sales-report-detail td {
        white-space: nowrap;
    }

    @media print {
        .no-print {
            display: none !important;
        }

        .card {
            border: 0 !important;
            box-shadow: none !important;
        }

        .table-responsive {
            overflow: visible !important;
        }

        .sales-report-detail {
            min-width: 0;
        }
    }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h4 class="mb-1">Laporan Penjualan</h4>
        <div class="text-secondary small">
            Analisis penjualan, penerimaan, dan piutang
        </div>
    </div>

    <div class="d-flex gap-2 no-print">
        <a
            href="{{ route('laporan.penjualan.export', request()->query()) }}"
            class="btn btn-outline-success"
        >
            📊 Export Excel
        </a>

    </div>
</div>

{{-- Filter --}}
<div class="card shadow-sm mb-3 no-print">
    <div class="card-body">
        <form method="GET" action="{{ route('laporan.penjualan') }}">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label">Tanggal Mulai</label>
                    <input
                        type="date"
                        name="start_date"
                        value="{{ $startDate }}"
                        class="form-control"
                    >
                </div>

                <div class="col-12 col-sm-6 col-lg-3">
                    <label class="form-label">Tanggal Akhir</label>
                    <input
                        type="date"
                        name="end_date"
                        value="{{ $endDate }}"
                        class="form-control"
                    >
                </div>

                <div class="col-12 col-sm-8 col-lg-4">
                    <label class="form-label">Unit Bisnis</label>
                    <select name="unit_id" class="form-select">
                        <option value="">Semua Unit Bisnis</option>

                        @foreach($units as $u)
                            <option
                                value="{{ $u->id }}"
                                @selected((string) $businessUnitId === (string) $u->id)
                            >
                                {{ $u->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-sm-4 col-lg-2">
                    <button type="submit" class="btn btn-outline-primary w-100">
                        Tampilkan
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Identitas laporan --}}
<div class="card shadow-sm mb-3">
    <div class="card-body py-3">
        <div class="row g-2 small">
            <div class="col-12 col-md-4">
                <div class="text-secondary">Entitas</div>
                <div class="fw-semibold">{{ $entityName }}</div>
            </div>

            <div class="col-12 col-md-4">
                <div class="text-secondary">Periode</div>
                <div class="fw-semibold">
                    {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}
                    s/d
                    {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
                </div>
            </div>

            <div class="col-12 col-md-4">
                <div class="text-secondary">Unit Bisnis</div>
                <div class="fw-semibold">{{ $selectedUnitName ?? 'Semua Unit Bisnis' }}</div>
            </div>
        </div>
    </div>
</div>

{{-- KPI --}}
<div class="row g-3 mb-3">
    <div class="col-12 col-sm-6 col-lg-2">
        <div class="card shadow-sm report-kpi">
            <div class="card-body">
                <div class="label">Penjualan</div>
                <div class="value">Rp {{ number_format($salesTotal, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-2">
        <div class="card shadow-sm report-kpi">
            <div class="card-body">
                <div class="label">Retur</div>
                <div class="value">Rp {{ number_format($returnTotal, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-2">
        <div class="card shadow-sm report-kpi">
            <div class="card-body">
                <div class="label">Penjualan Bersih</div>
                <div class="value">Rp {{ number_format($netSales, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-2">
        <div class="card shadow-sm report-kpi">
            <div class="card-body">
                <div class="label">HPP</div>
                <div class="value">Rp {{ number_format($netHpp, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-2">
        <div class="card shadow-sm report-kpi">
            <div class="card-body">
                <div class="label">Laba Kotor</div>
                <div class="value">Rp {{ number_format($grossProfit, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    <div class="col-12 col-sm-6 col-lg-2">
        <div class="card shadow-sm report-kpi">
            <div class="card-body">
                <div class="label">Margin</div>
                <div class="value">{{ number_format($margin, 2, ',', '.') }}%</div>
            </div>
        </div>
    </div>
</div>

{{-- Penerimaan & Piutang --}}
<div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold">
        Penerimaan & Piutang
    </div>

    <div class="table-responsive">
        <table class="table table-hover report-table mb-0">
            <thead class="table-light">
                <tr>
                    <th>Komponen</th>
                    <th class="text-end">Nilai</th>
                </tr>
            </thead>

            <tbody>
                <tr>
                    <td>Kas Tunai</td>
                    <td class="text-end">Rp {{ number_format($cashReceipt, 0, ',', '.') }}</td>
                </tr>

                <tr>
                    <td>Bank</td>
                    <td class="text-end">Rp {{ number_format($bankReceipt, 0, ',', '.') }}</td>
                </tr>

                <tr class="fw-semibold">
                    <td>Total Penerimaan</td>
                    <td class="text-end">Rp {{ number_format($totalReceipt, 0, ',', '.') }}</td>
                </tr>

                <tr>
                    <td>Penjualan Kredit</td>
                    <td class="text-end">Rp {{ number_format($creditSales, 0, ',', '.') }}</td>
                </tr>

                <tr>
                    <td>Pembayaran Piutang</td>
                    <td class="text-end">Rp {{ number_format($receivablePayments, 0, ',', '.') }}</td>
                </tr>

                <tr class="fw-semibold">
                    <td>Saldo Piutang</td>
                    <td class="text-end">Rp {{ number_format($receivable, 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

{{-- Komposisi Pembayaran --}}
<div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold">
        Komposisi Pembayaran
    </div>

    <div class="table-responsive">
        <table class="table table-hover report-table mb-0">
            <thead class="table-light">
                <tr>
                    <th>Metode</th>
                    <th class="text-end">Nilai</th>
                </tr>
            </thead>

            <tbody>
                <tr>
                    <td>Kas Tunai</td>
                    <td class="text-end">
                        Rp {{ number_format($paymentComposition['cash'], 0, ',', '.') }}
                    </td>
                </tr>

                <tr>
                    <td>Bank</td>
                    <td class="text-end">
                        Rp {{ number_format($paymentComposition['bank'], 0, ',', '.') }}
                    </td>
                </tr>

                <tr>
                    <td>Piutang</td>
                    <td class="text-end">
                        Rp {{ number_format($paymentComposition['credit'], 0, ',', '.') }}
                    </td>
                </tr>

                <tr class="fw-semibold">
                    <td>Total</td>
                    <td class="text-end">
                        Rp {{ number_format($paymentComposition['total'], 0, ',', '.') }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

{{-- Trend --}}
<div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold">
        Trend Penjualan
    </div>

    <div class="table-responsive">
        <table class="table table-hover report-table trend-table mb-0">
            <thead class="table-light">
                <tr>
                    <th>Periode</th>
                    <th class="text-end">Transaksi</th>
                    <th class="text-end">Penjualan</th>
                </tr>
            </thead>

            <tbody>
                @forelse($trend as $t)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($t->period)->format('d/m/Y') }}</td>
                        <td class="text-end">{{ number_format((int) $t->transactions, 0, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format((float) $t->sales, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center text-secondary py-3">
                            Tidak ada data trend pada periode ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Penjualan per Unit Bisnis --}}
<div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold">
        Penjualan per Unit Bisnis
    </div>

    <div class="table-responsive">
        <table class="table table-hover report-table mb-0">
            <thead class="table-light">
                <tr>
                    <th>Unit Bisnis</th>
                    <th class="text-end">Penjualan</th>
                    <th class="text-end">Retur</th>
                    <th class="text-end">Bersih</th>
                    <th class="text-end">HPP</th>
                    <th class="text-end">Laba Kotor</th>
                </tr>
            </thead>

            <tbody>
                @forelse($unitSales as $u)
                    <tr>
                        <td class="fw-semibold">{{ $u->name }}</td>
                        <td class="text-end">Rp {{ number_format((float) $u->sales, 0, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format((float) $u->return, 0, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format((float) $u->net_sales, 0, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format((float) $u->hpp, 0, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format((float) $u->gross_profit, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-secondary py-3">
                            Tidak ada data Unit Bisnis pada periode ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Produk Terlaris --}}
<div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold">
        Produk Terlaris
    </div>

    <div class="table-responsive">
        <table class="table table-hover report-table mb-0">
            <thead class="table-light">
                <tr>
                    <th>Produk</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Penjualan</th>
                    <th class="text-end">Laba Kotor</th>
                </tr>
            </thead>

            <tbody>
                @forelse($topProducts as $p)
                    <tr>
                        <td class="fw-semibold">{{ $p->product_name }}</td>
                        <td class="text-end">{{ number_format((float) $p->qty, 3, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format((float) $p->sales, 0, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format((float) $p->gross_profit, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-secondary py-3">
                            Tidak ada data produk pada periode ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Pelanggan Terbesar --}}
<div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold">
        Pelanggan Terbesar
    </div>

    <div class="table-responsive">
        <table class="table table-hover report-table mb-0">
            <thead class="table-light">
                <tr>
                    <th>Pelanggan</th>
                    <th class="text-end">Transaksi</th>
                    <th class="text-end">Penjualan</th>
                    <th class="text-end">Piutang</th>
                </tr>
            </thead>

            <tbody>
                @forelse($topPelanggans as $c)
                    <tr>
                        <td class="fw-semibold">{{ $c->customer_name }}</td>
                        <td class="text-end">{{ number_format((int) $c->transactions, 0, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format((float) $c->sales, 0, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format((float) $c->receivable, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-secondary py-3">
                            Tidak ada data pelanggan pada periode ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Detail Transaksi --}}
<div class="card shadow-sm">
    <div class="card-header fw-semibold">
        Detail Transaksi
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 sales-report-detail">
            <thead class="table-light">
                <tr>
                    <th>No. Penjualan</th>
                    <th>Tanggal</th>
                    <th>Pelanggan</th>
                    <th>Unit Bisnis</th>
                    <th>Cara Bayar</th>
                    <th>Jatuh Tempo</th>
                    <th class="text-end">Subtotal</th>
                    <th class="text-end">Diskon</th>
                    <th class="text-end">Total</th>
                    <th>Status</th>
                    <th class="text-end no-print">Aksi</th>
                </tr>
            </thead>

            <tbody>
                @forelse($rows as $r)
                    <tr>
                        <td class="fw-semibold">{{ $r->invoice_no }}</td>

                        <td>{{ \Carbon\Carbon::parse($r->sale_date)->format('d/m/Y') }}</td>

                        <td>{{ $r->customer_name ?? 'Umum' }}</td>

                        <td>{{ $r->unit_name ?? '-' }}</td>

                        <td>{{ collect(explode(', ', (string) $r->payment_methods))->map(fn($m) => match ($m) { 'cash' => 'Tunai', 'credit' => 'Kredit / Bon', 'transfer' => 'Transfer', 'qris' => 'QRIS', default => $m ?: '-' })->implode(', ') ?: '-' }}</td>

                        <td>
                            {{ !empty($r->due_date) ? \Carbon\Carbon::parse($r->due_date)->format('d/m/Y') : '-' }}
                        </td>

                        <td class="text-end">
                            Rp {{ number_format((float) $r->subtotal, 0, ',', '.') }}
                        </td>

                        <td class="text-end">
                            Rp {{ number_format((float) $r->discount, 0, ',', '.') }}
                        </td>

                        <td class="text-end fw-semibold">
                            Rp {{ number_format((float) $r->total, 0, ',', '.') }}
                        </td>

                        <td>
                            @php
                                $status = strtolower((string) $r->status);
                                $statusClass = match ($status) {
                                    'posted', 'paid', 'lunas' => 'text-bg-success',
                                    'pending', 'draft' => 'text-bg-warning',
                                    'cancelled', 'canceled', 'void' => 'text-bg-danger',
                                    default => 'text-bg-secondary',
                                };
                            @endphp

                            <span class="badge {{ $statusClass }}">
                                {{ match (strtolower((string) $r->status)) { 'posted' => 'Diposting', 'draft' => 'Draf', 'cancelled', 'canceled', 'void' => 'Dibatalkan', 'paid', 'lunas' => 'Lunas', default => $r->status ?: '-' } }}
                            </span>
                        </td>

                        <td class="text-end no-print">
                            <div class="d-inline-flex gap-1">
                                <a
                                    href="{{ route('inventori.penjualan.show', $r->id) }}"
                                    class="btn btn-sm btn-outline-secondary"
                                >
                                    Lihat
                                </a>

                                @if(empty($r->journal_id))
                                    <form
                                        method="POST"
                                        action="{{ route('laporan.penjualan.posting', $r->id) }}"
                                        class="d-inline"
                                    >
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            Posting
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="text-center text-secondary py-4">
                            Belum ada transaksi penjualan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if(method_exists($rows, 'links'))
        <div class="card-footer no-print">
            {{ $rows->onEachSide(1)->links("pagination::bootstrap-5") }}
        </div>
    @endif
</div>
@endsection
