<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_unit_account_mappings')) {
            Schema::create('business_unit_account_mappings', function (Blueprint $t) {
                $t->id();
                $t->foreignId('entity_id')->constrained();
                $t->foreignId('business_unit_id')->constrained('business_units')->cascadeOnDelete();
                $t->foreignId('account_id')->constrained('chart_of_accounts');
                $t->string('mapping_key', 50);
                $t->timestamps();
                $t->unique(['business_unit_id', 'mapping_key'], 'buam_unit_mapping_unique');
                $t->index(['entity_id', 'account_id']);
            });

            return;
        }

        // MySQL keeps the table when CREATE TABLE fails on an index name.
        // Make the migration safe to re-run and add the short unique index explicitly.
        $hasUnique = collect(
            \DB::select("SHOW INDEX FROM business_unit_account_mappings WHERE Key_name = 'buam_unit_mapping_unique'")
        )->isNotEmpty();

        if (! $hasUnique) {
            Schema::table('business_unit_account_mappings', function (Blueprint $t) {
                $t->unique(['business_unit_id', 'mapping_key'], 'buam_unit_mapping_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_unit_account_mappings');
    }
};