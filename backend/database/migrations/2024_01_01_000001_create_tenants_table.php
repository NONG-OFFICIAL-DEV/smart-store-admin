<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->string('slug', 100)->unique();
            $table->enum('plan', ['free', 'starter', 'pro', 'enterprise'])->default('free');
            $table->timestampTz('plan_expires_at')->nullable();
            $table->uuid('owner_user_id')->nullable(); // FK added after users table
            $table->text('logo_url')->nullable();
            $table->string('primary_color', 7)->nullable();
            $table->string('timezone', 60)->default('UTC');
            $table->char('currency', 3)->default('USD');
            $table->string('locale', 10)->default('en-US');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
