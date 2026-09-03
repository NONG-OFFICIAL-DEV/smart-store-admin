<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// System categories are authored by super admin and shared with every
// tenant of a matching business type (see category_business_type); a
// tenant's own categories stay is_system = false and are only ever visible
// to them, via the existing category_tenant pivot.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_system')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('is_system');
        });
    }
};
