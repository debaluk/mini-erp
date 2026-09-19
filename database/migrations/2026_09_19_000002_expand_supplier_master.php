<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('category')->nullable()->after('name');
            $table->string('whatsapp')->nullable()->after('phone');
            $table->string('email')->nullable()->after('whatsapp');
            $table->string('website')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['category', 'whatsapp', 'email', 'website']);
        });
    }
};
