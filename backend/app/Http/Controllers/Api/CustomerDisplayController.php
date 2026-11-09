<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CustomerDisplayDevice;
use App\Services\CustomerDisplayService;
use App\Traits\ApiResponse;
use App\Traits\ResolvesBranchContext;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CustomerDisplayController extends Controller
{
    use ApiResponse;
    use ResolvesBranchContext;

    public function __construct(private CustomerDisplayService $displays)
    {
    }

    /**
     * ResolvesBranchContext::resolveBranchId() trusts $request->branch_id
     * verbatim with no ownership check if it's present — fine for report
     * endpoints that only ever read, not for something that WRITES a
     * branch_id onto a row (claimPairing). Every other POS submit call
     * already sends its own active branch_id explicitly (see
     * FoodPos.vue/RetailPos.vue's submitOrder) — same convention here,
     * plus the check those callers get for free via TenantScope elsewhere.
     */
    private function assertBranchBelongsToCaller(string $branchId): void
    {
        $belongs = Branch::withoutGlobalScopes()
            ->where('id', $branchId)
            ->where('tenant_id', $this->resolveTenantId())
            ->exists();

        abort_unless($belongs, 403, 'That branch does not belong to you.');
    }

    /**
     * Public — an unpaired device (no login at all) calls this on load to
     * get its pairing code + the session id it'll listen on for the claim.
     */
    public function requestPairing(): JsonResponse
    {
        $device = $this->displays->requestPairing();

        return $this->success([
            'device_session_id' => $device->device_session_id,
            'pairing_code' => $device->pairing_code,
            'expires_at' => $device->code_expires_at,
        ]);
    }

    /**
     * Authenticated — POS Settings > Customer display > "Pair device".
     */
    public function claimPairing(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|size:6',
            'device_name' => 'nullable|string|max:100',
            'branch_id' => 'required|uuid',
        ]);

        $this->assertBranchBelongsToCaller($data['branch_id']);

        $device = $this->displays->claimPairing($data['branch_id'], $data['code'], $data['device_name'] ?? null);

        return $this->success([
            'id' => $device->id,
            'device_name' => $device->device_name,
            'paired_at' => $device->paired_at,
        ], 'Device paired.');
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['branch_id' => 'required|uuid']);

        $this->assertBranchBelongsToCaller($data['branch_id']);

        return $this->success($this->displays->devicesForBranch($data['branch_id']));
    }

    public function update(Request $request, CustomerDisplayDevice $device): JsonResponse
    {
        $data = $request->validate(['device_name' => 'required|string|max:100']);

        return $this->success($this->displays->rename($device, $data['device_name']), 'Device renamed.');
    }

    public function destroy(CustomerDisplayDevice $device): JsonResponse
    {
        $this->displays->unpair($device);

        return $this->success(null, 'Device unpaired.');
    }

    /**
     * Authenticated — FoodPos.vue/RetailPos.vue call this every time the
     * cart state changes. No persistence, pure relay (see
     * CustomerDisplayStateUpdated).
     */
    public function state(Request $request, string $deviceId): JsonResponse
    {
        $data = $request->validate(['state' => 'required|array']);

        $device = CustomerDisplayDevice::whereNotNull('paired_at')->findOrFail($deviceId);

        $this->displays->broadcastState($device->branch_id, $device->id, $data['state']);

        return $this->success(null);
    }

    /**
     * Public (no jwt.auth) — a paired tablet authorizes onto its own
     * private display channel using its device_token instead of a staff
     * JWT. Deliberately bypasses Broadcast::routes()/routes/channels.php
     * entirely: there's no authenticated user to resolve, so this does its
     * own access check, then hand-signs the same way PusherBroadcaster
     * would (Reverb's "reverb" driver IS a PusherBroadcaster — see
     * BroadcastManager::createReverbDriver()).
     */
    public function broadcastingAuth(Request $request, BroadcastManager $broadcastManager): Response
    {
        $data = $request->validate([
            'socket_id' => 'required|string',
            'channel_name' => 'required|string',
            'device_token' => 'required|string',
        ]);

        $device = $this->displays->deviceForToken($data['device_token']);

        abort_unless($device, 403, 'Invalid device token.');

        $expectedChannel = "private-branch.{$device->branch_id}.terminal.{$device->id}.display";

        abort_unless($data['channel_name'] === $expectedChannel, 403, 'Channel mismatch.');

        $device->update(['last_seen_at' => now()]);

        $pusher = $broadcastManager->pusher(config('broadcasting.connections.reverb'));
        $signature = $pusher->socket_auth($data['channel_name'], $data['socket_id']);

        return response($signature, 200)->header('Content-Type', 'application/json');
    }
}
