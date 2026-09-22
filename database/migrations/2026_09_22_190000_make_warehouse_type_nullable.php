<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('warehouses')
            ->where('type', 'general')
            ->update(['type' => null]);

        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('type')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        DB::table('warehouses')
            ->whereNull('type')
            ->update(['type' => 'general']);

        Schema::table('warehouses', function (Blueprint $table) {
            $table->string('type')->default('general')->nullable(false)->change();
        });
    }
};
