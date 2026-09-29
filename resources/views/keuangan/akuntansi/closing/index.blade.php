@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-3">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div>
            <h4 class="fw-bold mb-0">Tutup Buku</h4>
            <p class="text-muted small mb-0">
                Pemeriksaan dan penguncian periode akuntansi bulanan
            </p>
        </div>

        <span class="badge bg-success px-3 py-2">
            <i class="bi bi-building me-1"></i>
            {{ $entity->name ?? 'Entity' }}
        </span>
    </div>

    {{-- Filter Periode --}}
    <div class="card shadow-sm border-0 mb-2">
        <div class="card-header bg-white border-0 py-2">
            <h6 class="fw-bold mb-0">
                <i class="bi bi-calendar3 me-2"></i>
                Pilih Periode
            </h6>
        </div>

        <div class="card-body py-2">
            <form id="form-closing-period" method="GET" class="row g-1 align-items-end">

                <div class="col-md-3">
                    <label class="form-label mb-1 small fw-bold">Tahun</label>
                    <select name="period_year"
                            id="period_year"
                            class="form-select form-select-sm">
                        @for($year = now()->year - 2; $year <= now()->year + 1; $year++)
                            <option value="{{ $year }}"
                                {{ (string) request('period_year', now()->year) === (string) $year ? 'selected' : '' }}>
                                {{ $year }}
                            </option>
                        @endfor
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label mb-1 small fw-bold">Bulan</label>

                    @php
                        $selectedMonth = (int) request('period_month', now()->month);
                    @endphp

                    <select name="period_month"
                            id="period_month"
                            class="form-select form-select-sm">

                        @for($month = 1; $month <= 12; $month++)
                            <option value="{{ $month }}"
                                {{ $selectedMonth === $month ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::create()->month($month)->translatedFormat('F') }}
                            </option>
                        @endfor

                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label mb-1 small fw-bold">Entity</label>

                    <input type="text"
                           class="form-control form-control-sm"
                           value="{{ $entity->name ?? '-' }}"
                           readonly>
                </div>

                <div class="col-md-3">
                    <button type="button"
                            id="btn-check-period"
                            class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-search me-1"></i>
                        Periksa Periode
                    </button>
                </div>

            </form>
        </div>
    </div>

    {{-- Status Periode --}}
    <div class="card shadow-sm border-0 mb-2">
        <div class="card-body py-2">

            <div class="row align-items-center">

                <div class="col-md-8">
                    <div class="text-muted small">
                        Periode yang diperiksa
                    </div>

                    <h5 class="fw-bold mb-0" id="period-label">
                        {{ \Carbon\Carbon::create(
                            (int) request('period_year', now()->year),
                            (int) request('period_month', now()->month),
                            1
                        )->translatedFormat('F Y') }}
                    </h5>

                    <small class="text-muted">
                        Monthly Closing — Entity Level
                    </small>
                </div>

                <div class="col-md-4 text-md-end mt-1 mt-md-0">

                    <div class="text-muted small">
                        Status Periode
                    </div>

                    <span id="period-status"
                          class="badge bg-secondary px-3 py-2">
                        <i class="bi bi-question-circle me-1"></i>
                        Belum Diperiksa
                    </span>

                </div>

            </div>

        </div>
    </div>

    {{-- Pemeriksaan Closing --}}
    <div class="card shadow-sm border-0 mb-2">

        <div class="card-header bg-white border-0 py-2">

            <div class="d-flex justify-content-between align-items-center">

                <div>
                    <h6 class="fw-bold mb-0">
                        <i class="bi bi-clipboard-check me-2"></i>
                        Pemeriksaan Closing
                    </h6>

                    <small class="text-muted">
                        Pastikan seluruh pemeriksaan selesai sebelum periode dikunci.
                    </small>
                </div>

                <span id="progress-text"
                      class="fw-bold text-primary">
                    0 / 12
                </span>

            </div>

        </div>

        <div class="card-body py-2">

            {{-- Progress --}}
            <div class="progress mb-2" style="height: 6px;">
                <div id="closing-progress"
                     class="progress-bar"
                     role="progressbar"
                     style="width: 0%;">
                </div>
            </div>

            {{-- Checklist --}}
            <div class="row g-1">

                @php
                    $checks = [
                        [
                            'id' => 'check-journal',
                            'label' => 'Jurnal periode sudah posted',
                            'icon' => 'bi-journal-text'
                        ],
                        [
                            'id' => 'check-balance',
                            'label' => 'Total Debit = Total Credit',
                            'icon' => 'bi-calculator'
                        ],
                        [
                            'id' => 'check-sales',
                            'label' => 'Penjualan periode sudah selesai',
                            'icon' => 'bi-cart-check'
                        ],
                        [
                            'id' => 'check-sales-return',
                            'label' => 'Retur Penjualan sudah selesai',
                            'icon' => 'bi-arrow-return-left'
                        ],
                        [
                            'id' => 'check-purchase',
                            'label' => 'Pembelian periode sudah selesai',
                            'icon' => 'bi-bag-check'
                        ],
                        [
                            'id' => 'check-purchase-return',
                            'label' => 'Retur Pembelian sudah selesai',
                            'icon' => 'bi-arrow-return-right'
                        ],
                        [
                            'id' => 'check-inventory',
                            'label' => 'Persediaan / Stock Movement konsisten',
                            'icon' => 'bi-box-seam'
                        ],
                        [
                            'id' => 'check-hpp',
                            'label' => 'HPP periode sudah terbentuk',
                            'icon' => 'bi-graph-up-arrow'
                        ],
                        [
                            'id' => 'check-receivable',
                            'label' => 'Piutang konsisten dengan jurnal',
                            'icon' => 'bi-person-lines-fill'
                        ],
                        [
                            'id' => 'check-payable',
                            'label' => 'Hutang konsisten dengan jurnal',
                            'icon' => 'bi-credit-card'
                        ],
                        [
                            'id' => 'check-cashbank',
                            'label' => 'Kas & Bank periode sudah selesai',
                            'icon' => 'bi-bank'
                        ],
                        [
                            'id' => 'check-reports',
                            'label' => 'Laporan Akuntansi konsisten',
                            'icon' => 'bi-file-earmark-bar-graph'
                        ],
                    ];
                @endphp

                @foreach($checks as $check)

                    <div class="col-md-6">

                        <div class="border rounded px-2 py-1 closing-check h-100"
                             data-check="{{ $check['id'] }}">

                            <div class="d-flex align-items-center">

                                <div class="me-2">
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                                         style="width:30px;height:30px;">

                                        <i class="bi {{ $check['icon'] }} text-secondary"></i>

                                    </div>
                                </div>

                                <div class="flex-grow-1">

                                    <div class="fw-semibold small">
                                        {{ $check['label'] }}
                                    </div>

                                    <small class="check-message text-muted">
                                        Belum diperiksa
                                    </small>

                                </div>

                                <div class="check-status ms-2">

                                    <span class="badge bg-light text-muted border">
                                        <i class="bi bi-dash-circle me-1"></i>
                                        Belum
                                    </span>

                                </div>

                            </div>

                        </div>

                    </div>

                @endforeach

            </div>

        </div>

    </div>

    {{-- Laporan Dasar Closing --}}
    <div class="card shadow-sm border-0 mb-2">

        <div class="card-header bg-white border-0 py-2">

            <h6 class="fw-bold mb-0">
                <i class="bi bi-file-earmark-bar-graph me-2"></i>
                Laporan Dasar Closing
            </h6>

        </div>

        <div class="card-body py-1">

            <div class="row g-1">

                @php
                    $reports = [
                        [
                            'title' => 'Jurnal Umum',
                            'icon' => 'bi-journal-text'
                        ],
                        [
                            'title' => 'Buku Besar',
                            'icon' => 'bi-book'
                        ],
                        [
                            'title' => 'Trial Balance',
                            'icon' => 'bi-calculator'
                        ],
                        [
                            'title' => 'Profit & Loss',
                            'icon' => 'bi-graph-up'
                        ],
                        [
                            'title' => 'Balance Sheet',
                            'icon' => 'bi-bank'
                        ],
                    ];
                @endphp

                @foreach($reports as $report)

                    <div class="col">

                        <div class="border rounded px-2 py-1 h-100">

                            <div class="d-flex align-items-center">

                                <i class="bi {{ $report['icon'] }} text-primary me-2"></i>

                                <div>
                                    <div class="fw-semibold small">
                                        {{ $report['title'] }}
                                    </div>

                                    <small class="text-muted">
                                        Dasar closing
                                    </small>
                                </div>

                            </div>

                        </div>

                    </div>

                @endforeach

            </div>

        </div>

    </div>

    {{-- Final Closing --}}
    <div class="card shadow-sm border-0">

        <div class="card-body py-2">

            <div class="alert alert-warning d-flex align-items-start py-2 mb-2">

                <i class="bi bi-exclamation-triangle-fill me-2 mt-1"></i>

                <div class="small">
                    <strong>Perhatian:</strong>
                    Setelah periode ditutup, transaksi pada periode tersebut
                    tidak boleh dibuat, diubah, dihapus, atau diposting kembali.
                </div>

            </div>

            <div class="d-flex justify-content-between align-items-center">

                <div>
                    <div class="fw-bold">
                        Finalisasi Periode
                    </div>

                    <small class="text-muted">
                        Tutup periode setelah seluruh pemeriksaan dinyatakan lolos.
                    </small>
                </div>

                <button type="button"
                        id="btn-close-period"
                        class="btn btn-danger btn-sm px-4"
                        disabled>

                    <i class="bi bi-lock-fill me-1"></i>
                    Tutup Buku

                </button>

            </div>

        </div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const btnCheck = document.getElementById('btn-check-period');
    const btnClose = document.getElementById('btn-close-period');
    const progressBar = document.getElementById('closing-progress');
    const progressText = document.getElementById('progress-text');
    const periodStatus = document.getElementById('period-status');
    const periodLabel = document.getElementById('period-label');

    const checks = document.querySelectorAll('.closing-check');
    const totalChecks = checks.length;

    btnCheck.addEventListener('click', async function () {

        const year = document.getElementById('period_year').value;
        const month = document.getElementById('period_month').value;

        const date = new Date(year, month - 1, 1);

        periodLabel.textContent = date.toLocaleDateString('id-ID', {
            month: 'long',
            year: 'numeric'
        });

        periodStatus.className =
            'badge bg-warning text-dark px-3 py-2';

        periodStatus.innerHTML =
            '<i class="bi bi-arrow-repeat me-1"></i> Sedang Diperiksa';

        btnCheck.disabled = true;
        btnClose.disabled = true;

        progressBar.style.width = '0%';
        progressText.textContent = '0 / ' + totalChecks;

        checks.forEach(function (item) {
            const status = item.querySelector('.check-status');
            const message = item.querySelector('.check-message');

            status.innerHTML =
                '<span class="badge bg-secondary">Menunggu</span>';

            message.textContent =
                'Menunggu pemeriksaan backend';
        });

        try {

            const response = await fetch(
                '{{ route("akuntansi.closing-periode.check") }}' +
                '?period_year=' + encodeURIComponent(year) +
                '&period_month=' + encodeURIComponent(month),
                {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }
            );

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const result = await response.json();

            if (!result.success) {
                throw new Error('Pemeriksaan gagal');
            }

            let completed = 0;

            result.checks.forEach(function (check) {

                 const item = document.querySelector(
						'.closing-check[data-check="' + check.id + '"]'
					);

                if (!item) {
                    return;
                }

                const status = item.querySelector('.check-status');
                const message = item.querySelector('.check-message');

                if (check.passed) {

                    status.innerHTML =
                        '<span class="badge bg-success">' +
                        '<i class="bi bi-check-circle me-1"></i> Lulus' +
                        '</span>';

                } else {

                    status.innerHTML =
                        '<span class="badge bg-danger">' +
                        '<i class="bi bi-x-circle me-1"></i> Belum Lulus' +
                        '</span>';
                }

                message.textContent = check.message;

                completed++;

                const percentage =
                    Math.round((completed / totalChecks) * 100);

                progressBar.style.width =
                    percentage + '%';

                progressText.textContent =
                    completed + ' / ' + totalChecks;
            });

            if (result.summary.ready_to_close) {

                periodStatus.className =
                    'badge bg-success px-3 py-2';

                periodStatus.innerHTML =
                    '<i class="bi bi-check-circle-fill me-1"></i>' +
                    ' Siap Ditutup';

                btnClose.disabled = false;

            } else {

                periodStatus.className =
                    'badge bg-danger px-3 py-2';

                periodStatus.innerHTML =
                    '<i class="bi bi-exclamation-circle-fill me-1"></i>' +
                    ' Belum Siap Ditutup';
            }

        } catch (error) {

            console.error(error);

            periodStatus.className =
                'badge bg-danger px-3 py-2';

            periodStatus.innerHTML =
                '<i class="bi bi-x-circle-fill me-1"></i>' +
                ' Pemeriksaan Gagal';

            checks.forEach(function (item) {

                const status = item.querySelector('.check-status');
                const message = item.querySelector('.check-message');

                status.innerHTML =
                    '<span class="badge bg-danger">' +
                    '<i class="bi bi-x-circle me-1"></i> Error' +
                    '</span>';

                message.textContent =
                    'Tidak dapat mengambil data pemeriksaan';
            });

        } finally {

            btnCheck.disabled = false;
        }
    });

});
</script>


@endsection
