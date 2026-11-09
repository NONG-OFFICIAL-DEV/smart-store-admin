<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            // How long a forgotten kitchen ticket (any status) sits before
            // AutoServeStaleKitchenTickets marks its order served on its own —
            // configurable per branch since some run a much faster/slower
            // service than others. Defaults to 12h, matching the command's
            // own fallback.
            $table->unsignedSmallInteger('kitchen_auto_serve_hours')->default(12);
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('kitchen_auto_serve_hours');
        });
    }
};
