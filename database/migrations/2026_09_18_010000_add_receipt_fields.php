<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $t) {
            $t->text('address')->nullable()->after('name');
            $t->string('phone')->nullable()->after('address');
        });

        Schema::table('payments', function (Blueprint $t) {
            $t->decimal('paid_amount', 18, 2)->default(0)->after('amount');
            $t->decimal('change_amount', 18, 2)->default(0)->after('paid_amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $t) {
            $t->dropColumn(['paid_amount', 'change_amount']);
        });

        Schema::table('entities', function (Blueprint $t) {
            $t->dropColumn(['address', 'phone']);
        });
    }
};
