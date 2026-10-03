<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->unsignedBigInteger('production_work_order_id')->nullable()->after('bom_id');
            $table->unique('production_work_order_id', 'productions_work_order_unique');
        });

        $entityId = DB::table('entities')->value('id');
        $productionBu = DB::table('business_units')
            ->where('entity_id', $entityId)
            ->where('code', 'PROD')
            ->first();
        if (!$entityId || !$productionBu) {
            return;
        }

        $accounts = [
            'salary_payable' => '20005',
            'inventory_finished_goods' => '1000404',
        ];

        foreach ($accounts as $key => $code) {
            $accountId = DB::table('chart_of_accounts')
                ->where('entity_id', $entityId)
                ->where('code', $code)
                ->value('id');

            if ($accountId) {
                DB::table('business_unit_account_mappings')->updateOrInsert(
                    [
                        'entity_id' => $entityId,
                        'business_unit_id' => $productionBu->id,
                        'mapping_key' => $key,
                    ],
                    [
                        'account_id' => $accountId,
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        $entityId = DB::table('entities')->value('id');
        $productionBu = DB::table('business_units')
            ->where('entity_id', $entityId)
            ->where('code', 'PROD')
            ->first();

        if ($entityId && $productionBu) {
            DB::table('business_unit_account_mappings')
                ->where('entity_id', $entityId)
                ->where('business_unit_id', $productionBu->id)
                ->whereIn('mapping_key', ['salary_payable', 'inventory_finished_goods'])
                ->delete();
        }

        Schema::table('productions', function (Blueprint $table) {
            $table->dropUnique('productions_work_order_unique');
            $table->dropColumn('production_work_order_id');
        });
    }
};
