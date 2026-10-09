<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->foreignId('to_business_unit_id')
                ->nullable()
                ->after('business_unit_id');

            // Create the supporting index before adding the foreign key.
            $table->index(['to_business_unit_id', 'transfer_date'], 'stock_transfers_to_bu_date_idx');
            $table->foreign('to_business_unit_id')
                ->references('id')
                ->on('business_units')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropForeign(['to_business_unit_id']);
            $table->dropIndex('stock_transfers_to_bu_date_idx');
            $table->dropColumn('to_business_unit_id');
        });
    }
};
