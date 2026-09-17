<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Owner',
                'email' => 'owner@minierp.local',
                'password' => 'password',
                'role' => 'owner',
            ],
            [
                'name' => 'Admin',
                'email' => 'admin@minierp.local',
                'password' => 'password',
                'role' => 'admin',
            ],
            [
                'name' => 'Kasir',
                'email' => 'kasir@minierp.local',
                'password' => 'password',
                'role' => 'kasir',
            ],
            [
                'name' => 'Inventori',
                'email' => 'inventori@minierp.local',
                'password' => 'password',
                'role' => 'inventori',
            ],
            [
                'name' => 'Akuntansi',
                'email' => 'akuntansi@minierp.local',
                'password' => 'password',
                'role' => 'akuntansi',
            ],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make($data['password']),
                    'role' => $data['role'],
                    'is_active' => true,
                ]
            );
        }
    }
}
