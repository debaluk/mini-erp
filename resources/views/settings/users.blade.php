@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1">User</h3>
        <div class="text-secondary">Pengguna pada Entitas</div>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="newUser()">+ User</button>
</div>

@if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger py-2"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

<div class="card shadow-sm">
    <div class="card-header fw-semibold">{{ auth()->user()->entity?->name ?? 'Entitas' }}</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Nama</th><th>Email</th><th>Role</th><th>Business Unit</th><th>Hak Akses</th><th>Status</th><th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
            @forelse($users as $u)
                <tr>
                    <td class="fw-semibold">{{ $u->name }}</td>
                    <td>{{ $u->email }}</td>
                    <td><span class="badge text-bg-secondary">{{ ucfirst($u->role) }}</span></td>
                    <td>
                        @forelse(($userBusinessUnits[$u->id] ?? []) as $bu)
                            <span class="badge {{ $bu->is_default ? 'text-bg-primary' : 'text-bg-light border' }} me-1 mb-1">{{ $businessUnits->firstWhere('id', $bu->business_unit_id)?->code ?? 'BU' }}{{ $bu->is_default ? ' ★' : '' }}</span>
                        @empty
                            <span class="text-danger">Belum dipetakan</span>
                        @endforelse
                    </td>
                    <td>
                        @forelse(($userModules[$u->id] ?? []) as $m)
                            <span class="badge text-bg-light border me-1 mb-1">{{ $moduleCatalog[$m] ?? $m }}</span>
                        @empty
                            <span class="text-secondary">—</span>
                        @endforelse
                    </td>
                    <td><span class="badge {{ $u->is_active ? 'text-bg-success':'text-bg-secondary' }}">{{ $u->is_active ? 'Aktif':'Nonaktif' }}</span></td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-outline-primary btn-sm" title="Edit User" data-bs-toggle="modal" data-bs-target="#userModal" onclick='editUser(@json(array_merge((array) $u, ["modules" => ($userModules[$u->id] ?? []), "business_units" => ($userBusinessUnits[$u->id] ?? [])])))'>✎</button>
                        <form method="POST" action="{{ route('pengaturan.user.toggle',$u->id) }}" class="d-inline">
                            @csrf @method('PATCH')
                            <button class="btn btn-outline-secondary btn-sm" title="Aktif/Nonaktif">↔</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-secondary py-4">Belum ada user.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="userForm">
                @csrf
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1" id="userModalTitle">Tambah User</h5>
                        <div class="small text-secondary">Role menentukan default hak akses. Hak akses dapat disesuaikan.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama</label>
                            <input name="name" id="uName" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="uEmail" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select name="role" id="uRole" class="form-select" required>
                                <option value="admin">Admin</option>
                                <option value="kasir">Kasir</option>
                                <option value="inventori">Inventori</option>
                                <option value="akuntansi">Akuntansi</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label mb-2">Hak Akses Modul</label>
                            <div class="border rounded p-3">
                                <div class="row g-2">
                                    @foreach($moduleCatalog as $code=>$label)
                                    <div class="col-md-6 col-lg-4">
                                        <div class="form-check border rounded px-3 py-2 h-100">
                                            <input class="form-check-input module-check" type="checkbox" name="modules[]" value="{{ $code }}" id="module_{{ $code }}">
                                            <label class="form-check-label w-100" for="module_{{ $code }}">{{ $label }}</label>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="form-text">Pilih satu atau beberapa modul. Dashboard otomatis tersedia.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label mb-2">Business Unit</label>
                            <div class="border rounded p-3">
                                <div class="row g-2">
                                    @foreach($businessUnits as $bu)
                                    <div class="col-md-4">
                                        <div class="form-check border rounded px-3 py-2 h-100">
                                            <input class="form-check-input bu-check" type="checkbox" name="business_units[]" value="{{ $bu->id }}" id="bu_{{ $bu->id }}">
                                            <label class="form-check-label w-100" for="bu_{{ $bu->id }}">{{ $bu->code }} — {{ $bu->name }}</label>
                                        </div>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                            <label class="form-label mt-3">Default Business Unit</label>
                            <select name="default_business_unit_id" id="uDefaultBU" class="form-select" required>
                                @foreach($businessUnits as $bu)
                                    <option value="{{ $bu->id }}">{{ $bu->code }} — {{ $bu->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Default BU wajib termasuk dalam Business Unit yang dipetakan ke user.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Password <span id="passwordHint" class="text-secondary">(minimal 8 karakter)</span></label>
                            <input type="password" name="password" id="uPassword" class="form-control" minlength="8">
                            <input type="password" name="password_confirmation" id="uPasswordConfirmation" class="form-control mt-2" minlength="8" placeholder="Ulangi password">
                            <div class="form-text">Saat edit, kosongkan password jika tidak ingin mengubahnya.</div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function defaultModules(role){
    const map={
        admin:['master_data','konfigurasi'],
        kasir:['pos_retail'],
        inventori:['produksi','armada_jasa','inventori'],
        akuntansi:['akuntansi','laporan']
    };
    return map[role]||[];
}
function setModules(modules){
    document.querySelectorAll('.module-check').forEach(el=>el.checked=modules.includes(el.value));
}
function setBusinessUnits(rows, fallbackDefault){
    const ids = rows.map(r => String(r.business_unit_id ?? r.id));
    document.querySelectorAll('.bu-check').forEach(el => el.checked=ids.includes(el.value));
    syncDefaultBusinessUnit(fallbackDefault || ids[0] || '');
}
function selectedBusinessUnits(){
    return Array.from(document.querySelectorAll('.bu-check:checked')).map(el => el.value);
}
function syncDefaultBusinessUnit(preferred){
    const select = document.getElementById('uDefaultBU');
    const selected = new Set(selectedBusinessUnits());
    Array.from(select.options).forEach(o => {
        o.disabled = !selected.has(o.value);
    });
    if (preferred && selected.has(String(preferred))) {
        select.value = String(preferred);
    } else if (selected.size) {
        select.value = Array.from(selected)[0];
    } else {
        select.value = '';
    }
}
document.querySelectorAll('.bu-check').forEach(el => el.addEventListener('change', () => syncDefaultBusinessUnit()));
function newUser(){
    const f=document.getElementById('userForm');
    f.action='{{ route('pengaturan.user.store') }}';
    f.querySelector('[name="_method"]')?.remove();
    document.getElementById('userModalTitle').textContent='Tambah User';
    document.getElementById('uName').value='';
    document.getElementById('uEmail').value='';
    document.getElementById('uRole').value='admin';
    document.getElementById('uPassword').value='';
    document.getElementById('uPasswordConfirmation').value='';
    document.getElementById('uPassword').required=true;
    document.getElementById('uPasswordConfirmation').required=true;
    setModules(defaultModules('admin'));
    setBusinessUnits(@json($businessUnits), '{{ $businessUnits->first()?->id }}');
}
function editUser(u){
    const f=document.getElementById('userForm');
    f.action='/pengaturan/user/'+u.id;
    if(!f.querySelector('[name="_method"]')){
        const i=document.createElement('input'); i.type='hidden'; i.name='_method'; f.appendChild(i);
    }
    f.querySelector('[name="_method"]').value='PUT';
    document.getElementById('userModalTitle').textContent='Edit User';
    document.getElementById('uName').value=u.name;
    document.getElementById('uEmail').value=u.email;
    document.getElementById('uRole').value=u.role;
    setModules(u.modules || []);
    setBusinessUnits(u.business_units || [], u.default_business_unit_id);
    document.getElementById('uPassword').value='';
    document.getElementById('uPasswordConfirmation').value='';
    document.getElementById('uPassword').required=false;
    document.getElementById('uPasswordConfirmation').required=false;
}
document.getElementById('uRole').addEventListener('change',function(){
    setModules(defaultModules(this.value));
});
</script>
@endpush
@endsection
