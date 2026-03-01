<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\DB; // ← Add this line
use Illuminate\Database\Seeder;

class TableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('tables')->insert([
            ['table_number' => 'T1', 'is_available' => true],
            ['table_number' => 'T2', 'is_available' => true],
            ['table_number' => 'T3', 'is_available' => true],
            ['table_number' => 'T4', 'is_available' => true],
            ['table_number' => 'T5', 'is_available' => true],
        ]);
    }
}
