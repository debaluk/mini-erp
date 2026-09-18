@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4"><div><h3 class="mb-1">Role & Hak Akses</h3><div class="text-secondary">Struktur kewenangan Mini ERP</div></div></div>
<div class="row g-3 mb-3">@foreach($roles as $role)<div class="col-md-6 col-xl-4"><div class="card shadow-sm h-100"><div class="card-header fw-semibold">{{ $role['name'] }} <span class="badge text-bg-light float-end">{{ $role['scope'] }}</span></div><div class="card-body"><p class="small text-secondary mb-0">{{ $role['description'] }}</p></div></div></div>@endforeach</div>
<div class="card shadow-sm"><div class="card-header fw-semibold">Matriks Hak Akses</div><div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-0"><thead class="table-light"><tr><th>Hak Akses</th>@foreach($roles as $role)<th class="text-center">{{ $role['name'] }}</th>@endforeach</tr></thead><tbody>@foreach($permissions as $permission)<tr><td>{{ $permission }}</td>@foreach($roles as $role)<td class="text-center">{!! $matrix[$role['code']][$permission] ? '<span class="text-success fw-bold">✓</span>' : '<span class="text-secondary">—</span>' !!}</td>@endforeach</tr>@endforeach</tbody></table></div></div>
<div class="alert alert-info mt-3 mb-0">Hak akses Owner berada pada level entitas. Superadmin berada pada level sistem dan menjadi pengendali hak akses Owner. Role operasional tetap: Admin, Kasir, Inventori, Akuntansi.</div>
@endsection
