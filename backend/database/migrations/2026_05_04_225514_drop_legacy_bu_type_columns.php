<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop legacy enum columns now that business_type_id and branch_type_id
     * foreign keys are the source of truth.
     */
    public function up(): void
    {
        // 🔻 Remove legacy bu_type enum from tenants
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'bu_type')) {
                $table->dropColumn('bu_type');
            }
        });

        // 🔻 Remove legacy type enum from branches
        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'type')) {
                $table->dropColumn('type');
            }
        });
    }

    /**
     * Restore the legacy columns if rolling back.
     * Values will be NULL after rollback — data was migrated to the FK columns.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'bu_type')) {
                $table->enum('bu_type', [
                    'restaurant',
                    'minimart',
                    'retail',
                    'wholesale',
                    'cafe',
                    'bakery',
                    'kiosk',
                    'food_truck',
                ])->nullable()->after('name');
            }
        });

        Schema::table('branches', function (Blueprint $table) {
            if (!Schema::hasColumn('branches', 'type')) {
                $table->enum('type', [
                    'restaurant',
                    'cafe',
                    'kiosk',
                    'food_truck',
                ])->nullable()->after('name');
            }
        });
    }
};
