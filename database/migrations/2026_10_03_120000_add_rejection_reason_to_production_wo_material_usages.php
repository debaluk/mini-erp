<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_wo_material_usages', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('production_wo_material_usages', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });
    }
};
