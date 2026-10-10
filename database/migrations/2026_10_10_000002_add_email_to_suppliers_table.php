<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('suppliers', 'email')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->string('email')->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('suppliers', 'email')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $table->dropColumn('email');
            });
        }
    }
};
