@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Header Page -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-0">Buku Besar (General Ledger)</h4>
            <p class="text-muted small mb-0">Laporan mutasi kronologis dan kalkulasi saldo running per akun COA</p>
        </div>
    </div>

    <!-- Card Filter -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <form id="form-filter-buku-besar" method="GET" action="{{ route('akuntansi.buku-besar') }}" class="row g-2 align-items-end">

                <!-- Filter 1: Pilih COA (Wajib) -->
                <div class="col-md-4">
                    <label class="form-label mb-1 small fw-bold">Pilih Akun COA <span class="text-danger">*</span></label>
                    <select name="account_id" class="form-select form-select-sm" required>
                        <option value="">-- Pilih Akun --</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ (string) $accountId === (string) $acc->id ? 'selected' : '' }}>
                                [{{ $acc->code }}] {{ $acc->name }} ({{ strtoupper($acc->normal_balance) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filter 2: Business Unit -->
                <div class="col-md-2">
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

                <!-- Filter 3: Mulai Tanggal -->
                <div class="col-md-2">
                    <label class="form-label mb-1 small fw-bold">Mulai Tanggal</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
                </div>

                <!-- Filter 4: Sampai Tanggal -->
                <div class="col-md-2">
                    <label class="form-label mb-1 small fw-bold">Sampai Tanggal</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
                </div>

                <!-- Action Buttons -->
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm px-3 flex-fill">
                       Tampilkan
                    </button>

                    @if($selectedAccount)
                        <button type="button" id="btn-export-excel" class="btn btn-success btn-sm px-3">
                            Excel
                        </button>
                    @endif
                </div>

            </form>
        </div>
    </div>

    @if($selectedAccount)
        <!-- Summary Cards -->
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="p-3 bg-light rounded border shadow-sm">
                    <div class="text-muted small">Akun Terpilih</div>
                    <div class="fw-bold fs-6 text-dark">[{{ $selectedAccount->code }}] {{ $selectedAccount->name }}</div>
                    <span class="badge bg-secondary">Normal: {{ strtoupper($selectedAccount->normal_balance) }}</span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-light rounded border shadow-sm">
                    <div class="text-muted small">Saldo Awal Periode</div>
                    <div class="fw-bold fs-5 text-primary">Rp {{ number_format($initialBalance, 2, ',', '.') }}</div>
                    <div class="small text-muted">Per Tanggal {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3 bg-light rounded border shadow-sm">
                    <div class="text-muted small">Saldo Akhir Periode</div>
                    <div class="fw-bold fs-5 text-success">Rp {{ number_format($endingBalance, 2, ',', '.') }}</div>
                    <div class="small text-muted">Per Tanggal {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}</div>
                </div>
            </div>
        </div>

        <!-- Table Ledger -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.9rem;">
                        <thead class="table-dark">
                            <tr>
                                <th class="text-center" style="width: 100px;">Tanggal</th>
                                <th class="text-center" style="width: 150px;">No. Jurnal</th>
                                <th style="width: 150px;">Business Unit</th>
                                <th class="text-center" style="width: 110px;">Tipe</th>
                                <th>Keterangan / Memo</th>
                                <th class="text-end" style="width: 140px;">Debit (Rp)</th>
                                <th class="text-end" style="width: 140px;">Kredit (Rp)</th>
                                <th class="text-end" style="width: 160px;">Saldo Running (Rp)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Row Saldo Awal -->
                            <tr class="table-warning fw-bold">
                                <td class="text-center">{{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}</td>
                                <td class="text-center">-</td>
                                <td>-</td>
                                <td class="text-center"><span class="badge bg-secondary">INITIAL</span></td>
                                <td>SALDO AWAL PERIODE</td>
                                <td class="text-end">-</td>
                                <td class="text-end">-</td>
                                <td class="text-end fw-bold">Rp {{ number_format($initialBalance, 2, ',', '.') }}</td>
                            </tr>

                            @forelse($ledgerData as $row)
                                <tr>
                                    <td class="text-center">
                                        {{ $row->journal?->journal_date ? \Carbon\Carbon::parse($row->journal->journal_date)->format('d/m/Y') : '-' }}
                                    </td>
                                    <td class="text-center font-monospace">
                                        {{ $row->journal?->journal_no ?? '-' }}
                                    </td>
                                    <td>{{ $row->journal?->businessUnit?->name ?? '-' }}</td>
                                    <td class="text-center">
                                        <span class="badge bg-info text-dark">{{ strtoupper($row->journal?->source_type ?? 'MANUAL') }}</span>
                                    </td>
                                    <td>{{ $row->journal?->description ?? '-' }}</td>
                                    <td class="text-end">{{ $row->debit > 0 ? number_format($row->debit, 2, ',', '.') : '-' }}</td>
                                    <td class="text-end">{{ $row->credit > 0 ? number_format($row->credit, 2, ',', '.') : '-' }}</td>
                                    <td class="text-end fw-semibold">Rp {{ number_format($row->running_balance, 2, ',', '.') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                                        Tidak ada mutasi transaksi untuk akun ini pada periode yang dipilih.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-light border-top border-2">
                            <tr class="fw-bold">
                                <td colspan="5" class="text-end">TOTAL MUTASI PERIODE INI:</td>
                                <td class="text-end text-primary">Rp {{ number_format($ledgerData->sum('debit'), 2, ',', '.') }}</td>
                                <td class="text-end text-danger">Rp {{ number_format($ledgerData->sum('credit'), 2, ',', '.') }}</td>
                                <td class="text-end text-success">Rp {{ number_format($endingBalance, 2, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    @else
        <!-- Placeholder -->
        <div class="card shadow-sm border-0 text-center py-5">
            <div class="card-body">
                <i class="bi bi-journal-text text-secondary display-4 d-block mb-3"></i>
                <h5 class="fw-bold">Pilih Akun COA Terlebih Dahulu</h5>
                <p class="text-muted small max-w-md mx-auto">
                    Silakan pilih salah satu Akun COA pada form filter di atas lalu klik tombol <strong>Tampilkan</strong> untuk memuat data Buku Besar.
                </p>
            </div>
        </div>
    @endif

</div>

<!-- JavaScript Export Handler -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('btn-export-excel')?.addEventListener('click', function () {
        const form     = document.getElementById('form-filter-buku-besar');
        const formData = new FormData(form);
        const params   = new URLSearchParams(formData).toString();

        window.location.href = "{{ route('akuntansi.buku-besar.export') }}?" + params;
    });
});
</script>
@endsection