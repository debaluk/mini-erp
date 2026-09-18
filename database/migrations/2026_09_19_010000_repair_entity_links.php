<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $entity = DB::table('entities')->orderBy('id')->first();

        if (!$entity) {
            return;
        }

        if (str_starts_with((string) $entity->code, 'ENT-')) {
            do {
                $entityCode = date('Ym', strtotime($entity->created_at ?: now()))
                    . strtoupper(Str::random(6));
            } while (
                DB::table('entities')
                    ->where('code', $entityCode)
                    ->where('id', '!=', $entity->id)
                    ->exists()
            );

            DB::table('entities')
                ->where('id', $entity->id)
                ->update([
                    'code' => $entityCode,
                    'updated_at' => now(),
                ]);
        }

        DB::table('users')
            ->whereIn('role', ['owner', 'admin', 'kasir', 'inventori', 'akuntansi'])
            ->whereNull('entity_id')
            ->update([
                'entity_id' => $entity->id,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Data repair tidak dibatalkan agar relasi user-entitas tetap aman.
    }
};
