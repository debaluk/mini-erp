@extends('layouts.app')

@section('content')
<div class="container-fluid px-0 py-0">
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1 fw-bold text-dark">Histori Item</h3>
        <div class="text-secondary small">{{ $stock->code ?: $stock->sku ?: '-' }} — {{ $stock->product_name }}</div>
    </div>
    <a href="{{ route('inventori.setok-persediaan.index', request()->except(['start_date', 'end_date'])) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>
<div class="card shadow-sm border-0">
    
    <div class="card-body border-bottom">
        <div class="row g-2 mb-3">
            <div class="col-md-3"><div class="small text-secondary">Satuan Dasar</div><div class="fw-semibold">{{ $stock->unit_name ?: $stock->unit_code ?: '-' }}</div></div>
            <div class="col-md-3"><div class="small text-secondary">Unit Bisnis</div><div class="fw-semibold">{{ $stock->business_unit_names }}</div></div>
            <div class="col-md-3"><div class="small text-secondary">Gudang</div><div class="fw-semibold">{{ $stock->warehouse_name }}</div></div>
            <div class="col-md-3"><div class="small text-secondary">Stok Saat Ini</div><div class="fw-semibold">{{ rtrim(rtrim(number_format((float) $stock->qty, 3, ',', '.'), '0'), ',') }}</div></div>
        </div>
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label mb-1 small fw-bold">Tanggal Awal</label><input type="date" name="start_date" value="{{ $startDate }}" class="form-control form-control-sm" required></div>
            <div class="col-md-3"><label class="form-label mb-1 small fw-bold">Tanggal Akhir</label><input type="date" name="end_date" value="{{ $endDate }}" class="form-control form-control-sm" required></div>
            <div class="col-md-2"><button class="btn btn-primary btn-sm w-100">Tampilkan</button></div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.88rem;">
            <thead class="table-dark"><tr><th>Tanggal</th><th>Referensi</th><th>Keterangan</th><th class="text-end">Stok Masuk</th><th class="text-end">Stok Keluar</th><th class="text-end">Saldo Berjalan</th><th class="text-end">HPP per Unit</th></tr></thead>
            <tbody>
                <tr class="table-light fw-semibold"><td colspan="5">Saldo Awal sebelum {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}</td><td class="text-end">{{ rtrim(rtrim(number_format($openingBalance, 3, ',', '.'), '0'), ',') }}</td><td></td></tr>
                @forelse($history as $movement)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($movement->date)->format('d/m/Y H:i') }}</td>
                        <td>{{ $movement->reference }}</td>
                        <td>{{ $movement->description }}</td>
                        <td class="text-end">{{ $movement->in ? rtrim(rtrim(number_format($movement->in, 3, ',', '.'), '0'), ',') : '-' }}</td>
                        <td class="text-end">{{ $movement->out ? rtrim(rtrim(number_format($movement->out, 3, ',', '.'), '0'), ',') : '-' }}</td>
                        <td class="text-end fw-semibold">{{ rtrim(rtrim(number_format($movement->balance, 3, ',', '.'), '0'), ',') }}</td>
                        <td class="text-end">{{ $movement->unit_cost !== null ? 'Rp '.number_format((float) $movement->unit_cost, 0, ',', '.') : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-secondary py-4">Tidak ada pergerakan stok pada periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-secondary">Histori ini hanya untuk melihat pergerakan stok. Kartu Stok pada menu Laporan tetap terpisah dan tidak diubah.</div>
</div>
</div>
@endsection
