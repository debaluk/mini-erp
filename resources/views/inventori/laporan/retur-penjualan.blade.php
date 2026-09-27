@extends('layouts.app')

@section('content')
<div class="container-fluid py-3">

    {{-- HEADER --}}
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h4 class="mb-1">Laporan Retur Penjualan</h4>
            <div class="text-muted">
                Analisis retur, refund, HPP, dan dampak laba kotor
            </div>
        </div>

        <a href="{{ route('laporan.retur-penjualan.export', request()->query()) }}"
           class="btn btn-success">
            Export Excel
        </a>
    </div>

    {{-- FILTER --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET"
                  action="{{ route('laporan.retur-penjualan') }}"
                  class="row g-3 align-items-end">

                <div class="col-md-3">
                    <label class="form-label">Mulai</label>
                    <input type="date"
                           name="start_date"
                           value="{{ $startDate }}"
                           class="form-control">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Sampai</label>
                    <input type="date"
                           name="end_date"
                           value="{{ $endDate }}"
                           class="form-control">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Business Unit</label>
                    <select name="unit_id" class="form-select">
                        <option value="">Semua Business Unit</option>
                        @foreach($units as $unit)
                            <option value="{{ $unit->id }}"
                                @selected((int) $businessUnitId === (int) $unit->id)>
                                {{ $unit->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 d-grid">
                    <button class="btn btn-primary">
                        Tampilkan
                    </button>
                </div>

            </form>
        </div>
    </div>

    {{-- IDENTITY --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row small">
                <div class="col-md-4">
                    <span class="text-muted">Entitas:</span>
                    <strong>{{ $entityName }}</strong>
                </div>
                <div class="col-md-4">
                    <span class="text-muted">Periode:</span>
                    <strong>{{ $startDate }} s/d {{ $endDate }}</strong>
                </div>
                <div class="col-md-4">
                    <span class="text-muted">Business Unit:</span>
                    <strong>{{ $selectedUnitName ?: 'Semua' }}</strong>
                </div>
            </div>
        </div>
    </div>

    {{-- KPI --}}
    <div class="row g-3 mb-3">

        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total Retur</div>
                    <div class="fs-4 fw-bold">
                        {{ number_format((int)($summary->total_returns ?? 0), 0, ',', '.') }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Nilai Retur</div>
                    <div class="fs-4 fw-bold">
                        Rp {{ number_format($returnValue, 0, ',', '.') }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Refund Kas</div>
                    <div class="fs-4 fw-bold">
                        Rp {{ number_format($refundCash, 0, ',', '.') }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Refund Bank</div>
                    <div class="fs-4 fw-bold">
                        Rp {{ number_format($refundBank, 0, ',', '.') }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Pengurang Piutang</div>
                    <div class="fs-4 fw-bold">
                        Rp {{ number_format($refundReceivable, 0, ',', '.') }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">HPP Retur Good</div>
                    <div class="fs-4 fw-bold">
                        Rp {{ number_format($hppReturn, 0, ',', '.') }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Retur Good</div>
                    <div class="fs-4 fw-bold">
                        {{ number_format((float)($summary->good_qty ?? 0), 2, ',', '.') }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Retur Reject</div>
                    <div class="fs-4 fw-bold">
                        {{ number_format((float)($summary->reject_qty ?? 0), 2, ',', '.') }}
                    </div>
                </div>
            </div>
        </div>

    </div>

    {{-- DAMPAK LABA --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="text-muted small">Dampak Laba Kotor</div>
            <div class="fs-3 fw-bold">
                Rp {{ number_format($grossProfitImpact, 0, ',', '.') }}
            </div>
            <div class="small text-muted mt-1">
                Nilai retur dikurangi HPP retur kondisi good.
                Kerugian reject ditampilkan terpisah.
            </div>
        </div>
    </div>

    {{-- PENJUALAN PER BU --}}
    <div class="card shadow-sm mb-3">
        <div class="card-header fw-bold">
            Retur per Business Unit
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                <tr>
                    <th>Business Unit</th>
                    <th class="text-end">Retur</th>
                    <th class="text-end">Nilai Retur</th>
                    <th class="text-end">HPP Good</th>
                    <th class="text-end">Kerugian Reject</th>
                </tr>
                </thead>
                <tbody>
                @forelse($byBusinessUnit as $row)
                    <tr>
                        <td>{{ $row->business_unit_name }}</td>
                        <td class="text-end">
                            {{ number_format((int)$row->return_count, 0, ',', '.') }}
                        </td>
                        <td class="text-end">
                            Rp {{ number_format((float)$row->return_value, 0, ',', '.') }}
                        </td>
                        <td class="text-end">
                            Rp {{ number_format((float)$row->hpp_return, 0, ',', '.') }}
                        </td>
                        <td class="text-end">
                            Rp {{ number_format((float)$row->reject_loss, 0, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-3">
                            Tidak ada data.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- TOP PRODUCTS --}}
    <div class="card shadow-sm mb-3">
        <div class="card-header fw-bold">
            Produk Paling Banyak Diretur
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                <tr>
                    <th>Produk</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Nilai Retur</th>
                    <th class="text-end">HPP Good</th>
                    <th class="text-end">Kerugian Reject</th>
                </tr>
                </thead>
                <tbody>
                @forelse($topProducts as $row)
                    <tr>
                        <td>{{ $row->product_name }}</td>
                        <td class="text-end">
                            {{ number_format((float)$row->qty, 2, ',', '.') }}
                        </td>
                        <td class="text-end">
                            Rp {{ number_format((float)$row->return_value, 0, ',', '.') }}
                        </td>
                        <td class="text-end">
                            Rp {{ number_format((float)$row->hpp_good, 0, ',', '.') }}
                        </td>
                        <td class="text-end">
                            Rp {{ number_format((float)$row->reject_loss, 0, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-3">
                            Tidak ada data.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- GOOD VS REJECT --}}
    <div class="card shadow-sm mb-3">
        <div class="card-header fw-bold">
            Good vs Reject
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                <tr>
                    <th>Kondisi</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Nilai Retur</th>
                    <th class="text-end">HPP</th>
                </tr>
                </thead>
                <tbody>
                @forelse($conditionSummary as $row)
                    <tr>
                        <td>{{ strtoupper($row->condition) }}</td>
                        <td class="text-end">
                            {{ number_format((float)$row->qty, 2, ',', '.') }}
                        </td>
                        <td class="text-end">
                            Rp {{ number_format((float)$row->return_value, 0, ',', '.') }}
                        </td>
                        <td class="text-end">
                            Rp {{ number_format((float)$row->hpp, 0, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-3">
                            Tidak ada data.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- TREND --}}
    <div class="card shadow-sm mb-3">
        <div class="card-header fw-bold">
            Tren Retur
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                <tr>
                    <th>Tanggal</th>
                    <th class="text-end">Jumlah Retur</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Nilai Retur</th>
                </tr>
                </thead>
                <tbody>
                @forelse($trend as $row)
                    <tr>
                        <td>{{ $row->period }}</td>
                        <td class="text-end">
                            {{ number_format((int)$row->return_count, 0, ',', '.') }}
                        </td>
                        <td class="text-end">
                            {{ number_format((float)$row->qty, 2, ',', '.') }}
                        </td>
                        <td class="text-end">
                            Rp {{ number_format((float)$row->return_value, 0, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-3">
                            Tidak ada data.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- DETAIL --}}
    <div class="card shadow-sm mb-3">
        <div class="card-header fw-bold">
            Detail Transaksi
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                <tr>
                    <th>No. Retur</th>
                    <th>Tanggal</th>
                    <th>No. Faktur</th>
                    <th>Customer</th>
                    <th>BU</th>
                    <th>Produk</th>
                    <th class="text-end">Qty</th>
                    <th class="text-end">Nilai Retur</th>
                    <th class="text-end">HPP</th>
                    <th>Kondisi</th>
                    <th>Gudang</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
                </thead>

                <tbody>
                @forelse($detail as $row)
                    <tr>
                        <td>{{ $row->return_no }}</td>
                        <td>{{ $row->return_date }}</td>
                        <td>{{ $row->invoice_no }}</td>
                        <td>{{ $row->customer_name ?: '-' }}</td>
                        <td>{{ $row->business_unit_name ?: '-' }}</td>
                        <td>{{ $row->product_name ?: '-' }}</td>
                        <td class="text-end">
                            {{ number_format((float)$row->qty, 2, ',', '.') }}
                        </td>
                        <td class="text-end">
                            Rp {{ number_format((float)$row->return_value, 0, ',', '.') }}
                        </td>
                        <td class="text-end">
                            Rp {{ number_format((float)$row->hpp_total, 0, ',', '.') }}
                        </td>
                        <td>{{ strtoupper($row->condition) }}</td>
                        <td>{{ $row->warehouse_name ?: '-' }}</td>
                        <td>{{ $row->status }}</td>
                        <td>
                            <a href="{{ url('/inventori/penjualan/retur/'.$row->id.'/print') }}"
                               target="_blank"
                               class="btn btn-sm btn-outline-secondary"
                               title="Cetak Nota Kredit">
                                <i class="bi bi-printer"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="13" class="text-center text-muted py-4">
                            Tidak ada transaksi retur pada periode ini.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($detail->hasPages())
            <div class="card-footer">
                {{ $detail->onEachSide(1)->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>

</div>
@endsection
