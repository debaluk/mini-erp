<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnitAndConversionSeeder extends Seeder
{
    public function run(): void
    {
        $entity = DB::table('entities')->orderBy('id')->first();
        if (!$entity) {
            $entityId = DB::table('entities')->insertGetId([
                'code' => 'ENT-001',
                'name' => 'Entitas Utama',
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $entityId = $entity->id;
        }

        DB::transaction(function () use ($entityId): void {
            $now = now();

            $units = [
                ['code'=>'PCS','name'=>'Pieces'],
                ['code'=>'UNIT','name'=>'Unit'],
                ['code'=>'KG','name'=>'Kilogram'],
                ['code'=>'GRAM','name'=>'Gram'],
                ['code'=>'TON','name'=>'Ton'],
                ['code'=>'SAK','name'=>'Sak'],
                ['code'=>'KARUNG','name'=>'Karung'],
                ['code'=>'DUS','name'=>'Dus'],
                ['code'=>'BOX','name'=>'Box'],
                ['code'=>'PACK','name'=>'Pack'],
                ['code'=>'LUSIN','name'=>'Lusin'],
                ['code'=>'M','name'=>'Meter'],
                ['code'=>'CM','name'=>'Centimeter'],
                ['code'=>'M2','name'=>'Meter Persegi'],
                ['code'=>'M3','name'=>'Meter Kubik'],
                ['code'=>'LITER','name'=>'Liter'],
                ['code'=>'ML','name'=>'Mililiter'],
                ['code'=>'TRIP','name'=>'Trip'],
                ['code'=>'JAM','name'=>'Jam'],
                ['code'=>'HARI','name'=>'Hari'],
            ];

            // Seed only missing master UOMs; existing UOMs are not deleted because
            // conversion/history rows may already depend on them.
            foreach ($units as $unit) {
                DB::table('units')->updateOrInsert(
                    ['entity_id' => $entityId, 'code' => $unit['code']],
                    ['name' => $unit['name'], 'is_active' => 1, 'updated_at' => $now, 'created_at' => $now]
                );
            }

            $uid = [];
            foreach (DB::table('units')->where('entity_id', $entityId)->get(['id','code']) as $u) {
                $uid[$u->code] = $u->id;
            }

            $products = DB::table('products')->where('entity_id', $entityId)->get(['id','name','base_unit_id']);

            foreach ($products as $product) {
                $n = strtolower($product->name);
                $base = null;
                $extra = null;

                if (str_contains($n, 'semen')) {
                    $base = 'KG'; $extra = ['SAK', 50];
                } elseif (str_contains($n, 'batako')) {
                    $base = 'PCS'; $extra = ['DUS', 20];
                } elseif (str_contains($n, 'paku')) {
                    $base = 'PCS'; $extra = ['BOX', 100];
                } elseif (str_contains($n, 'keramik')) {
                    $base = 'PCS'; $extra = ['DUS', 10];
                } elseif (str_contains($n, 'cat')) {
                    $base = 'LITER'; $extra = ['DUS', 12];
                } elseif (str_contains($n, 'kabel')) {
                    $base = 'M'; $extra = ['BOX', 100];
                }

                if (!$base || !isset($uid[$base])) {
                    continue;
                }

                // Only assign a base unit when the product has none yet.
                if (!$product->base_unit_id) {
                    DB::table('products')->where('id', $product->id)->update([
                        'base_unit_id' => $uid[$base],
                        'updated_at' => $now,
                    ]);
                }

                if ($extra && isset($uid[$extra[0]]) && $uid[$extra[0]] !== $uid[$base]) {
                    DB::table('product_unit_conversions')->updateOrInsert(
                        ['product_id' => $product->id, 'unit_id' => $uid[$extra[0]]],
                        [
                            'conversion_factor' => $extra[1],
                            'is_default_purchase' => 1,
                            'is_default_sale' => 0,
                            'is_active' => 1,
                            'updated_at' => $now,
                            'created_at' => $now,
                        ]
                    );
                }
            }
        });
    }
}
