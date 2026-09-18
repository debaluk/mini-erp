@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div><h3 class="mb-1">Entitas</h3><div class="text-secondary">Profil Entitas / Unit Bisnis</div></div>
    @if(auth()->user()->role === 'superadmin')
    <form method="GET" class="d-flex align-items-end gap-2">
        <div><label class="form-label">Pilih Entitas</label><select name="entity_id" class="form-select" onchange="this.form.submit()">@foreach($entities as $e)<option value="{{ $e->id }}" @selected($entity->id === $e->id)>{{ $e->code }} · {{ $e->name }}</option>@endforeach</select></div>
    </form>
    @endif
</div>

@if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger py-2"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="card shadow-sm">
    <div class="card-header fw-semibold">Data Entitas</div>
    <div class="card-body">
        <form method="POST" action="{{ route('pengaturan.entitas.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @if(auth()->user()->role === 'superadmin')<input type="hidden" name="entity_id" value="{{ $entity->id }}">@endif
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Logo</label>
                    <div class="border rounded p-2 text-center mb-2" style="height:120px;">
                        @if($entity->logo_path)<img src="{{ asset('storage/'.$entity->logo_path) }}" alt="Logo" style="max-width:100%;max-height:100px;">@else<span class="text-secondary small">Belum ada logo</span>@endif
                    </div>
                    <input type="file" name="logo" class="form-control" accept="image/*">
                    <div class="small text-secondary mt-1">Maks. 2 MB.</div>
                </div>
                <div class="col-md-9">
                    <div class="row g-3">
                        <div class="col-md-3"><label class="form-label">Kode Entitas</label><input class="form-control" value="{{ $entity->code }}" readonly></div>
                        <div class="col-md-9"><label class="form-label">Nama Entitas</label><input name="name" class="form-control" value="{{ old('name',$entity->name) }}" required></div>
                        <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email',$entity->email) }}"></div>
                        <div class="col-md-6"><label class="form-label">Telepon</label><input name="phone" class="form-control" value="{{ old('phone',$entity->phone) }}"></div>
                        <div class="col-md-6"><label class="form-label">NPWP</label><input name="npwp" class="form-control" value="{{ old('npwp',$entity->npwp) }}"></div>
                        <div class="col-md-6"><label class="form-label">NIB</label><input name="nib" class="form-control" value="{{ old('nib',$entity->nib) }}"></div>
                        <div class="col-12"><label class="form-label">Alamat</label><textarea name="address" class="form-control" rows="3">{{ old('address',$entity->address) }}</textarea></div>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-end mt-3"><button class="btn btn-primary">Simpan Perubahan</button></div>
        </form>
    </div>
</div>
@endsection
