<?php

use App\Models\Branch;
use Illuminate\Support\Facades\Broadcast;

/*
 * Every PK in this app is a UUID string — (int) casts both sides to 0,
 * making this always true and letting any authenticated user subscribe to
 * any other user's private notification channel. Fixed to a string compare
 * (same bug, same fix, already found in photo-studio-saas's scaffolded
 * version of this exact file).
 */
Broadcast::channel('App.Models.User.{id}', function ($user, string $id) {
    return (string) $user->id === $id;
});

/*
 * Kitchen Display / Orders Management subscribe here for live new-order
 * pushes (see NewOrderPlaced). Staff are pinned to one branch
 * (Staff::branch_id); an owner has no single branch_id but can switch
 * between any branch their tenant owns (authStore's activeBranchId) — so
 * both shapes need checking, not just a direct id compare.
 */
Broadcast::channel('branch.{branchId}.orders', function ($user, string $branchId) {
    if ($user->is_super_admin) {
        return true;
    }

    if ($tenant = $user->ownedTenant) {
        return Branch::withoutGlobalScopes()
            ->where('id', $branchId)
            ->where('tenant_id', $tenant->id)
            ->exists();
    }

    return (string) $user->staff?->branch_id === $branchId;
});

/*
 * Staff-side auth for the customer-display channel — used if a staff
 * member's own browser ever subscribes (e.g. a "display connected?"
 * indicator in Settings). The paired TABLET itself never goes through
 * this: it has no JWT, so it authorizes via device_token against
 * CustomerDisplayController::broadcastingAuth() instead (a separate,
 * unauthenticated route — see routes/api.php's v1/public group).
 */
Broadcast::channel('branch.{branchId}.terminal.{terminalId}.display', function ($user, string $branchId, string $terminalId) {
    if ($user->is_super_admin) {
        return true;
    }

    if ($tenant = $user->ownedTenant) {
        return Branch::withoutGlobalScopes()
            ->where('id', $branchId)
            ->where('tenant_id', $tenant->id)
            ->exists();
    }

    return (string) $user->staff?->branch_id === $branchId;
});
