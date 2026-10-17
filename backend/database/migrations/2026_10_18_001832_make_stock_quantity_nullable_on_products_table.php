<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * products.stock_quantity was added NOT NULL DEFAULT 0, but the
     * frontend explicitly sends `null` for food products (stock doesn't
     * apply to a made-to-order item, same as reorder_level which is
     * already nullable) — every food product create/update 500'd on this
     * constraint. Raw SQL rather than ->nullable()->change() since this
     * app doesn't have doctrine/dbal installed.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE products ALTER COLUMN stock_quantity DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('UPDATE products SET stock_quantity = 0 WHERE stock_quantity IS NULL');
        DB::statement('ALTER TABLE products ALTER COLUMN stock_quantity SET NOT NULL');
    }
};
