<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntry extends Model
{
    use HasFactory;

    /**
     * Nama tabel database [1].
     */
    protected $table = 'journal_entries';

    /**
     * Atribut yang dapat diisi secara massal.
     */
    protected $fillable = [
        'journal_id',
        'account_id',
        'debit',
        'credit',
    ];

    /**
     * Casting tipe data numerik presisi decimal(18,2) [1].
     */
    protected $casts = [
        'debit'  => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    // =========================================================================
    // RELASI ELOQUENT
    // =========================================================================

    /**
     * Relasi balik ke Header Jurnal [1, 2].
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'journal_id');
    }

    /**
     * Relasi ke Akun COA (Chart of Accounts) 7-Digit [1, 9].
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}