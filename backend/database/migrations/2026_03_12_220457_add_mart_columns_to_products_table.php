<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {

            // ── Mart / Retail fields ───────────────────────────────────────
            $table->decimal('selling_price', 12, 2)
                  ->nullable()
                  ->after('base_price')
                  ->comment('Mart selling price. If null, use base_price');

            $table->decimal('wholesale_price', 12, 2)
                  ->nullable()
                  ->after('selling_price')
                  ->comment('Wholesale price for wholesale tenants');

            $table->decimal('stock_quantity', 12, 3)
                  ->default(0)
                  ->after('wholesale_price')
                  ->comment('Current stock level');

            $table->decimal('reorder_level', 12, 3)
                  ->nullable()
                  ->after('stock_quantity')
                  ->comment('Alert when stock falls below this');

            $table->boolean('track_stock')
                  ->default(false)
                  ->after('reorder_level')
                  ->comment('false = restaurant, true = mart/retail');

            $table->date('expiry_date')
                  ->nullable()
                  ->after('track_stock');

            $table->string('unit', 30)
                  ->nullable()
                  ->after('expiry_date')
                  ->comment('pcs, kg, litre, box...');

            // ── Index for low stock queries ────────────────────────────────
            $table->index(['tenant_id', 'track_stock', 'stock_quantity']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'track_stock', 'stock_quantity']);
            $table->dropColumn([
                'selling_price',
                'wholesale_price',
                'stock_quantity',
                'reorder_level',
                'track_stock',
                'expiry_date',
                'unit',
            ]);
        });
    }
};

/*
|─────────────────────────────────────────────────────────────────────────────
| HOW PRICE WORKS AFTER THIS MIGRATION
|─────────────────────────────────────────────────────────────────────────────
|
| Restaurant product:
|   base_price    = menu price (e.g. $5.00)
|   selling_price = null
|   track_stock   = false
|   → use base_price
|
| Mart / Retail product:
|   base_price    = cost price fallback (optional)
|   selling_price = $1.50  ← actual shelf price
|   track_stock   = true
|   stock_quantity = 100
|   → use selling_price
|
| Wholesale product:
|   selling_price   = $1.50  ← retail price
|   wholesale_price = $1.00  ← bulk price (min qty applies)
|   track_stock     = true
|   → use wholesale_price if customer is wholesale tier
|
|─────────────────────────────────────────────────────────────────────────────
*/
