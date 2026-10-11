@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-start mb-3">
    <div><h4 class="mb-1">Detail Perhitungan Produksi</h4><div class="text-secondary small">Rincian informatif SPK</div></div>
    <a href="{{ route('laporan.produksi') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<div class="card border-0 shadow-sm mb-3"><div class="card-body"><div class="row g-3">
    <div class="col-md-4"><div class="text-secondary small">Nomor SPK</div><div class="fw-semibold">{{ $wo->wo_no }}</div></div>
    <div class="col-md-4"><div class="text-secondary small">Tanggal</div><div>{{ \Carbon\Carbon::parse($wo->wo_date)->format('d/m/Y') }}</div></div>
    <div class="col-md-4"><div class="text-secondary small">Produk</div><div>{{ $wo->product_name }}</div></div>
    <div class="col-md-4"><div class="text-secondary small">Gudang</div><div>{{ $wo->warehouse_name ?? '—' }}</div></div>
    <div class="col-md-4"><div class="text-secondary small">Target SPK</div><div>{{ \App\Helpers\FormatHelper::indo((float) $wo->target_output_qty, 0) }} pcs</div></div>
    <div class="col-md-4"><div class="text-secondary small">Status biaya</div><div><span class="badge {{ $production ? 'text-bg-success' : 'text-bg-light text-dark' }}">{{ $production ? 'Realisasi tersedia' : 'Estimasi SPK' }}</span></div></div>
</div></div></div>

<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold">1. Estimasi SPK</div><div class="table-responsive"><table class="table table-sm align-middle mb-0">
<thead class="table-light"><tr><th>Komponen</th><th>Kelompok</th><th class="text-end">Nilai</th></tr></thead><tbody>
<tr><td>Estimasi material (snapshot SPK)</td><td>Bahan</td><td class="text-end">Rp {{ number_format((float) $wo->estimated_material_cost, 2, ',', '.') }}</td></tr>
@foreach ($estimatedCosts as $cost)
<tr><td>{{ $cost->description ?: 'Biaya SPK' }}</td><td>{{ ['B' => 'Bahan', 'U' => 'Upah', 'A' => 'Alat', 'S' => 'Sewa', 'O' => 'Overhead'][$cost->cost_group] ?? $cost->cost_group }}</td><td class="text-end">Rp {{ number_format((float) $cost->amount, 2, ',', '.') }}</td></tr>
@endforeach
<tr class="table-light fw-semibold"><td colspan="2">Total biaya estimasi SPK</td><td class="text-end">Rp {{ number_format((float) $estimatedTotal, 2, ',', '.') }}</td></tr>
</tbody></table></div><div class="card-body text-secondary small">Estimasi hanya sebagai pembanding, bukan otomatis biaya realisasi.</div></div>

<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold">2. Pemakaian material aktual</div>
@if ($production)
<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead class="table-light"><tr><th>Bahan</th><th class="text-end">Qty aktual (satuan dasar)</th><th class="text-end">Biaya aktual</th></tr></thead><tbody>
@forelse ($materialLines as $line)
<tr><td>{{ $line->product_name }}</td><td class="text-end">{{ \App\Helpers\FormatHelper::indo((float) $line->actual_qty, 3) }}</td><td class="text-end">Rp {{ number_format((float) $line->total_cost, 2, ',', '.') }}</td></tr>
@empty<tr><td colspan="3" class="text-center text-secondary py-3">Belum ada rincian pemakaian material disetujui.</td></tr>@endforelse
<tr class="table-light fw-semibold"><td colspan="2">Total material aktual</td><td class="text-end">Rp {{ number_format((float) $actualMaterial, 2, ',', '.') }}</td></tr>
</tbody></table></div>
@else<div class="card-body text-secondary">Realisasi belum tersedia. Estimasi material: <strong>Rp {{ number_format((float) $wo->estimated_material_cost, 2, ',', '.') }}</strong>.</div>@endif
</div>

<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold">3. Upah</div>
@if ($production)
<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead class="table-light"><tr><th>Rincian</th><th class="text-end">Nilai aktual</th></tr></thead><tbody>
@forelse ($laborLines as $line)<tr><td>{{ $line->description ?? 'Upah produksi' }}</td><td class="text-end">Rp {{ number_format((float) $line->amount, 2, ',', '.') }}</td></tr>
@empty<tr><td colspan="2" class="text-center text-secondary py-3">Tidak ada rincian upah aktual pada transaksi produksi.</td></tr>@endforelse
<tr class="table-light fw-semibold"><td>Total upah aktual</td><td class="text-end">Rp {{ number_format((float) $actualLabor, 2, ',', '.') }}</td></tr>
</tbody></table></div>
@else<div class="card-body text-secondary">Realisasi belum tersedia. Estimasi upah: <strong>Rp {{ number_format((float) $estimatedLabor, 2, ',', '.') }}</strong>.</div>@endif
<div class="card-body text-secondary small">Upah ditampilkan sebagai informasi sesuai data SPK/realisasi.</div></div>

<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold">4. Hasil produksi dan reject</div><div class="card-body"><div class="row g-3">
<div class="col-md-3"><div class="text-secondary small">Target SPK</div><div class="fs-5 fw-semibold">{{ \App\Helpers\FormatHelper::indo((float) $wo->target_output_qty, 0) }} pcs</div></div>
<div class="col-md-3"><div class="text-secondary small">Hasil baik</div><div class="fs-5 fw-semibold text-success">{{ \App\Helpers\FormatHelper::indo((float) $goodQty, 0) }} pcs</div></div>
<div class="col-md-3"><div class="text-secondary small">Reject</div><div class="fs-5 fw-semibold text-danger">{{ \App\Helpers\FormatHelper::indo((float) $rejectQty, 0) }} pcs</div></div>
<div class="col-md-3"><div class="text-secondary small">HPP/unit (informasi)</div><div class="fs-5 fw-semibold">@if ($hppUnit !== null) Rp {{ number_format((float) $hppUnit, 2, ',', '.') }} @else — @endif</div></div>
</div></div></div>

<div class="card border-0 shadow-sm mb-3"><div class="card-header bg-white fw-semibold">5. Perbandingan biaya dan selisih informasi</div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><tbody>
<tr><td>Total biaya estimasi SPK</td><td class="text-end">Rp {{ number_format((float) $estimatedTotal, 2, ',', '.') }}</td></tr>
<tr><td>Material {{ $production ? 'aktual' : '(estimasi; realisasi belum tersedia)' }}</td><td class="text-end">Rp {{ number_format((float) $materialCost, 2, ',', '.') }}</td></tr>
<tr><td>Upah {{ $production ? 'aktual' : '(estimasi; realisasi belum tersedia)' }}</td><td class="text-end">Rp {{ number_format((float) $laborCost, 2, ',', '.') }}</td></tr>
<tr class="table-light fw-semibold"><td>Material + upah pembanding</td><td class="text-end">Rp {{ number_format((float) $comparisonTotal, 2, ',', '.') }}</td></tr>
<tr class="fw-semibold"><td>Selisih informasi (estimasi SPK − material + upah pembanding)</td><td class="text-end">Rp {{ number_format((float) $variance, 2, ',', '.') }}</td></tr>
</tbody></table></div><div class="card-body"><span class="badge text-bg-warning">Informasi — belum diakui sebagai biaya</span><div class="fw-semibold mt-3 mb-1">Analisis Selisih Biaya Produksi</div><p class="small text-secondary mb-2">Selisih antara estimasi dan realisasi biaya produksi merupakan indikator evaluasi pelaksanaan SPK. Nilai realisasi yang lebih tinggi dari estimasi dapat dipengaruhi oleh perubahan harga rata-rata bahan maupun peningkatan kuantitas pemakaian material.</p><p class="small text-secondary mb-2">Untuk memastikan penyebab selisih, periksa kuantitas bahan yang direncanakan dan aktual serta harga rata-rata yang digunakan. Selisih biaya tidak serta-merta menunjukkan pemborosan material, melainkan menjadi dasar evaluasi efisiensi produksi.</p><p class="small text-secondary mb-0">Informasi ini bersifat analitis dan tidak secara otomatis menimbulkan jurnal atau perubahan nilai HPP.</p></div></div>
@endsection
