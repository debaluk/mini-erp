<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_work_order_costs', function (Blueprint $table) {
            $table->string('pay_type', 20)->default('borongan')->after('amount');
            $table->decimal('unit_rate', 18, 2)->nullable()->after('pay_type');
        });
    }

    public function down(): void
    {
        Schema::table('production_work_order_costs', function (Blueprint $table) {
            $table->dropColumn(['pay_type', 'unit_rate']);
        });
    }
};
