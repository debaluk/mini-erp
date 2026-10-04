<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bom_items', function (Blueprint $table) {
            $table->unsignedBigInteger('unit_id')->nullable()->after('product_id');
            $table->index('unit_id', 'bom_items_unit_id_index');
        });

        DB::statement('
            UPDATE bom_items bi
            INNER JOIN products p ON p.id = bi.product_id
            SET bi.unit_id = p.base_unit_id
            WHERE bi.unit_id IS NULL
        ');

        DB::statement('ALTER TABLE bom_items MODIFY unit_id BIGINT UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        Schema::table('bom_items', function (Blueprint $table) {
            $table->dropIndex('bom_items_unit_id_index');
            $table->dropColumn('unit_id');
        });
    }
};
