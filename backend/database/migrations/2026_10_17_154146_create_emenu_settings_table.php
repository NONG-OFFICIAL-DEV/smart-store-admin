<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Single-row platform config — same shape as telegram_settings.
        // Lets a super admin repoint where table QR codes/digital-menu
        // links resolve to without a deploy. See EmenuSetting::current().
        Schema::create('emenu_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('base_url')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emenu_settings');
    }
};
