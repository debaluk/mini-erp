<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Locked master item categories: Barang, Jasa, Aset.
        DB::statement("
            ALTER TABLE products
            MODIFY item_type ENUM('barang','jasa','aset')
            NOT NULL DEFAULT 'barang'
        ");

        DB::statement("
            ALTER TABLE products
            MODIFY type ENUM('raw_material','merchandise','wip','finished_goods','asset')
            NOT NULL DEFAULT 'merchandise'
        ");

        // Supplier tax identity/status is master data. Posted purchase
        // documents store their own supplier_tax_status snapshot.
        Schema::table('suppliers', function (Blueprint $t) {
            $t->string('tax_status')->default('non_pkp')->after('address');
            $t->string('npwp')->nullable()->after('tax_status');
            $t->string('nik')->nullable()->after('npwp');
            $t->index(['entity_id', 'tax_status']);
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $t) {
            $t->dropIndex(['entity_id', 'tax_status']);
            $t->dropColumn(['tax_status', 'npwp', 'nik']);
        });

        DB::statement("
            ALTER TABLE products
            MODIFY type ENUM('raw_material','merchandise','wip','finished_goods')
            NOT NULL DEFAULT 'merchandise'
        ");

        DB::statement("
            ALTER TABLE products
            MODIFY item_type ENUM('barang','jasa')
            NOT NULL DEFAULT 'barang'
        ");
    }
};
