@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-1">Setup Mapping Account</h3>
        <div class="text-secondary">Mapping akun mengikuti Unit Bisnis dan Tipe Usaha.</div>
    </div>
    <a href="{{ route('pengaturan.konfigurasi') }}" class="btn btn-outline-secondary">Kembali</a>
</div>

@if(session('success')) <div class="alert alert-success py-2">{{ session('success') }}</div> @endif
@if($errors->any())
<div class="alert alert-danger py-2"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

@forelse($units as $unit)
@php
$labels = match($unit->business_type) {
 'retail' => ['hpp_merchandise'=>'HPP Barang Dagangan'],
 'production' => ['hpp_finished_goods'=>'HPP Hasil Produksi','direct_material'=>'Bahan Baku Langsung','direct_labor'=>'Beban Langsung Tenaga Kerja','direct_freight'=>'Beban Langsung Jasa Angkut'],
 'service' => ['direct_labor'=>'Beban Langsung Tenaga Kerja','direct_material'=>'Bahan Langsung','direct_other'=>'Beban Langsung Lainnya'],
};
@endphp
<div class="card shadow-sm mb-3">
 <div class="card-header"><strong>{{ $unit->code }} — {{ $unit->name }}</strong> <span class="badge text-bg-secondary ms-2">{{ ['retail'=>'Retail','production'=>'Produksi','service'=>'Jasa'][$unit->business_type] }}</span></div>
 <div class="card-body">
  <form method="POST" action="{{ route('pengaturan.account-mapping.save', $unit->id) }}">
   @csrf
   <div class="row g-3">
    @foreach($labels as $key=>$label)
    @php $current=$mappings[$unit->id.':'.$key]->account_id ?? null; @endphp
    <div class="col-md-6">
     <label class="form-label">{{ $label }} *</label>
     <select name="accounts[{{ $key }}]" class="form-select" required>
      <option value="">Pilih akun</option>
      @foreach($accounts as $account)
      <option value="{{ $account->id }}" @selected((string)$current === (string)$account->id)>{{ $account->code }} — {{ $account->name }}</option>
      @endforeach
     </select>
    </div>
    @endforeach
   </div>
   <button class="btn btn-primary mt-3">Simpan Mapping</button>
  </form>
 </div>
</div>
@empty
<div class="alert alert-light border">Belum ada Unit Bisnis. Buat Unit Bisnis terlebih dahulu.</div>
@endforelse

<div class="alert alert-light border small"><strong>Catatan:</strong> Mapping tersimpan per Unit Bisnis. Tipe Usaha menentukan mapping yang tersedia.</div>
@endsection