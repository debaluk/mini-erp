<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $entityIds = DB::table('entities')->where('is_active', true)->pluck('id');

        $roots = [
            ['code' => '100', 'name' => 'Asset', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '200', 'name' => 'Hutang', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '300', 'name' => 'Modal', 'type' => 'equity', 'normal_balance' => 'credit'],
            ['code' => '400', 'name' => 'Pendapatan', 'type' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '500', 'name' => 'HPP', 'type' => 'cogs', 'normal_balance' => 'debit'],
            ['code' => '600', 'name' => 'Biaya', 'type' => 'expense', 'normal_balance' => 'debit'],
        ];

        foreach ($entityIds as $entityId) {
            foreach ($roots as $root) {
                $account = DB::table('chart_of_accounts')
                    ->where('entity_id', $entityId)
                    ->where('code', $root['code'])
                    ->first();

                $data = [
                    'name' => $root['name'],
                    'type' => $root['type'],
                    'normal_balance' => $root['normal_balance'],
                    'level' => 1,
                    'parent_id' => null,
                    'is_postable' => false,
                    'is_cash_bank' => false,
                    'is_active' => true,
                    'updated_at' => now(),
                ];

                if ($account) {
                    DB::table('chart_of_accounts')->where('id', $account->id)->update($data);
                } else {
                    DB::table('chart_of_accounts')->insert(array_merge($data, [
                        'entity_id' => $entityId,
                        'code' => $root['code'],
                        'description' => null,
                        'created_at' => now(),
                    ]));
                }
            }
        }
    }

    public function down(): void
    {
        // Akun utama tidak dihapus agar data COA yang sudah dipakai tetap aman.
    }
};
