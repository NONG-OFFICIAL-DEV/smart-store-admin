<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            // Food-category tenants get a kitchen_display_tickets row on
            // every order (see CoffeePOSorderController/OrderController) —
            // branches that don't actually staff a Kitchen Display just
            // accumulate tickets stuck in 'new' forever with no one to
            // progress them. Defaults true so existing branches keep
            // today's behavior unchanged.
            $table->boolean('kitchen_ticket_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('kitchen_ticket_enabled');
        });
    }
};
