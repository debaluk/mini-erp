<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $legacyCodes = [
            '1000', '1100', '1200',
            '2000',
            '3000',
            '4000',
            '5000',
            '6000',
        ];

        // COA legacy masih direferensikan journal_entries.
        // Jangan hapus agar histori jurnal tetap valid.
        DB::table('chart_of_accounts')
            ->whereIn('code', $legacyCodes)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        $legacyCodes = [
            '1000', '1100', '1200',
            '2000',
            '3000',
            '4000',
            '5000',
            '6000',
        ];

        DB::table('chart_of_accounts')
            ->whereIn('code', $legacyCodes)
            ->update([
                'is_active' => true,
                'updated_at' => now(),
            ]);
    }
};
