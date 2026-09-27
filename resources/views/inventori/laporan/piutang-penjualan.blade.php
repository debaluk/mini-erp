@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Laporan Piutang Penjualan</h4>
        <div class="text-secondary small">Daftar operasional piutang dari penjualan kredit.</div>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Mulai</label>
                <input type="date" name="start_date" class="form-control"
                       value="{{ $startDate }}">
            </div>

            <div class="col-md-2">
                <label class="form-label">Sampai</label>
                <input type="date" name="end_date" class="form-control"
                       value="{{ $endDate }}">
            </div>

            <div class="col-md-3">
                <label class="form-label">Business Unit</label>
                <select name="unit_id" class="form-select">
                    <option value="">Semua Business Unit</option>
                    @foreach($units as $unit)
                        <option value="{{ $unit->id }}"
                            @selected((string) $businessUnitId === (string) $unit->id)>
                            {{ $unit->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Customer</label>
                <select name="customer_id" class="form-select">
                    <option value="">Semua Customer</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}"
                            @selected((string) $customerId === (string) $customer->id)>
                            {{ $customer->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="all" @selected($status === 'all')>Semua</option>
                    <option value="unpaid" @selected($status === 'unpaid')>Belum Lunas</option>
                    <option value="belum_jatuh_tempo" @selected($status === 'belum_jatuh_tempo')>Belum Jatuh Tempo</option>
                    <option value="jatuh_tempo" @selected($status === 'jatuh_tempo')>Jatuh Tempo</option>
                    <option value="lewat_jatuh_tempo" @selected($status === 'lewat_jatuh_tempo')>Lewat Jatuh Tempo</option>
                    <option value="lunas" @selected($status === 'lunas')>Lunas</option>
                </select>
            </div>

            <div class="col-12">
                <button class="btn btn-primary">Tampilkan</button>
                <a href="{{ route('laporan.piutang-penjualan') }}" class="btn btn-outline-secondary">Reset</a>

                @php
                    $exportParams = request()->query();
                @endphp

                <a href="{{ route('laporan.piutang-penjualan.export', $exportParams) }}"
                   class="btn btn-success">
                    Export Excel
                </a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-secondary small">Total Piutang</div>
                <div class="fs-5 fw-semibold">Rp {{ number_format($totalOutstanding, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-secondary small">Belum Jatuh Tempo</div>
                <div class="fs-5 fw-semibold">Rp {{ number_format($belumJatuhTempo, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-secondary small">Jatuh Tempo</div>
                <div class="fs-5 fw-semibold">Rp {{ number_format($jatuhTempo, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-secondary small">Lewat Jatuh Tempo</div>
                <div class="fs-5 fw-semibold">Rp {{ number_format($lewatJatuhTempo, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-semibold">
        Daftar Piutang Penjualan
    </div>

    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>No</th>
                    <th>Faktur</th>
                    <th>Tanggal</th>
                    <th>Customer</th>
                    <th>Business Unit</th>
                    <th>Jatuh Tempo</th>
                    <th class="text-end">Nilai Faktur</th>
                    <th class="text-end">Dibayar</th>
                    <th class="text-end">Sisa Piutang</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
                @forelse($rows as $row)
                    @php
                        $outstanding = (float) $row->outstanding;
                        $dueDate = $row->due_date ? substr((string) $row->due_date, 0, 10) : null;

                        if ($outstanding <= 0) {
                            $statusLabel = 'Lunas';
                            $statusClass = 'success';
                        } elseif ($dueDate === null || $dueDate > now()->toDateString()) {
                            $statusLabel = 'Belum Jatuh Tempo';
                            $statusClass = 'secondary';
                        } elseif ($dueDate === now()->toDateString()) {
                            $statusLabel = 'Jatuh Tempo';
                            $statusClass = 'warning';
                        } else {
                            $statusLabel = 'Lewat Jatuh Tempo';
                            $statusClass = 'danger';
                        }
                    @endphp

                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td class="fw-semibold">{{ $row->invoice_no }}</td>
                        <td>{{ \Carbon\Carbon::parse($row->sale_date)->format('d/m/Y') }}</td>
                        <td>{{ $row->customer_name ?: '-' }}</td>
                        <td>{{ $row->business_unit_name ?: '-' }}</td>
                        <td>{{ $dueDate ? \Carbon\Carbon::parse($dueDate)->format('d/m/Y') : '-' }}</td>
                        <td class="text-end">Rp {{ number_format($row->total, 0, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format($row->paid_amount, 0, ',', '.') }}</td>
                        <td class="text-end fw-semibold">Rp {{ number_format($outstanding, 0, ',', '.') }}</td>
                        <td>
                            <span class="badge text-bg-{{ $statusClass }}">
                                {{ $statusLabel }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center text-secondary py-4">
                            Tidak ada data piutang penjualan.
                        </td>
                    </tr>
                @endforelse
            </tbody>

            @if($rows->count())
                <tfoot class="table-light">
                    <tr class="fw-semibold">
                        <td colspan="6" class="text-end">TOTAL</td>
                        <td class="text-end">Rp {{ number_format($totalInvoice, 0, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format($totalPaid, 0, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format($totalOutstanding, 0, ',', '.') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
