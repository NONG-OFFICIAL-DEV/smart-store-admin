<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A plain stored column instead of a `created_at::date` index
        // expression — Postgres rejects that expression in an index because
        // casting a timestamptz to date depends on the session timezone, so
        // it isn't IMMUTABLE. Set by Order::booted() alongside order_number.
        Schema::table('orders', function (Blueprint $table) {
            $table->date('order_date')->nullable()->after('order_number');
        });

        DB::statement("UPDATE orders SET order_date = (created_at AT TIME ZONE 'UTC')::date WHERE order_date IS NULL");

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['order_number']);
            // order_number now resets to ORD-0001 per branch every day (see
            // Order::generateOrderNumber()) instead of being a globally
            // unique random code — uniqueness has to include the branch and
            // the day instead of being a flat constraint on the column alone.
            $table->unique(['branch_id', 'order_number', 'order_date'], 'orders_branch_number_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_branch_number_date_unique');
            $table->unique('order_number');
            $table->dropColumn('order_date');
        });
    }
};
