<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 🔹 Add business_type_id to tenants
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'business_type_id')) {
                $table->foreignUuid('business_type_id')
                    ->nullable()
                    ->after('plan')
                    ->constrained('business_types')
                    ->nullOnDelete();
            }
        });

        // 🔹 Add branch_type_id to branches
        Schema::table('branches', function (Blueprint $table) {
            if (!Schema::hasColumn('branches', 'branch_type_id')) {
                $table->foreignUuid('branch_type_id')
                    ->nullable()
                    ->after('type')
                    ->constrained('branch_types')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        // 🔻 rollback tenants
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'business_type_id')) {
                $table->dropForeign(['business_type_id']);
                $table->dropColumn('business_type_id');
            }
        });

        // 🔻 rollback branches
        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'branch_type_id')) {
                $table->dropForeign(['branch_type_id']);
                $table->dropColumn('branch_type_id');
            }
        });
    }
};
