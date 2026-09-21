@extends('layouts.app')

@section('content')
<div class="mb-4">
    <h3 class="mb-1">Konfigurasi</h3>
    <div class="text-secondary">Pengaturan dasar cara kerja sistem ERP.</div>
</div>

<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#setup-unit" type="button" role="tab">
            Setup Unit
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#setup-akun" type="button" role="tab">
            Setup Akun
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#nota-invoice" type="button" role="tab">
            Setup Nota/Invoice
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#nomor-dokumen" type="button" role="tab">
            Nomor Dokumen
        </button>
    </li>
</ul>

@if(session('success'))
    <div class="alert alert-success py-2">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger py-2">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="tab-content">
    <div class="tab-pane fade show active" id="setup-unit" role="tabpanel">
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold">Setup Unit Bisnis</div>
            <div class="card-body">
                <form method="POST" action="{{ $editUnit ? route('pengaturan.unit-bisnis.update', $editUnit->id) : route('pengaturan.unit-bisnis.store') }}">
                    @csrf
                    @if($editUnit) @method('PUT') @endif

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="row align-items-center">
                                <label class="col-sm-4 col-form-label">Kode Unit *</label>
                                <div class="col-sm-8">
                                    <input type="text" name="code" class="form-control" value="{{ old('code', $editUnit->code ?? '') }}" required>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="row align-items-center">
                                <label class="col-sm-4 col-form-label">Nama Unit *</label>
                                <div class="col-sm-8">
                                    <input type="text" name="name" class="form-control" value="{{ old('name', $editUnit->name ?? '') }}" required>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="row align-items-center">
                                <label class="col-sm-4 col-form-label">Tipe Usaha *</label>
                                <div class="col-sm-8">
                                    <select name="business_type" class="form-select" required>
                                        <option value="">Pilih</option>
                                        <option value="retail" @selected(old('business_type', $editUnit->business_type ?? '') === 'retail')>Retail</option>
                                        <option value="production" @selected(old('business_type', $editUnit->business_type ?? '') === 'production')>Produksi</option>
                                        <option value="service" @selected(old('business_type', $editUnit->business_type ?? '') === 'service')>Jasa</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="row align-items-center">
                                <label class="col-sm-4 col-form-label">Metode HPP *</label>
                                <div class="col-sm-8">
                                    <select name="hpp_method" class="form-select" required>
                                        <option value="">Pilih</option>
                                        <option value="perpetual" @selected(old('hpp_method', $editUnit->hpp_method ?? '') === 'perpetual')>Perpetual</option>
                                        <option value="periodic" @selected(old('hpp_method', $editUnit->hpp_method ?? '') === 'periodic')>Periodik</option>
                                        <option value="direct_cost" @selected(old('hpp_method', $editUnit->hpp_method ?? '') === 'direct_cost')>Direct Cost</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="row align-items-center">
                                <label class="col-sm-4 col-form-label">Status</label>
                                <div class="col-sm-8">
                                    <div class="form-check">
                                        <input type="hidden" name="is_active" value="0">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="unit-active" @checked(old('is_active', $editUnit->is_active ?? true))>
                                        <label class="form-check-label" for="unit-active">Aktif</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 pt-2">
                            <button type="submit" class="btn btn-primary">{{ $editUnit ? 'Update' : 'Simpan' }}</button>
                            @if($editUnit)<a href="{{ route('pengaturan.konfigurasi') }}" class="btn btn-secondary">Batal</a>@endif
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold">Daftar Unit Bisnis</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Kode Unit</th>
                                <th>Nama Unit</th>
                                <th>Tipe Usaha</th>
                                <th>Metode HPP</th>
                                <th>Status</th>
                                <th style="width: 150px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($units as $unit)
                                <tr>
                                    <td>{{ $unit->code }}</td>
                                    <td>{{ $unit->name }}</td>
                                    <td>{{ ['retail' => 'Retail', 'production' => 'Produksi', 'service' => 'Jasa'][$unit->business_type] }}</td>
                                    <td>{{ $unit->hpp_method === 'perpetual' ? 'Perpetual' : 'Periodik' }}</td>
                                    <td>
                                        <span class="badge {{ $unit->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                            {{ $unit->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-edit-unit"
                                            data-url="{{ route('pengaturan.unit-bisnis.edit', $unit->id) }}">Edit</button>
                                        <form method="POST" action="{{ route('pengaturan.unit-bisnis.destroy', $unit->id) }}" class="d-inline" onsubmit="return confirm('Hapus unit bisnis ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-secondary py-4">Belum ada unit bisnis.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Keterangan Metode HPP</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <div class="fw-semibold mb-1">PERPETUAL — MOVING AVERAGE</div>
                            <div class="text-secondary small">
                                HPP dihitung dan diperbarui secara berjalan setiap terjadi transaksi persediaan
                                yang memengaruhi nilai stok. Metode yang digunakan adalah Moving Average
                                (rata-rata bergerak). Penerimaan/pembelian membentuk rata-rata biaya persediaan
                                yang menjadi dasar HPP saat barang dijual atau digunakan.
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <div class="fw-semibold mb-1">PERIODIK</div>
                            <div class="text-secondary small">
                                HPP dihitung pada akhir periode berdasarkan:
                                <strong>Persediaan Awal + Pembelian Bersih - Persediaan Akhir = HPP</strong>.
                                Persediaan akhir ditentukan berdasarkan Stock Opname (SO), kemudian HPP
                                difinalisasi melalui proses Tutup Buku / Closing.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="setup-akun" role="tabpanel">
        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold">Setup Akun</div>
            <div class="card-body">
                <div class="text-secondary small mb-4">
                    Akun default transaksi. HPP mengikuti Unit Bisnis dan Metode HPP; akun lainnya digunakan sebagai default posting.
                </div>
                <form method="POST" action="{{ route('pengaturan.account-mapping.save') }}">
                    @csrf
                    <h6 class="fw-semibold border-bottom pb-2">HPP</h6>
                    @forelse($units as $unit)
                        @php
                            $hppKey = $unit->business_type === 'retail' ? 'hpp_retail' : ($unit->business_type === 'production' ? 'hpp_production' : 'hpp_service');
                            $currentHpp = $mappings->get($unit->id . '|' . $hppKey)?->account_id;
                            $type = ['retail'=>'Retail', 'production'=>'Produksi', 'service'=>'Jasa'][$unit->business_type] ?? $unit->business_type;
                        @endphp
                        <div class="row align-items-center mb-3">
                            <div class="col-md-4">
                                <label class="form-label mb-1"><strong>{{ $type }}</strong></label>
                                <div class="text-secondary small">{{ $unit->code }} — {{ $unit->name }} · {{ ucfirst($unit->hpp_method) }}</div>
                            </div>
                            <div class="col-md-8">
                                <select name="accounts[{{ $unit->id }}][{{ $hppKey }}]" class="form-select" required>
                                    <option value="">Pilih akun HPP</option>
                                    @foreach($accounts as $account)
                                        <option value="{{ $account->id }}" @selected((string)$currentHpp === (string)$account->id)>{{ $account->code }} — {{ $account->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    @empty
                        <div class="alert alert-light border">Belum ada Unit Bisnis.</div>
                    @endforelse

                    @php
                        $accountGroups = [
                            'Persediaan' => [
                                'inventory_merchandise' => 'Persediaan Barang Dagangan',
                                'inventory_raw_material' => 'Persediaan Bahan Baku',
                                'inventory_wip' => 'Barang Dalam Proses (WIP)',
                                'inventory_finished_goods' => 'Persediaan Barang Jadi',
                            ],
                            'Penjualan / Pendapatan' => [
                                'sales_merchandise' => 'Penjualan Barang',
                                'sales_finished_goods' => 'Penjualan Produk',
                                'service_revenue' => 'Pendapatan Jasa',
                            ],
                            'Kas & Bank' => [
                                'cash' => 'Kas',
                                'bank' => 'Bank',
                            ],
                            'Piutang & Hutang' => [
                                'receivable' => 'Piutang Usaha',
                                'payable' => 'Hutang Usaha',
                            ],
                        ];
                    @endphp

                    @foreach($accountGroups as $group => $fields)
                        <h6 class="fw-semibold border-bottom pb-2 mt-4">{{ $group }}</h6>
                        @foreach($fields as $key => $label)
                            <div class="row align-items-center mb-3">
                                <div class="col-md-4"><label class="form-label mb-0">{{ $label }}</label></div>
                                <div class="col-md-8">
                                    <select name="defaults[{{ $key }}]" class="form-select" required>
                                        <option value="">Pilih akun</option>
                                        @foreach($accounts as $account)
                                            <option value="{{ $account->id }}" @selected((string)($defaults[$key] ?? '') === (string)$account->id)>{{ $account->code }} — {{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        @endforeach
                    @endforeach

                    <button type="submit" class="btn btn-primary">Simpan Setup Akun</button>
                </form>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="nota-invoice" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Setup Nota/Invoice</div>
            <div class="card-body text-secondary">
                Setup Nota/Invoice disiapkan pada tahap berikutnya.
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="nomor-dokumen" role="tabpanel">
        <div class="card shadow-sm">
            <div class="card-header fw-semibold">Nomor Dokumen</div>
            <div class="card-body text-secondary">
                Setup Nomor Dokumen disiapkan pada tahap berikutnya.
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('#setup-unit form');
    if (!form) return;

    const code = form.querySelector('[name="code"]');
    const name = form.querySelector('[name="name"]');
    const type = form.querySelector('[name="business_type"]');
    const method = form.querySelector('[name="hpp_method"]');
    const active = form.querySelector('[name="is_active"][type="checkbox"]');
    const submit = form.querySelector('button[type="submit"]');
    if (!submit) return;

    const cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.className = 'btn btn-secondary ms-1 d-none';
    cancel.textContent = 'Batal';
    submit.after(cancel);

    let methodInput = form.querySelector('[name="_method"]');
    if (!methodInput) {
        methodInput = document.createElement('input');
        methodInput.type = 'hidden';
        methodInput.name = '_method';
        form.appendChild(methodInput);
    }

    function resetForm() {
        form.action = "{{ route('pengaturan.unit-bisnis.store') }}";
        methodInput.value = '';
        code.value = '';
        name.value = '';
        type.value = '';
        method.value = '';
        active.checked = true;
        submit.textContent = 'Simpan';
        cancel.classList.add('d-none');
        form.removeAttribute('data-editing');
    }

    document.querySelectorAll('.btn-edit-unit').forEach(function (button) {
        button.addEventListener('click', async function () {
            try {
                const response = await fetch(button.dataset.url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!response.ok) throw new Error('Gagal mengambil data unit.');
                const data = await response.json();

                code.value = data.code || '';
                name.value = data.name || '';
                type.value = data.business_type || '';
                method.value = data.hpp_method || '';
                active.checked = !!data.is_active;
                form.action = "{{ url('/pengaturan/konfigurasi/unit-bisnis') }}/" + data.id;
                methodInput.value = 'PUT';
                submit.textContent = 'Update';
                cancel.classList.remove('d-none');
                form.setAttribute('data-editing', data.id);

                document.querySelector('[data-bs-target="#setup-unit"]').click();
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } catch (error) {
                alert(error.message);
            }
        });
    });

    cancel.addEventListener('click', resetForm);
});
</script>
@endsection
