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

        DB::table('chart_of_accounts')
            ->whereIn('code', $legacyCodes)
            ->delete();
    }

    public function down(): void
    {
        // Data COA legacy yang dihapus tidak dikembalikan.
    }
};
