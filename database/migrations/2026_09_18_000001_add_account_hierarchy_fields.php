<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $t) {
            $t->unsignedTinyInteger('level')->default(1)->after('name');
            $t->enum('normal_balance', ['debit', 'credit'])->default('debit')->after('type');
            $t->boolean('is_postable')->default(false)->after('parent_id');
            $t->boolean('is_cash_bank')->default(false)->after('is_postable');
            $t->text('description')->nullable()->after('is_cash_bank');
            $t->index(['entity_id', 'level', 'is_active']);
            $t->index(['entity_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $t) {
            $t->dropIndex(['chart_of_accounts_entity_id_level_is_active_index']);
            $t->dropIndex(['chart_of_accounts_entity_id_parent_id_index']);
            $t->dropColumn(['level', 'normal_balance', 'is_postable', 'is_cash_bank', 'description']);
        });
    }
};
