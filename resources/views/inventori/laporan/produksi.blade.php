@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Laporan Produksi</h4>
        <div class="text-secondary small">Rekap SPK Unit Bisnis Produksi.</div>
    </div>
    <a href="{{ route('laporan.produksi.export', request()->query()) }}" class="btn btn-success">
        <i class="bi bi-file-earmark-excel me-1"></i>Export Excel
    </a>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form method="GET" action="{{ route('laporan.produksi') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Unit Bisnis</label>
                <input type="text" class="form-control" value="Produksi" readonly>
            </div>
            <div class="col-md-3">
                <label for="date_from" class="form-label">Dari Tanggal</label>
                <input id="date_from" type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
            </div>
            <div class="col-md-3">
                <label for="date_to" class="form-label">Sampai Tanggal</label>
                <input id="date_to" type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Tampilkan</button>
                <a href="{{ route('laporan.produksi') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Tgl</th><th>SPK</th><th>Target</th>
                    <th class="text-end">Estimasi Biaya</th>
                    <th class="text-end">Bahan Baku</th><th class="text-end">Upah</th>
                    <th class="text-end">Produksi</th><th class="text-end">HPP / Unit</th>
                    <th class="text-end">Reject</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ \\Carbon\\Carbon::parse($row->date)->format('d/m/Y') }}</td>
                        <td><div class="fw-semibold">{{ $row->wo_no }}</div><div class="text-secondary small">{{ $row->product_name }}</div></td>
                        <td>{{ \\App\\Helpers\\FormatHelper::indo((float) $row->target, 3) }}</td>
                        <td class="text-end">Rp {{ number_format((float) $row->estimated_cost, 2, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format((float) $row->material_cost, 2, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format((float) $row->labor_cost, 2, ',', '.') }}</td>
                        <td class="text-end">{{ \\App\\Helpers\\FormatHelper::indo((float) $row->production_qty, 3) }}</td>
                        <td class="text-end">@if ($row->hpp_unit !== null) Rp {{ number_format((float) $row->hpp_unit, 2, ',', '.') }} @else <span class="text-secondary">—</span> @endif</td>
                        <td class="text-end">{{ \\App\\Helpers\\FormatHelper::indo((float) $row->reject_qty, 3) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-secondary py-4">Tidak ada SPK pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="text-secondary small mt-2">Bahan baku dan upah menampilkan realisasi setelah posting; sebelum posting, angka menunjukkan estimasi. HPP per unit tampil setelah posting produksi.</div>
@endsection
