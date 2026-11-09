<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A device exists (device_session_id) before it's ever paired — it
        // generates that id itself on first load and immediately listens on
        // a public channel named after it, so the claim flow never needs
        // the device to poll. branch_id/device_token/paired_at stay null
        // until a cashier claims the pairing_code from POS Settings.
        Schema::create('customer_display_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            $table->uuid('device_session_id')->unique();
            $table->string('pairing_code', 6)->nullable()->unique();
            $table->timestampTz('code_expires_at')->nullable();
            $table->string('device_token', 64)->nullable()->unique();
            $table->string('device_name', 100)->nullable();
            $table->timestampTz('paired_at')->nullable();
            $table->timestampTz('last_seen_at')->nullable();
            $table->timestampsTz();

            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_display_devices');
    }
};
