<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('business_units')) {
            Schema::create('business_units', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('entity_id');
                $table->string('code', 50);
                $table->string('name', 150);
                $table->enum('business_type', ['retail', 'production', 'service']);
                $table->enum('hpp_method', ['perpetual', 'periodic']);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['entity_id', 'code']);
                $table->index(['entity_id', 'business_type']);
            });

            return;
        }

        if (!Schema::hasColumn('business_units', 'business_type')) {
            Schema::table('business_units', function (Blueprint $table) {
                $table->enum('business_type', ['retail', 'production', 'service'])
                    ->nullable()
                    ->after('name');
            });
        }

        if (!Schema::hasColumn('business_units', 'hpp_method')) {
            Schema::table('business_units', function (Blueprint $table) {
                $table->enum('hpp_method', ['perpetual', 'periodic'])
                    ->nullable()
                    ->after('business_type');
            });
        }

        // Preserve existing units and classify the known legacy codes.
        DB::table('business_units')
            ->whereNull('business_type')
            ->update([
                'business_type' => DB::raw("
                    CASE
                        WHEN UPPER(code) LIKE 'RET%' THEN 'retail'
                        WHEN UPPER(code) LIKE 'PROD%' THEN 'production'
                        WHEN UPPER(code) LIKE 'JASA%' THEN 'service'
                        ELSE 'retail'
                    END
                "),
            ]);

        DB::table('business_units')
            ->whereNull('hpp_method')
            ->update(['hpp_method' => 'perpetual']);

        // New fields are now required after legacy rows have been backfilled.
        Schema::table('business_units', function (Blueprint $table) {
            $table->enum('business_type', ['retail', 'production', 'service'])->nullable(false)->change();
            $table->enum('hpp_method', ['perpetual', 'periodic'])->nullable(false)->change();
        });

        if (!Schema::hasIndex('business_units', ['entity_id', 'business_type'])) {
            Schema::table('business_units', function (Blueprint $table) {
                $table->index(['entity_id', 'business_type']);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('business_units')) {
            return;
        }

        if (Schema::hasColumn('business_units', 'hpp_method')) {
            Schema::table('business_units', function (Blueprint $table) {
                $table->dropColumn('hpp_method');
            });
        }

        if (Schema::hasColumn('business_units', 'business_type')) {
            Schema::table('business_units', function (Blueprint $table) {
                $table->dropColumn('business_type');
            });
        }
    }
};
