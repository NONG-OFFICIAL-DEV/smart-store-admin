<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // Null/0 = no trial offered. A tenant only ever gets a trial on
            // their FIRST subscription (see TenantSubscriptionService::changePlan),
            // never by switching plans later.
            $table->unsignedSmallInteger('trial_days')->nullable()->after('api_limit');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('trial_days');
        });
    }
};
