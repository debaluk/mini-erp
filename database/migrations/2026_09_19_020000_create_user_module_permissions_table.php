<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_module_permissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('module', 50);
            $t->timestamps();
            $t->unique(['user_id', 'module']);
        });

        $defaults = [
            'admin' => ['master_data', 'konfigurasi'],
            'kasir' => ['pos_retail'],
            'inventori' => ['produksi', 'armada_jasa', 'inventori'],
            'akuntansi' => ['akuntansi', 'laporan'],
        ];

        foreach ($defaults as $role => $modules) {
            $users = DB::table('users')->where('role', $role)->pluck('id');
            foreach ($users as $userId) {
                foreach ($modules as $module) {
                    DB::table('user_module_permissions')->insertOrIgnore([
                        'user_id' => $userId,
                        'module' => $module,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_module_permissions');
    }
};
