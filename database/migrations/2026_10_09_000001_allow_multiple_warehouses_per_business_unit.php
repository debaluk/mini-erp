<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouse_business_units', function (Blueprint $table) {
            // Add a replacement index first because MySQL may use the unique
            // index to support the business_unit_id foreign key.
            $table->index('business_unit_id', 'wbu_business_unit_idx');
            $table->dropUnique('wbu_business_unit_unique');
        });
    }

    public function down(): void
    {
        Schema::table('warehouse_business_units', function (Blueprint $table) {
            // Restore the unique index before removing the replacement index
            // so the foreign key always has a supporting index.
            $table->unique('business_unit_id', 'wbu_business_unit_unique');
            $table->dropIndex('wbu_business_unit_idx');
        });
    }
};
