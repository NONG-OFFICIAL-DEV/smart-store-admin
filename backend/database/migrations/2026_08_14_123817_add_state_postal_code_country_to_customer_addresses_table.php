<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * These 3 columns already exist on the dev database (added at some
     * point by a migration that's no longer on disk — the `migrations`
     * table has more recorded rows than there are migration files today).
     * This formalizes that drift into a real, reproducible migration
     * instead of leaving fresh environments (tests, new setups) permanently
     * out of sync with dev. hasColumn-guarded so it's a no-op wherever the
     * columns already exist.
     */
    public function up(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_addresses', 'state')) {
                $table->string('state', 100)->nullable();
            }
            if (! Schema::hasColumn('customer_addresses', 'postal_code')) {
                $table->string('postal_code', 20)->nullable();
            }
            if (! Schema::hasColumn('customer_addresses', 'country')) {
                $table->char('country', 2)->default('US');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropColumn(['state', 'postal_code', 'country']);
        });
    }
};
