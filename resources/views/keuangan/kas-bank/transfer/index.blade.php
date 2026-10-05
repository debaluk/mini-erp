@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">

    <!-- Page Header (Judul & Tombol Memicu Modal) -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h3 class="mb-1 fw-bold text-dark">Mutasi Kas & Bank</h3>
            <div class="text-secondary small">Transfer & Pemindahan Dana Internal Antar Rekening Kas/Bank</div>
        </div>
        <button type="button" class="btn btn-primary btn-sm px-3 fw-semibold" data-bs-toggle="modal" data-bs-target="#modal-tambah-mutasi">
            <i class="bi bi-arrow-left-right me-1"></i> Transfer Kas / Bank
        </button>
    </div>

    <!-- Alert Notifications -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="p-3 bg-white rounded border shadow-sm">
                <div class="text-muted small">Setoran ke Rekening Bank</div>
                <div class="fw-bold fs-5 text-primary">Rp {{ number_format($totalBankDeposit, 2, ',', '.') }}</div>
                <div class="small text-muted">Setoran Uang Kasir / Tunai ke Bank</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3 bg-white rounded border shadow-sm">
                <div class="text-muted small">Pengisian Kas Kecil</div>
                <div class="fw-bold fs-5 text-info">Rp {{ number_format($totalCashRefill, 2, ',', '.') }}</div>
                <div class="small text-muted">Penarikan Bank ke Kas Tunai</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="p-3 bg-white rounded border shadow-sm">
                <div class="text-muted small">Total Volume Mutasi</div>
                <div class="fw-bold fs-5 text-dark">Rp {{ number_format($totalTransferAmount, 2, ',', '.') }}</div>
                <div class="small text-muted">Periode Filter Terpilih</div>
            </div>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <form id="form-filter-kas-mutasi" method="GET" action="{{ route('akuntansi.kas-mutasi') }}" class="row g-2 align-items-end">

                <div class="col-md-3">
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

                <div class="col-md-3">
                    <label class="form-label mb-1 small fw-bold">Akun Kas/Bank Asal</label>
                    <select name="from_account_id" class="form-select form-select-sm">
                        <option value="">Semua Akun Asal</option>
                        @foreach($cashAccounts as $acc)
                            <option value="{{ $acc->id }}" {{ (string) $fromAccountId === (string) $acc->id ? 'selected' : '' }}>
                                [{{ $acc->code }}] {{ $acc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label mb-1 small fw-bold">Akun Kas/Bank Tujuan</label>
                    <select name="to_account_id" class="form-select form-select-sm">
                        <option value="">Semua Akun Tujuan</option>
                        @foreach($cashAccounts as $acc)
                            <option value="{{ $acc->id }}" {{ (string) $toAccountId === (string) $acc->id ? 'selected' : '' }}>
                                [{{ $acc->code }}] {{ $acc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3 d-flex gap-2">
                    <div class="flex-fill">
                        <label class="form-label mb-1 small fw-bold">Mulai</label>
                        <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
                    </div>
                    <div class="flex-fill">
                        <label class="form-label mb-1 small fw-bold">Sampai</label>
                        <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
                    </div>
                </div>

                <div class="col-md-12 d-flex justify-content-end gap-2 mt-2">
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-search me-1"></i> Tampilkan
                    </button>
                    <button type="button" id="btn-export-excel" class="btn btn-success btn-sm px-3">
                        <i class="bi bi-file-earmark-excel me-1"></i> Export Excel
                    </button>
                </div>

            </form>
        </div>
    </div>

    <!-- Table Data Mutasi -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.9rem;">
                    <thead class="table-dark">
                        <tr>
                            <th class="text-center" style="width: 100px;">Tanggal</th>
                            <th class="text-center" style="width: 150px;">No. Transaksi</th>
                            <th style="width: 140px;">Business Unit</th>
                            <th style="width: 200px;">Akun Asal (Sumber Uang)</th>
                            <th style="width: 200px;">Akun Tujuan (Penerima Uang)</th>
                            <th>Keterangan / Memo</th>
                            <th class="text-end" style="width: 160px;">Nominal (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transfers as $journal)
                            @php
                                $fromEntry = $journal->entries->where('credit', '>', 0)->first();
                                $toEntry   = $journal->entries->where('debit', '>', 0)->first();
                            @endphp
                            <tr>
                                <td class="text-center">{{ \Carbon\Carbon::parse($journal->journal_date)->format('d/m/Y') }}</td>
                                <td class="text-center font-monospace fw-semibold">{{ $journal->journal_no }}</td>
                                <td>{{ $journal->businessUnit?->name ?? '-' }}</td>
                                <td>
                                    <span class="text-danger fw-semibold">
                                        [{{ $fromEntry?->account?->code }}] {{ $fromEntry?->account?->name }}
                                    </span>
                                </td>
                                <td>
                                    <span class="text-success fw-semibold">
                                        [{{ $toEntry?->account?->code }}] {{ $toEntry?->account?->name }}
                                    </span>
                                </td>
                                <td>{{ $journal->description }}</td>
                                <td class="text-end fw-bold text-primary">
                                    Rp {{ number_format($toEntry?->debit ?? 0, 2, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="bi bi-inbox fs-3 d-block mb-1"></i>
                                    Tidak ada transaksi mutasi kas/bank pada periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="table-light border-top border-2">
                        <tr class="fw-bold">
                            <td colspan="6" class="text-end">TOTAL VOLUME MUTASI:</td>
                            <td class="text-end text-primary fs-6">Rp {{ number_format($totalTransferAmount, 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- Modal Input Mutasi Kas / Bank -->
<div class="modal fade" id="modal-tambah-mutasi" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title fw-bold"><i class="bi bi-arrow-left-right me-1"></i> Form Transfer / Mutasi Internal Kas & Bank</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('akuntansi.kas-mutasi.store') }}" method="POST">
                @csrf
                <div class="modal-body p-3">
                    <div class="row g-3">
                        
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Business Unit <span class="text-danger">*</span></label>
                            <select name="business_unit_id" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Business Unit --</option>
                                @foreach($businessUnits as $bu)
                                    <option value="{{ $bu->id }}">{{ $bu->code }} - {{ $bu->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Tanggal Transaksi <span class="text-danger">*</span></label>
                            <x-date-input-id name="journal_date" :value="date('Y-m-d')" required />
                        </div>

                        <!-- Sumber Uang (Kredit) -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Akun Asal (Pengirim / Kredit) <span class="text-danger">*</span></label>
                            <select name="from_account_id" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Kas/Bank Asal --</option>
                                @foreach($cashAccounts as $acc)
                                    <option value="{{ $acc->id }}">[{{ $acc->code }}] {{ $acc->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Tujuan Uang (Debit) -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Akun Tujuan (Penerima / Debit) <span class="text-danger">*</span></label>
                            <select name="to_account_id" class="form-select form-select-sm" required>
                                <option value="">-- Pilih Kas/Bank Tujuan --</option>
                                @foreach($cashAccounts as $acc)
                                    <option value="{{ $acc->id }}">[{{ $acc->code }}] {{ $acc->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Nominal Mutasi (Rp) <span class="text-danger">*</span></label>
                            <input type="number" name="amount" step="0.01" min="1" class="form-control form-control-sm fw-bold text-primary fs-6" placeholder="0.00" required>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Keterangan / Memo <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="Contoh: Setoran uang kasir ke Bank BCA / Pengisian Kas Kecil Toko..." required></textarea>
                        </div>

                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-save me-1"></i> Simpan & Posting Mutasi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('btn-export-excel')?.addEventListener('click', function () {
        const form     = document.getElementById('form-filter-kas-mutasi');
        const formData = new FormData(form);
        const params   = new URLSearchParams(formData).toString();

        window.location.href = "{{ route('akuntansi.kas-mutasi.export') }}?" + params;
    });
});
</script>
@endsection