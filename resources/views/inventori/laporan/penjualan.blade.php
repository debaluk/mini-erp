@extends('layouts.app')

@section('content')
<style>
    .sales-report-table {
        min-width: 1080px;
    }

    .sales-report-table th,
    .sales-report-table td {
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

        .sales-report-table {
            min-width: 0;
        }
    }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h4 class="mb-1">Laporan Penjualan</h4>
        <div class="text-secondary small">Laporan transaksi penjualan berdasarkan periode dan Business Unit</div>
    </div>

    <div class="d-flex gap-2 no-print">
        <a href="{{ route('laporan.penjualan.export', request()->query()) }}" class="btn btn-outline-success">
            📊 Export Excel
        </a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body border-bottom no-print">
        <form method="GET" action="{{ route('laporan.penjualan') }}">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label">Tanggal Mulai</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="form-control">
                </div>

                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label">Tanggal Akhir</label>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="form-control">
                </div>

                <div class="col-12 col-lg-3">
                    <label class="form-label">Customer</label>
                    <input
                        name="customer"
                        value="{{ request('customer') }}"
                        class="form-control"
                        placeholder="Cari customer..."
                    >
                </div>

                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label">Business Unit</label>
                    <select name="unit_id" class="form-select">
                        <option value="">Semua Business Unit</option>
                        @foreach($units as $u)
                            <option value="{{ $u->id }}" @selected((string) request('unit_id') === (string) $u->id)>
                                {{ $u->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-sm-6 col-lg-2">
                    <label class="form-label">Cara Bayar</label>
                    <select name="payment_method" class="form-select">
                        <option value="">Semua</option>
                        <option value="cash" @selected(request('payment_method') === 'cash')>Tunai</option>
                        <option value="credit" @selected(request('payment_method') === 'credit')>Kredit / Bon</option>
                        <option value="transfer" @selected(request('payment_method') === 'transfer')>Transfer</option>
                        <option value="qris" @selected(request('payment_method') === 'qris')>QRIS</option>
                    </select>
                </div>

                <div class="col-12 col-lg-1">
                    <button type="submit" class="btn btn-outline-primary w-100">
                        Cari
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 sales-report-table">
            <thead class="table-light">
                <tr>
                    <th>No. Penjualan</th>
                    <th>Tanggal</th>
                    <th>Customer</th>
                    <th>Business Unit</th>
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

                        <td>{{ $r->payment_methods ?? '-' }}</td>

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
                                {{ $r->status }}
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
                                    <form method="POST" action="{{ route('laporan.penjualan.posting', $r->id) }}" class="d-inline">
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
            {{ $rows->links() }}
        </div>
    @endif
</div>
@endsection
