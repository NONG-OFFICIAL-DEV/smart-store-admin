<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        $this->call([
            // RoleSeeder::class,
            // UserSeeder::class,
            // PurchaseStatusSeeder::class,
            // TableSeeder::class,
            // Add other seeders here
        ]);
        \App\Models\User::firstOrCreate(
            ['email' => 'adminstore@example.com'],
            ['first_name' => 'Admin', 'last_name' => 'Store', 'password_hash' => bcrypt('admin1234')]
        );
    }
}
