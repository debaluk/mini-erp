<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    private function moduleCatalog(): array
    {
        return [
            'master_data' => 'Master Data',
            'pos_retail' => 'POS Retail',
            'produksi' => 'Produksi',
            'armada_jasa' => 'Armada & Jasa',
            'inventori' => 'Inventori',
            'akuntansi' => 'Akuntansi',
            'laporan' => 'Laporan',
            'konfigurasi' => 'Konfigurasi',
        ];
    }

    private function defaultModulesForRole(string $role): array
    {
        return match ($role) {
            'admin' => ['master_data', 'konfigurasi'],
            'kasir' => ['pos_retail'],
            'inventori' => ['produksi', 'armada_jasa', 'inventori'],
            'akuntansi' => ['akuntansi', 'laporan'],
            default => [],
        };
    }
    private function entityId(Request $request): ?int
    {
        if ($request->user()?->role === 'superadmin') {
            return $request->integer('entity_id') ?: DB::table('entities')->value('id');
        }

        return $request->user()?->entity_id;
    }

    public function entity(Request $request)
    {
        $entityId = $this->entityId($request);
        abort_unless($entityId, 404);

        if ($request->user()->role === 'superadmin') {
            $entities = DB::table('entities')->orderBy('name')->get();
            $entity = DB::table('entities')->where('id', $entityId)->first();
        } else {
            $entities = collect();
            $entity = DB::table('entities')->where('id', $entityId)->first();
        }

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

        return view('settings.users', compact('users', 'moduleCatalog', 'userModules'));
    }

    public function userStore(Request $request)
    {
        abort_unless($request->user()->role === 'owner' && $request->user()->entity_id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['admin', 'kasir', 'inventori', 'akuntansi'])],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['string', Rule::in(array_keys($this->moduleCatalog()))],
        ]);

        $userId = DB::table('users')->insertGetId([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'entity_id' => $request->user()->entity_id,
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_module_permissions')->insert(array_map(fn ($module) => [
            'user_id' => $userId,
            'module' => $module,
            'created_at' => now(),
            'updated_at' => now(),
        ], array_values(array_unique($data['modules']))));

        return back()->with('success', 'User berhasil ditambahkan.');
    }

    public function userUpdate(Request $request, int $id)
    {
        abort_unless($request->user()->role === 'owner' && $request->user()->entity_id, 403);

        $user = DB::table('users')
            ->where('id', $id)
            ->where('entity_id', $request->user()->entity_id)
            ->first();
        abort_unless($user, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'role' => ['required', Rule::in(['admin', 'kasir', 'inventori', 'akuntansi'])],
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['string', Rule::in(array_keys($this->moduleCatalog()))],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'updated_at' => now(),
        ];

        if (!empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        DB::table('users')->where('id', $id)->update($payload);

        DB::table('user_module_permissions')->where('user_id', $id)->delete();
        DB::table('user_module_permissions')->insert(array_map(fn ($module) => [
            'user_id' => $id,
            'module' => $module,
            'created_at' => now(),
            'updated_at' => now(),
        ], array_values(array_unique($data['modules']))));

        return back()->with('success', 'User berhasil diperbarui.');
    }

    public function userToggle(Request $request, int $id)
    {
        abort_unless($request->user()->role === 'owner' && $request->user()->entity_id, 403);

        $user = DB::table('users')
            ->where('id', $id)
            ->where('entity_id', $request->user()->entity_id)
            ->first();
        abort_unless($user, 404);

        DB::table('users')->where('id', $id)->update([
            'is_active' => !$user->is_active,
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Status user berhasil diubah.');
    }

    public function roles(Request $request)
    {
        $roles = [
            ['code' => 'superadmin', 'name' => 'Superadmin', 'scope' => 'Level sistem', 'description' => 'Pemilik/pengelola sistem. Mengelola seluruh entitas dan hak akses Owner.'],
            ['code' => 'owner', 'name' => 'Owner', 'scope' => 'Entitas', 'description' => 'Pemilik entitas. Mengelola bisnis entitas dan user operasional.'],
            ['code' => 'admin', 'name' => 'Admin', 'scope' => 'Entitas', 'description' => 'Administrasi, master data, user dan konfigurasi sesuai hak akses yang diberikan.'],
            ['code' => 'kasir', 'name' => 'Kasir', 'scope' => 'Entitas', 'description' => 'Operasional POS Retail, pembayaran dan shift kasir.'],
            ['code' => 'inventori', 'name' => 'Inventori', 'scope' => 'Entitas', 'description' => 'Pembelian, gudang, stok, produksi dan armada.'],
            ['code' => 'akuntansi', 'name' => 'Akuntansi', 'scope' => 'Entitas', 'description' => 'Akuntansi, HPP dan laporan keuangan.'],
        ];

        $permissions = [
            'Dashboard',
            'Master Data',
            'POS Retail',
            'Produksi',
            'Armada & Jasa',
            'Inventori',
            'Akuntansi',
            'Laporan',
            'Pengaturan User',
            'Pengaturan Entitas',
            'Role & Hak Akses',
            'Konfigurasi',
        ];

        $matrix = [
            'superadmin' => array_fill_keys($permissions, true),
            'owner' => array_fill_keys($permissions, true),
            'admin' => array_fill_keys($permissions, false),
            'kasir' => array_fill_keys($permissions, false),
            'inventori' => array_fill_keys($permissions, false),
            'akuntansi' => array_fill_keys($permissions, false),
        ];

        foreach (['Dashboard','Master Data','Pengaturan User','Konfigurasi'] as $p) $matrix['admin'][$p] = true;
        foreach (['Dashboard','POS Retail'] as $p) $matrix['kasir'][$p] = true;
        foreach (['Dashboard','Produksi','Armada & Jasa','Inventori'] as $p) $matrix['inventori'][$p] = true;
        foreach (['Dashboard','Akuntansi','Laporan'] as $p) $matrix['akuntansi'][$p] = true;

        return view('settings.roles', compact('roles', 'permissions', 'matrix'));
    }

    public function configuration(Request $request)
    {
        $entityId = $request->user()->entity_id;
        abort_unless($entityId, 403);

        $units = DB::table('business_units')
            ->where('entity_id', $entityId)
            ->orderBy('code')
            ->get();

        $accounts = DB::table('chart_of_accounts')
            ->where('entity_id', $entityId)
            ->where('is_active', true)
            ->whereIn('type', ['cogs', 'expense'])
            ->orderBy('code')
            ->get();

        return view('settings.configuration', compact('units', 'accounts'));
    }
}
