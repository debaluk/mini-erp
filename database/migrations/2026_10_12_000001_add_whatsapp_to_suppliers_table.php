<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('suppliers', 'whatsapp')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->string('whatsapp', 50)->nullable()->after('phone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('suppliers', 'whatsapp')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropColumn('whatsapp');
            });
        }
    }
};
