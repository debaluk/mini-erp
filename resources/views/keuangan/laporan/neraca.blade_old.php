@extends('layouts.app')
@section('content')
<div class="card shadow-sm"><div class="card-header fw-semibold">Neraca</div><div class="card-body"><div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>Uraian</th><th class="text-end">Jumlah (Rp)</th></tr></thead><tbody>@forelse($report['lines']??[] as $r)<tr><td>{{ $r['label']??$r['name']??'-' }}</td><td class="text-end">Rp {{ number_format($r['amount']??0,0,',','.') }}</td></tr>@empty<tr><td colspan="2" class="text-center text-secondary py-4">Belum ada data.</td></tr>@endforelse</tbody></table></div></div></div>
@endsection
