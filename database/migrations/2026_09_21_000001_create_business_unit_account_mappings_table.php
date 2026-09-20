<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_unit_account_mappings')) return;

        Schema::create('business_unit_account_mappings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('entity_id')->constrained();
            $t->foreignId('business_unit_id')->constrained('business_units')->cascadeOnDelete();
            $t->foreignId('account_id')->constrained('chart_of_accounts');
            $t->string('mapping_key', 50);
            $t->timestamps();
            $t->unique(['business_unit_id', 'mapping_key']);
            $t->index(['entity_id', 'account_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('business_unit_account_mappings'); }
};