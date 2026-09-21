<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_unit_account_mappings')) {
            $rows = DB::table('business_unit_account_mappings as m')
                ->join('business_units as u', 'u.id', '=', 'm.business_unit_id')
                ->where('m.mapping_key', 'hpp')
                ->select('m.id', 'u.business_type')
                ->get();

            foreach ($rows as $row) {
                $key = match ($row->business_type) {
                    'retail' => 'hpp_retail',
                    'production' => 'hpp_production',
                    'service' => 'hpp_service',
                    default => 'hpp',
                };

                DB::table('business_unit_account_mappings')
                    ->where('id', $row->id)
                    ->update(['mapping_key' => $key, 'updated_at' => now()]);
            }
        }

        if (Schema::hasColumn('business_units', 'hpp_account_id')) {
            Schema::table('business_units', function (Blueprint $table) {
                $table->dropForeign(['hpp_account_id']);
                $table->dropColumn('hpp_account_id');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('business_units', 'hpp_account_id')) {
            Schema::table('business_units', function (Blueprint $table) {
                $table->foreignId('hpp_account_id')
                    ->nullable()
                    ->after('hpp_method')
                    ->constrained('chart_of_accounts')
                    ->nullOnDelete();
            });
        }
    }
};
