<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_work_orders', function (Blueprint $table) {
            $table->dateTime('started_at')->nullable()->after('wo_date');
        });
    }

    public function down(): void
    {
        Schema::table('production_work_orders', function (Blueprint $table) {
            $table->dropColumn('started_at');
        });
    }
};
