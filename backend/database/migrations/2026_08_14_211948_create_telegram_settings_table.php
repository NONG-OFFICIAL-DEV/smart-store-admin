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
        // Single-row platform config — no tenant scoping, no key/value
        // shape needed, just the 2 fields this actually manages. See
        // TelegramSetting::current().
        Schema::create('telegram_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('bot_token')->nullable();
            $table->string('bot_username')->nullable();
            $table->timestampsTz();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_settings');
    }
};
