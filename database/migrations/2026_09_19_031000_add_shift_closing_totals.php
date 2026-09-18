<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_shifts', function (Blueprint $t) {
            $t->decimal('expected_cash', 18, 2)->nullable()->after('closing_cash');
            $t->decimal('cash_difference', 18, 2)->nullable()->after('expected_cash');
        });
    }

    public function down(): void
    {
        Schema::table('cash_shifts', function (Blueprint $t) {
            $t->dropColumn(['expected_cash', 'cash_difference']);
        });
    }
};
