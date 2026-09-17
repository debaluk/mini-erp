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
            $table->string('role')->default('staff')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('email_verified_at')->nullable()->after('is_active');
            $table->rememberToken()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'name', 'email', 'password', 'role', 'is_active',
                'email_verified_at', 'remember_token'
            ]);
        });
    }
};
