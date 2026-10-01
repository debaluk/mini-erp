<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->unsignedBigInteger('stock_opname_id')->nullable()->after('warehouse_id');
            $table->foreign('stock_opname_id', 'fk_sa_stock_opname')
                ->references('id')
                ->on('stock_opnames')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->dropForeign('fk_sa_stock_opname');
            $table->dropColumn('stock_opname_id');
        });
    }
};
