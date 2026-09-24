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
        if ($this->role === 'owner') {
            return in_array($module, ['master', 'inventori', 'keuangan', 'pengaturan'], true);
        }

        if (!in_array($module, ['master', 'inventori', 'keuangan', 'pengaturan'], true)) {
            return false;
        }

        return DB::table('user_module_permissions')
            ->where('user_id', $this->id)
            ->where('module', $module)
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
