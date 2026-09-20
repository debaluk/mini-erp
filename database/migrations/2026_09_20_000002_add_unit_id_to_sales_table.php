<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('customer_id')->constrained('business_units')->nullOnDelete();
            $table->index(['entity_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropIndex(['entity_id', 'unit_id']);
            $table->dropColumn('unit_id');
        });
    }
};
