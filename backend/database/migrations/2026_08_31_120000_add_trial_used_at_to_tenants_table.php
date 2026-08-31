<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Persistent "has this tenant ever been granted a trial" marker.
//
// Before this column existed, TenantSubscriptionService::changePlan() decided
// whether to start a new trial by checking only for a currently active/trial
// TenantSubscription row. Once a trial lapses (status -> 'suspended') or is
// cancelled (status -> 'cancelled'), that row falls out of the check, so the
// same tenant could get a brand-new trial_ends_at just by calling
// change-plan again. This column is set once, the first time a trial is
// granted, and is never cleared — so it survives cancellation/suspension and
// blocks re-granting a trial for that tenant permanently.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('trial_used_at')->nullable()->after('plan_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('trial_used_at');
        });
    }
};
