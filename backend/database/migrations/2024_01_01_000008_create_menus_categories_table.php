<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | MENUS
        |--------------------------------------------------------------------------
        | A menu belongs to a tenant.
        | Example: Breakfast Menu, Lunch Menu, Dinner Menu
        */
        Schema::create('menus', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index('tenant_id');
        });


        /*
        |--------------------------------------------------------------------------
        | BRANCH ↔ MENU ASSIGNMENT
        |--------------------------------------------------------------------------
        | Assign menus to branches
        */
        Schema::create('branch_menus', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->foreignUuid('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->foreignUuid('menu_id')
                ->constrained('menus')
                ->cascadeOnDelete();

            $table->time('available_from')->nullable();
            $table->time('available_until')->nullable();
            $table->json('days_of_week')->nullable()->comment('Array of 0-6');
            $table->smallInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->unique(['branch_id', 'menu_id']);
        });


        /*
        |--------------------------------------------------------------------------
        | CATEGORIES
        |--------------------------------------------------------------------------
        | Categories belong to TENANT (not menu!)
        | They can be reused across multiple menus.
        */
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('parent_id')->nullable();

            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->text('image_url')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('color', 7)->nullable();

            $table->smallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index('parent_id');
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('categories');
        Schema::dropIfExists('branch_menus');
        Schema::dropIfExists('menus');
    }
};
