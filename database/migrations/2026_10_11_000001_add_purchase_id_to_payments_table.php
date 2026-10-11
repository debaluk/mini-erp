<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('payments', 'purchase_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreignId('purchase_id')
                    ->nullable()
                    ->after('sale_id')
                    ->constrained('purchases')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payments', 'purchase_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('purchase_id');
            });
        }
    }
};
