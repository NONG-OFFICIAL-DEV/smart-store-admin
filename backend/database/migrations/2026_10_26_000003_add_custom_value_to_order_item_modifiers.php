<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_item_modifiers', function (Blueprint $table) {
            $table->string('custom_value', 50)->nullable()->after('option_name');
        });

        // A free-typed custom value (e.g. "Sugar 80%") has no corresponding
        // modifier_options row to point at — modifier_option_id must become
        // optional to represent that line. nullOnDelete to match the
        // variant_id snapshot precedent on this same table family (losing
        // the FK on a later option delete shouldn't block the delete or
        // corrupt historical order data — option_name/custom_value already
        // carry the real snapshot).
        Schema::table('order_item_modifiers', function (Blueprint $table) {
            $table->dropForeign(['modifier_option_id']);
        });
        Schema::table('order_item_modifiers', function (Blueprint $table) {
            $table->uuid('modifier_option_id')->nullable()->change();
            $table->foreign('modifier_option_id')->references('id')->on('modifier_options')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_item_modifiers', function (Blueprint $table) {
            $table->dropForeign(['modifier_option_id']);
        });
        Schema::table('order_item_modifiers', function (Blueprint $table) {
            $table->uuid('modifier_option_id')->nullable(false)->change();
            $table->foreign('modifier_option_id')->references('id')->on('modifier_options')->restrictOnDelete();
            $table->dropColumn('custom_value');
        });
    }
};
