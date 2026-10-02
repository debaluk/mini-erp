<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_return_items', function (Blueprint $t) {
            $t->string('condition')->default('good')->after('return_value');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_return_items', function (Blueprint $t) {
            $t->dropColumn('condition');
        });
    }
};
