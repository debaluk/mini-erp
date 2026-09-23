<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Entity;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'entity_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function hasModuleAccess(string $module): bool
    {
        if (in_array($this->role, ['superadmin', 'owner'], true)) {
            return true;
        }

        $groups = [
            'master_operasional' => ['master_operasional', 'master_data', 'pos_retail', 'produksi', 'armada_jasa', 'inventori'],
            'keuangan_akuntansi' => ['keuangan_akuntansi', 'akuntansi', 'laporan'],
            'seting' => ['seting', 'konfigurasi'],
            'master_data' => ['master_data', 'master_operasional'],
            'pos_retail' => ['pos_retail', 'master_operasional'],
            'produksi' => ['produksi', 'master_operasional'],
            'armada_jasa' => ['armada_jasa', 'master_operasional'],
            'inventori' => ['inventori', 'master_operasional'],
            'akuntansi' => ['akuntansi', 'keuangan_akuntansi'],
            'laporan' => ['laporan', 'keuangan_akuntansi'],
            'konfigurasi' => ['konfigurasi', 'seting'],
        ];

        $allowed = $groups[$module] ?? [$module];

        return DB::table('user_module_permissions')
            ->where('user_id', $this->id)
            ->whereIn('module', $allowed)
            ->exists();
    }

    public function hasAnyModuleAccess(array $modules): bool
    {
        foreach ($modules as $module) {
            if ($this->hasModuleAccess($module)) {
                return true;
            }
        }

        return false;
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
