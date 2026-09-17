<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->string('email')->unique()->after('name');
            $table->string('password')->after('email');
            $table->enum('role', [
                'owner',
                'admin',
                'kasir',
                'inventori',
                'akuntansi',
            ])->default('admin')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->rememberToken();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropColumn([
                'name',
                'email',
                'password',
                'role',
                'is_active',
                'remember_token',
            ]);
        });
    }
};
