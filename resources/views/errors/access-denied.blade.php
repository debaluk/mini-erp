@extends('layouts.app')

@section('content')
<div class="container py-5">
    <div class="card shadow-sm border-0">
        <div class="card-body text-center py-5">
            <h3 class="mb-3">Maaf, Anda tidak memiliki hak akses.</h3>
            <p class="text-secondary mb-4">Anda tidak memiliki izin untuk membuka halaman atau modul ini.</p>
            <a href="{{ route('dashboard') }}" class="btn btn-primary">Kembali ke Dashboard</a>
        </div>
    </div>
</div>
@endsection
