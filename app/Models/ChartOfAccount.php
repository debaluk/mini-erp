<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class ChartOfAccount extends Model
{
    use HasFactory;

    /**
     * Nama tabel database
     */
    protected $table = 'chart_of_accounts';

    /**
     * Atribut yang dapat diisi secara massal
     */
    protected $fillable = [
        'entity_id',
        'code',
        'name',
        'level',
        'type',
        'normal_balance',
        'parent_id',
        'is_postable',
        'is_cash_bank',
        'description',
        'is_active',
    ];

    /**
     * Casting tipe data otomatis
     */
    protected $casts = [
        'level'        => 'integer',
        'is_postable'  => 'boolean',
        'is_cash_bank' => 'boolean',
        'is_active'    => 'boolean',
    ];

    // =========================================================================
    // RELASI ELOQUENT
    // =========================================================================

    /**
     * Relasi ke Tenant / Entitas
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    /**
     * Relasi ke Akun Parent (Header COA)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_id');
    }

    /**
     * Relasi ke Sub-Akun Child
     */
    public function children(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_id');
    }

    /**
     * Relasi ke Rincian Jurnal (Journal Entries)
     */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'account_id');
    }

    // =========================================================================
    // LOCAL SCOPES (FILTER QUERY)
    // =========================================================================

    /**
     * Scope khusus akun yang siap dipakai transaksi (Postable / Level Detail)
     */
    public function scopePostable(Builder $query): Builder
    {
        return $query->where('is_postable', true)->where('is_active', true);
    }

    /**
     * Scope khusus akun Kas & Bank
     */
    public function scopeCashBank(Builder $query): Builder
    {
        return $query->where('is_cash_bank', true)->where('is_active', true);
    }

    /**
     * Scope khusus akun aktif
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}