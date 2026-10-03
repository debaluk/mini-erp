<!doctype html>
<html lang="id"><head><meta charset="utf-8"><title>{{ $wo->wo_no }} - SPK</title>
<style>
@page{size:A4;margin:14mm}body{font-family:Arial,sans-serif;font-size:12px;color:#111}.header{display:flex;justify-content:space-between;border-bottom:2px solid #111;padding-bottom:10px;margin-bottom:16px}.title{font-size:20px;font-weight:700}.no{font-size:18px;font-weight:700;text-align:right}.grid{display:grid;grid-template-columns:130px 1fr 130px 1fr;gap:7px 10px;margin-bottom:18px}.label{font-weight:700}.box{border:1px solid #aaa;padding:10px;margin-bottom:16px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #888;padding:7px}th{background:#eee;text-align:left}.sign{display:grid;grid-template-columns:repeat(3,1fr);gap:30px;margin-top:55px;text-align:center}.sign div{min-height:70px}@media print{.no-print{display:none}}
</style></head><body>
<div class="no-print" style="text-align:right;margin-bottom:10px"><button onclick="window.print()">Cetak</button></div>
<div class="header"><div><div class="title">{{ $entity->name ?? 'Perusahaan' }}</div><div>{{ $entity->address ?? '' }}</div></div><div><div class="title">WORK ORDER / SPK</div><div class="no">{{ $wo->wo_no }}</div></div></div>
<div class="grid">
<div class="label">Tanggal SPK</div><div>{{ \Carbon\Carbon::parse($wo->wo_date)->format('d/m/Y') }}</div><div class="label">Status</div><div>{{ strtoupper($wo->status) }}</div>
<div class="label">Produk</div><div>{{ $wo->product_code }} — {{ $wo->product_name }}</div><div class="label">BOM / Formula</div><div>{{ $wo->bom_code }} — {{ $wo->bom_name }}</div>
<div class="label">Gudang</div><div>{{ $wo->warehouse_code }} — {{ $wo->warehouse_name }}</div><div class="label">Jumlah Batch</div><div>{{ \App\Helpers\FormatHelper::indo($wo->batch_qty, 3) }}</div>
<div class="label">Target Output</div><div>{{ \App\Helpers\FormatHelper::indo($wo->target_output_qty, 3) }}</div>
</div>
<div class="box"><h3>Estimasi Biaya Produksi</h3>
<table style="margin-top:8px">
<tr><td>Material</td><td style="text-align:right">Rp {{ \App\Helpers\FormatHelper::indo($materialCost, 2) }}</td></tr>
<tr><td>Tenaga + Equipment + Rent + Overhead</td><td style="text-align:right">Rp {{ \App\Helpers\FormatHelper::indo($costs->sum('amount'), 2) }}</td></tr>
<tr><th>Total Estimasi Biaya WO/SPK</th><th style="text-align:right">Rp {{ \App\Helpers\FormatHelper::indo($totalEstimatedCost, 2) }}</th></tr>
</table></div>
<div class="box"><h3>Tenaga Kerja / Pekerja</h3><table style="margin-top:8px"><thead><tr><th>No.</th><th>Kode</th><th>Nama Pekerja</th><th>Peran / Pekerjaan</th></tr></thead><tbody>
@foreach($workers as $i=>$worker)<tr><td>{{ $i+1 }}</td><td>{{ $worker->code }}</td><td>{{ $worker->name }}</td><td>{{ $worker->role ?: '-' }}</td></tr>@endforeach
</tbody></table></div>
<div class="box"><h3>Instruksi / Catatan Produksi</h3><div style="min-height:80px;margin-top:8px">{!! nl2br(e($wo->notes ?: '-')) !!}</div></div>
<div class="box"><h3>Ketentuan Penyelesaian</h3><div style="margin-top:8px">Hasil produksi dicatat sebagai <strong>Good</strong> dan <strong>Reject</strong>. Biaya aktual mengikuti BUASO. Biaya Upah (U) diakui pada saat penyelesaian produksi.</div></div>
<div class="sign"><div>Dibuat Oleh<br><br><br><strong>____________________</strong></div><div>Pelaksana Produksi<br><br><br><strong>____________________</strong></div><div>Diperiksa / Disetujui<br><br><br><strong>____________________</strong></div></div>
</body></html>