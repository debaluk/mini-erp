<div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold">{{ isset($editUnit) ? "Edit Unit Bisnis" : "Tambah Unit Bisnis" }}</div>
    <div class="card-body">
        <form method="POST" action="{{ isset($editUnit) ? route('pengaturan.unit-bisnis.update', $editUnit->id) : route('pengaturan.unit-bisnis.store') }}" class="row align-items-end">
            @csrf
            @if(isset($editUnit))
                @method('PUT')
            @endif
            <div class="col-md-2">
                <label class="form-label">Kode Unit *</label>
                <input type="text" name="code" class="form-control" value="{{ old('code', $editUnit->code ?? '') }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Nama Unit *</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $editUnit->name ?? '') }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tipe Usaha *</label>
                <select name="business_type" class="form-select" required>
                    <option value="">Pilih</option>
                    <option value="retail" @selected(old('business_type', $editUnit->business_type ?? '') === 'retail')>Retail</option>
                    <option value="production" @selected(old('business_type', $editUnit->business_type ?? '') === 'production')>Produksi</option>
                    <option value="service" @selected(old('business_type', $editUnit->business_type ?? '') === 'service')>Jasa</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Metode HPP *</label>
                <select name="hpp_method" class="form-select" required>
                    <option value="">Pilih</option>
                    <option value="perpetual" @selected(old('hpp_method', $editUnit->hpp_method ?? '') === 'perpetual')>Perpetual</option>
                    <option value="periodic" @selected(old('hpp_method', $editUnit->hpp_method ?? '') === 'periodic')>Periodik</option>
                    <option value="direct_cost" @selected(old('hpp_method', $editUnit->hpp_method ?? '') === 'direct_cost')>Direct Cost</option>
                </select>
            </div>
            <div class="col-md-1">
                <div class="form-check mb-2">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="new-active" @checked(old('is_active', $editUnit->is_active ?? true))>
                    <label class="form-check-label" for="new-active">Aktif</label>
                </div>
            </div>
            <div class="col-md-2">
                <div class="d-flex gap-2">
                    <button class="btn btn-primary flex-fill">{{ isset($editUnit) ? 'Update' : 'Simpan' }}</button>
                    @if(isset($editUnit))
                        <a href="{{ route('pengaturan.unit-bisnis') }}" class="btn btn-outline-secondary flex-fill">Batal</a>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>
