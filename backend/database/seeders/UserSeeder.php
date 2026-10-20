<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        \App\Models\User::firstOrCreate(
            ['email' => 'admin@smartstore.com'],
            [
                'first_name'    => 'Super',
                'last_name'     => 'Admin',
                'password_hash' => Hash::make('Admin@1234'),
                'is_super_admin' => true,
                'is_admin'      => true,
                'is_active'     => true,
            ]
        );
    }
}
