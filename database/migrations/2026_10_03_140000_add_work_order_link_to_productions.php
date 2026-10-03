<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->unsignedBigInteger('production_work_order_id')
                ->nullable()
                ->after('bom_id');

            $table->unique(
                'production_work_order_id',
                'productions_work_order_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->dropUnique('productions_work_order_unique');
            $table->dropColumn('production_work_order_id');
        });
    }
};
