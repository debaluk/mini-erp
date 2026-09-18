<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $t) {
            $t->text('address')->nullable()->after('name');
            $t->string('phone')->nullable()->after('address');
            $t->string('email')->nullable()->after('phone');
            $t->string('npwp')->nullable()->after('email');
            $t->string('nib')->nullable()->after('npwp');
            $t->string('logo_path')->nullable()->after('nib');
        });

        Schema::table('users', function (Blueprint $t) {
            $t->string('name')->after('id');
            $t->string('email')->unique()->after('name');
            $t->timestamp('email_verified_at')->nullable()->after('email');
            $t->string('password')->after('email_verified_at');
            $t->rememberToken();
            $t->string('role')->default('owner')->after('password');
            $t->foreignId('entity_id')->nullable()->after('role')->constrained('entities')->nullOnDelete();
            $t->boolean('is_active')->default(true)->after('entity_id');
            $t->index(['entity_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropForeign(['entity_id']);
            $t->dropIndex(['entity_id', 'role']);
            $t->dropColumn(['name', 'email', 'email_verified_at', 'password', 'remember_token', 'role', 'entity_id', 'is_active']);
        });

        Schema::table('entities', function (Blueprint $t) {
            $t->dropColumn(['address', 'phone', 'email', 'npwp', 'nib', 'logo_path']);
        });
    }
};
