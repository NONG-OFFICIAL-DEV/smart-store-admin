<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->boolean('allow_custom_value')->default(false)->after('is_required');
            $table->string('custom_suffix', 10)->nullable()->after('allow_custom_value');
        });

        Schema::table('modifier_options', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_available');
        });
    }

    public function down(): void
    {
        Schema::table('modifier_options', function (Blueprint $table) {
            $table->dropColumn('is_default');
        });

        Schema::table('modifier_groups', function (Blueprint $table) {
            $table->dropColumn(['allow_custom_value', 'custom_suffix']);
        });
    }
};
