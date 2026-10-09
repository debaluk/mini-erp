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
                ->after('business_unit_id')
                ->constrained('business_units')
                ->restrictOnDelete();
            $table->index(['to_business_unit_id', 'transfer_date'], 'stock_transfers_to_bu_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropIndex('stock_transfers_to_bu_date_idx');
            $table->dropConstrainedForeignId('to_business_unit_id');
        });
    }
};
