@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Pemakaian Bahan Baku</h4>
        <div class="text-secondary small">{{ $usage->usage_no }}</div>
    </div>
    <a href="{{ route('produksi.pemakaian-bahan') }}" class="btn btn-outline-secondary">Kembali</a>
</div>

@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Informasi Pemakaian</span>
        @php
            $labels = ['draft'=>'Draft','pending'=>'Menunggu Approval','approved'=>'Disetujui','rejected'=>'Ditolak'];
            $badges = ['draft'=>'secondary','pending'=>'warning','approved'=>'success','rejected'=>'danger'];
        @endphp
        <span class="badge text-bg-{{ $badges[$usage->status] ?? 'secondary' }}">{{ $labels[$usage->status] ?? $usage->status }}</span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3"><div class="text-secondary small">No. Pengeluaran</div><div class="fw-semibold">{{ $usage->usage_no }}</div></div>
            <div class="col-md-3"><div class="text-secondary small">Tanggal</div><div>{{ CarbonCarbon::parse($usage->usage_date)->format('d/m/Y') }}</div></div>
            <div class="col-md-3"><div class="text-secondary small">No. SPK</div><div>{{ $usage->wo_no }}</div></div>
            <div class="col-md-3"><div class="text-secondary small">Produk</div><div>{{ $usage->product_name }}</div></div>
            <div class="col-md-3"><div class="text-secondary small">BOM</div><div>{{ $usage->bom_code }}</div></div>
            <div class="col-md-3"><div class="text-secondary small">Gudang</div><div>{{ $usage->warehouse_name }}</div></div>
            <div class="col-md-3"><div class="text-secondary small">Dibuat Oleh</div><div>{{ $usage->creator_name ?: '-' }}</div></div>
            <div class="col-md-3"><div class="text-secondary small">Disetujui Oleh</div><div>{{ $usage->approver_name ?: '-' }}</div></div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header fw-semibold">Rencana & Aktual Pemakaian</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>SKU</th>
                        <th>Bahan</th>
                        <th class="text-end">Rencana</th>
                        <th>Satuan</th>
                        <th class="text-end">Aktual Dipakai</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($items as $item)
                    <tr>
                        <td>{{ $item->sku }}</td>
                        <td>{{ $item->product_name }}</td>
                        <td class="text-end">{{ AppHelpersFormatHelper::indo((float) $item->planned_qty, 3) }}</td>
                        <td>{{ $item->unit_code ?: $item->unit_name }}</td>
                        <td class="text-end">{{ AppHelpersFormatHelper::indo((float) $item->actual_qty, 3) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($usage->notes)
<div class="card border-0 shadow-sm mb-3"><div class="card-body"><div class="text-secondary small mb-1">Catatan</div>{{ $usage->notes }}</div></div>
@endif

<div class="d-flex justify-content-end gap-2">
    @if($usage->status === 'draft')
        <form method="POST" action="{{ route('produksi.pemakaian-bahan.submit', $usage->id) }}">
            @csrf
            <button class="btn btn-primary" onclick="return confirm('Ajukan pemakaian bahan untuk approval?')">Ajukan Approval</button>
        </form>
    @elseif($usage->status === 'pending')
        <form method="POST" action="{{ route('produksi.pemakaian-bahan.reject', $usage->id) }}">
            @csrf
            <button class="btn btn-outline-danger" onclick="return confirm('Tolak pemakaian bahan ini?')">Tolak</button>
        </form>
        <form method="POST" action="{{ route('produksi.pemakaian-bahan.approve', $usage->id) }}">
            @csrf
            <button class="btn btn-success" onclick="return confirm('Setujui? Setelah disetujui stok akan berkurang dan beban bahan baku diakui.')">Setujui</button>
        </form>
    @elseif($usage->status === 'approved')
        <span class="text-success align-self-center small">Beban bahan baku sudah diakui.</span>
    @endif
</div>
@endsection
