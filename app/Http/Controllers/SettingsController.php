<?php

namespace App\Http\Controllers;

use App\Models\BusinessUnit;
use App\Models\BusinessUnitAccountMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    private function moduleCatalog(): array
    {
        return [
            'master' => 'Master',
            'inventori' => 'Inventori',
            'keuangan' => 'Keuangan',
            'pengaturan' => 'Pengaturan',
        ];
    }

    private function defaultModulesForRole(string $role): array
    {
        return match ($role) {
            'owner' => ['master', 'inventori', 'keuangan', 'pengaturan'],
            'admin' => ['master', 'pengaturan'],
            'kasir' => ['inventori'],
            'inventori' => ['inventori'],
            'akuntansi' => ['keuangan'],
            default => [],
        };
    }

    private function entityId(Request $request): ?int
    {
        return $request->user()?->entity_id;
    }

    public function entity(Request $request)
    {
        $entityId = $this->entityId($request);
        abort_unless($entityId, 404);

        $entities = collect();
        $entity = DB::table('entities')->where('id', $entityId)->first();

        return view('settings.entity', compact('entity', 'entities'));
    }

    public function entityUpdate(Request $request)
    {
        $entityId = $this->entityId($request);
        abort_unless($entityId, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'npwp' => ['nullable', 'string', 'max:100'],
            'nib' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $entity = DB::table('entities')->where('id', $entityId)->first();
        abort_unless($entity, 404);

        unset($data['logo']);

        if ($request->hasFile('logo')) {
            if ($entity->logo_path) {
                Storage::disk('public')->delete($entity->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('entities/logos', 'public');
        }

        $data['updated_at'] = now();
        DB::table('entities')->where('id', $entityId)->update($data);

        return back()->with('success', 'Data entitas berhasil disimpan.');
    }

    public function users(Request $request)
    {
        $entityId = $request->user()->entity_id;
        abort_unless($request->user()->role === 'owner' && $entityId, 403);

        $users = DB::table('users')
            ->where('entity_id', $entityId)
            ->orderBy('name')
            ->get();

        $moduleCatalog = $this->moduleCatalog();
        $userModules = DB::table('user_module_permissions')
            ->whereIn('user_id', $users->pluck('id'))
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('module')->values()->all());

        foreach ($users as $user) {
            if ($user->role === 'owner') {
                $userModules[$user->id] = ['master', 'inventori', 'keuangan', 'pengaturan'];
            }
        }

        $businessUnits = DB::table('business_units')
            ->where('entity_id', $entityId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $userBusinessUnits = DB::table('user_business_units')
            ->whereIn('user_id', $users->pluck('id'))
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('business_unit_id')->values()->all());

        return view('settings.users', compact(
            'users',
            'moduleCatalog',
            'userModules',
            'businessUnits',
            'userBusinessUnits'
        ));
    }

    public function userStore(Request $request)
    {
        abort_unless($request->user()->hasModuleAccess('pengaturan') && $request->user()->entity_id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['admin', 'kasir', 'inventori', 'akuntansi'])],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(array_keys($this->moduleCatalog()))],
            'business_units' => ['required', 'array', 'min:1'],
            'business_units.*' => [
                'integer',
                Rule::exists('business_units', 'id')->where(fn ($query) => $query
                    ->where('entity_id', $request->user()->entity_id)
                    ->where('is_active', true)
                ),
            ],
            'default_business_unit' => [
                'required',
                'integer',
                Rule::exists('business_units', 'id')->where(fn ($query) => $query
                    ->where('entity_id', $request->user()->entity_id)
                    ->where('is_active', true)
                ),
            ],
        ]);

        if (!in_array((int) $data['default_business_unit'], array_map('intval', $data['business_units']), true)) {
            throw ValidationException::withMessages([
                'default_business_unit' => 'Business Unit default harus termasuk dalam Business Unit yang dipilih.',
            ]);
        }

        $modules = array_values(array_unique($data['modules'] ?? $this->defaultModulesForRole($data['role'])));

        $userId = DB::table('users')->insertGetId([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'entity_id' => $request->user()->entity_id,
            'default_business_unit_id' => $data['default_business_unit'],
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_module_permissions')->insert(array_map(fn ($module) => [
            'user_id' => $userId,
            'module' => $module,
            'created_at' => now(),
            'updated_at' => now(),
        ], $modules));

        DB::table('user_business_units')->insert(array_map(fn ($businessUnitId) => [
            'user_id' => $userId,
            'business_unit_id' => $businessUnitId,
            'is_default' => (int) $businessUnitId === (int) $data['default_business_unit'],
            'created_at' => now(),
            'updated_at' => now(),
        ], array_values(array_unique($data['business_units']))));

        return back()->with('success', 'User berhasil ditambahkan.');
    }

    public function userUpdate(Request $request, int $id)
    {
        abort_unless($request->user()->hasModuleAccess('pengaturan') && $request->user()->entity_id, 403);

        $user = DB::table('users')
            ->where('id', $id)
            ->where('entity_id', $request->user()->entity_id)
            ->first();
        abort_unless($user, 404);
        abort_unless($user->role !== 'owner', 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'role' => ['required', Rule::in(['admin', 'kasir', 'inventori', 'akuntansi'])],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(array_keys($this->moduleCatalog()))],
            'business_units' => ['required', 'array', 'min:1'],
            'business_units.*' => [
                'integer',
                Rule::exists('business_units', 'id')->where(fn ($query) => $query
                    ->where('entity_id', $request->user()->entity_id)
                    ->where('is_active', true)
                ),
            ],
            'default_business_unit' => [
                'required',
                'integer',
                Rule::exists('business_units', 'id')->where(fn ($query) => $query
                    ->where('entity_id', $request->user()->entity_id)
                    ->where('is_active', true)
                ),
            ],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        abort_unless(in_array((int) $data['default_business_unit'], array_map('intval', $data['business_units']), true), 422);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'default_business_unit_id' => $data['default_business_unit'],
            'updated_at' => now(),
        ];

        if (!empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        DB::table('users')->where('id', $id)->update($payload);

        DB::table('user_module_permissions')->where('user_id', $id)->delete();
        $modules = array_values(array_unique($data['modules'] ?? $this->defaultModulesForRole($data['role'])));

        DB::table('user_module_permissions')->insert(array_map(fn ($module) => [
            'user_id' => $id,
            'module' => $module,
            'created_at' => now(),
            'updated_at' => now(),
        ], $modules));

        DB::table('user_business_units')->where('user_id', $id)->delete();
        DB::table('user_business_units')->insert(array_map(fn ($businessUnitId) => [
            'user_id' => $id,
            'business_unit_id' => $businessUnitId,
            'is_default' => (int) $businessUnitId === (int) $data['default_business_unit'],
            'created_at' => now(),
            'updated_at' => now(),
        ], array_values(array_unique($data['business_units']))));

        return back()->with('success', 'User berhasil diperbarui.');
    }

    public function userToggle(Request $request, int $id)
    {
        abort_unless($request->user()->hasModuleAccess('pengaturan') && $request->user()->entity_id, 403);

        $user = DB::table('users')
            ->where('id', $id)
            ->where('entity_id', $request->user()->entity_id)
            ->first();
        abort_unless($user, 404);
        abort_unless($user->role !== 'owner', 403);

        DB::table('users')->where('id', $id)->update([
            'is_active' => !$user->is_active,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Status user berhasil diubah.');
    }

    public function roles(Request $request)
    {
        $roles = [
            ['code' => 'owner', 'name' => 'Owner', 'scope' => 'Entitas', 'description' => 'Pemilik entitas. Hak akses selalu penuh dan role tidak dapat dikurangi.'],
            ['code' => 'admin', 'name' => 'Admin', 'scope' => 'Entitas', 'description' => 'Administrasi, master data dan pengaturan sesuai hak akses user.'],
            ['code' => 'kasir', 'name' => 'Kasir', 'scope' => 'Entitas', 'description' => 'Operasional penjualan retail/POS melalui akses Inventori.'],
            ['code' => 'inventori', 'name' => 'Inventori', 'scope' => 'Entitas', 'description' => 'Pembelian, gudang, stok dan produksi melalui akses Inventori.'],
            ['code' => 'akuntansi', 'name' => 'Akuntansi', 'scope' => 'Entitas', 'description' => 'Akuntansi dan laporan keuangan melalui akses Keuangan.'],
        ];

        $permissions = ['Master', 'Inventori', 'Keuangan', 'Pengaturan'];

        $matrix = [
            'owner' => ['Master' => true, 'Inventori' => true, 'Keuangan' => true, 'Pengaturan' => true],
            'admin' => ['Master' => true, 'Inventori' => false, 'Keuangan' => false, 'Pengaturan' => true],
            'kasir' => ['Master' => false, 'Inventori' => true, 'Keuangan' => false, 'Pengaturan' => false],
            'inventori' => ['Master' => false, 'Inventori' => true, 'Keuangan' => false, 'Pengaturan' => false],
            'akuntansi' => ['Master' => false, 'Inventori' => false, 'Keuangan' => true, 'Pengaturan' => false],
        ];

        return view('settings.roles', compact('roles', 'permissions', 'matrix'));
    }

    public function configuration(Request $request)
    {
        $entityId = $request->user()->entity_id;
        abort_unless($entityId, 403);

        $units = BusinessUnit::where('entity_id', $entityId)
            ->orderBy('code')
            ->get();

        $accounts = DB::table('chart_of_accounts')
            ->where('entity_id', $entityId)
            ->where('is_active', true)
            ->where('is_postable', true)
            ->orderBy('code')
            ->get();

        $mappings = BusinessUnitAccountMapping::where('entity_id', $entityId)
            ->get()
            ->groupBy('business_unit_id')
            ->map(fn ($rows) => $rows->keyBy('mapping_key'));

        $mappingLabels = [
            'cash' => 'Kas',
            'bank' => 'Bank',
            'receivable' => 'Piutang',
            'payable' => 'Hutang',
            'inventory' => 'Persediaan',
            'sales_merchandise' => 'Penjualan Barang Dagangan',
            'sales_finished_goods' => 'Penjualan Hasil Produksi',
            'sales_service' => 'Pendapatan Jasa',
            'cogs_merchandise' => 'HPP Barang Dagangan',
            'cogs_finished_goods' => 'HPP Hasil Produksi',
            'direct_material' => 'Bahan Baku Langsung',
            'direct_labor' => 'Tenaga Kerja Langsung',
            'direct_overhead' => 'Overhead Langsung',
        ];

        $warehouseList = DB::table('warehouses')
            ->where('entity_id', $entityId)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'address', 'is_active']);

        $warehouseMappings = DB::table('warehouse_business_units')
            ->where('entity_id', $entityId)
            ->get()
            ->keyBy('warehouse_id');

        $mappingKeys = $units->mapWithKeys(fn ($unit) => [
            $unit->id => match ($unit->business_type) {
                'retail' => ['cash','bank','receivable','payable','inventory','sales_merchandise','cogs_merchandise'],
                'production' => ['cash','bank','receivable','payable','inventory','sales_finished_goods','cogs_finished_goods','direct_material','direct_labor','direct_overhead'],
                'service' => ['cash','bank','receivable','payable','sales_service','direct_material','direct_labor','direct_overhead'],
                default => [],
            },
        ]);

        return view('settings.configuration', compact(
            'units',
            'accounts',
            'mappings',
            'mappingLabels',
            'mappingKeys',
            'warehouseList',
            'warehouseMappings'
        ));
    }

    public function warehouseMappingSave(Request $request)
    {
        $entityId = $request->user()->entity_id;
        abort_unless($entityId, 403);

        $data = $request->validate([
            'warehouse_business_units' => ['nullable', 'array'],
            'warehouse_business_units.*' => ['nullable', 'integer'],
        ]);

        $warehouseIds = DB::table('warehouses')
            ->where('entity_id', $entityId)
            ->where('is_active', 1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $businessUnitIds = DB::table('business_units')
            ->where('entity_id', $entityId)
            ->where('is_active', 1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $submitted = collect($data['warehouse_business_units'] ?? [])
            ->mapWithKeys(fn ($businessUnitId, $warehouseId) => [(int) $warehouseId => (int) $businessUnitId])
            ->filter(fn ($businessUnitId, $warehouseId) =>
                $businessUnitId > 0 && in_array($warehouseId, $warehouseIds, true)
            );

        abort_if($submitted->values()->diff($businessUnitIds)->isNotEmpty(), 422, 'Unit Bisnis tidak valid.');
        abort_if($submitted->countBy()->filter(fn ($count) => $count > 1)->isNotEmpty(), 422, 'Satu Unit Bisnis hanya boleh memiliki satu Gudang.');

        DB::transaction(function () use ($entityId, $warehouseIds, $submitted): void {
            DB::table('warehouse_business_units')->where('entity_id', $entityId)->delete();

            foreach ($submitted as $warehouseId => $businessUnitId) {
                DB::table('warehouse_business_units')->insert([
                    'entity_id' => $entityId,
                    'warehouse_id' => $warehouseId,
                    'business_unit_id' => $businessUnitId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Compatibility sementara modul operasional masih membaca kolom legacy.
                DB::table('warehouses')
                    ->where('entity_id', $entityId)
                    ->where('id', $warehouseId)
                    ->update(['business_unit_id' => $businessUnitId, 'updated_at' => now()]);
            }

            DB::table('warehouses')
                ->where('entity_id', $entityId)
                ->whereIn('id', $warehouseIds)
                ->whereNotIn('id', $submitted->keys()->all())
                ->update(['business_unit_id' => null, 'updated_at' => now()]);
        });

        return back()->with('success', 'Mapping Gudang ke Unit Bisnis berhasil disimpan.');
    }
}
