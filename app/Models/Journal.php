<?php


namespace App\Models;

use App\Exports\JournalGroupedMatrixExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Journal extends Model
{
    use HasFactory;

    /**
     * Nama tabel database [2].
     */
    protected $table = 'journals';

    /**
     * Atribut yang dapat diisi secara massal.
     */
    protected $fillable = [
        'entity_id',
        'business_unit_id',
        'journal_no',
        'journal_date',
        'source_type',
        'source_id',
        'description',
        'status',
    ];

    /**
     * Casting tipe data otomatis.
     */
    protected $casts = [
        'journal_date' => 'date',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    // =========================================================================
    // RELASI ELOQUENT
    // =========================================================================

    /**
     * Relasi ke Entitas / Tenant Utama [2, 3].
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    /**
     * Relasi ke Unit Bisnis (Retail, Produksi, Jasa) [2, 4].
     */
    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class, 'business_unit_id');
    }

    /**
     * Relasi ke Rincian Baris Jurnal (Debit & Kredit) [1, 2].
     */
    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'journal_id');
    }

    // =========================================================================
    // LOCAL SCOPES (Server-Side Filtering & DataTables)
    // =========================================================================

    /**
     * Scope untuk menyaring data jurnal berdasarkan kriteria Form / AJAX Request.
     */
    public function scopeFilter(Builder $query, array $filters): Builder
{
    return $query
        ->when(!empty($filters['start_date']), fn($q) => $q->whereDate('journal_date', '>=', $filters['start_date']))
        ->when(!empty($filters['end_date']), fn($q) => $q->whereDate('journal_date', '<=', $filters['end_date']))
        ->when(!empty($filters['business_unit_id']), fn($q) => $q->where('business_unit_id', $filters['business_unit_id']))
        ->when(!empty($filters['source_type']), fn($q) => $q->where('source_type', $filters['source_type']));
}

    /**
     * Scope khusus hanya jurnal berstatus POSTED.
     */
    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', 'posted');
    }

    /**
     * Scope Idempotency Guard: Mengecek apakah transaksi sumber sudah pernah diposting [5, 6].
     */
    public function scopeBySource(Builder $query, string $sourceType, int|string $sourceId): Builder
    {
        return $query->where('source_type', $sourceType)
                    ->where('source_id', $sourceId);
    }

    // =========================================================================
    // ACCESSORS & HELPER METHODS
    // =========================================================================

    /**
     * Menghitung total Debit dari seluruh baris detail.
     */
    public function getTotalDebitAttribute(): float
    {
        return (float) $this->entries->sum('debit');
    }

    /**
     * Menghitung total Kredit dari seluruh baris detail.
     */
    public function getTotalCreditAttribute(): float
    {
        return (float) $this->entries->sum('credit');
    }

    /**
     * Pengecekan Keseimbangan Jurnal (Debit == Kredit) [7].
     */
    public function getIsBalancedAttribute(): bool
    {
        return abs($this->total_debit - $this->total_credit) < 0.001;
    }

    /**
     * Hak Akses Edit: Hanya Jurnal Manual / Situasional yang dapat di-edit [8].
     * Jurnal otomatis dari Engine ('sale', 'purchase', 'transfer', dll) bersifat LOCKED ?? [5, 8].
     */
    public function getIsEditableAttribute(): bool
    {
        return in_array(strtolower($this->source_type ?? ''), ['manual', 'jurnal_umum']);
    }
	
	public function export(Request $request)
	{
		$fileName = 'Export_Jurnal_Matrix_' . date('Ymd_His') . '.xlsx';

		return Excel::download(new JournalGroupedMatrixExport($request), $fileName);
	}
}