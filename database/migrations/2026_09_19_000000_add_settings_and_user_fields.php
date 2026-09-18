<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $t) {
            $t->string('email')->nullable()->after('phone');
            $t->string('npwp')->nullable()->after('email');
            $t->string('nib')->nullable()->after('npwp');
            $t->string('logo_path')->nullable()->after('nib');
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('entity_id')
                ->nullable()
                ->after('role')
                ->constrained('entities')
                ->nullOnDelete();

            $t->index(['entity_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropForeign(['entity_id']);
            $t->dropIndex(['entity_id', 'role']);
            $t->dropColumn('entity_id');
        });

        Schema::table('entities', function (Blueprint $t) {
            $t->dropColumn(['email', 'npwp', 'nib', 'logo_path']);
        });
    }
};
