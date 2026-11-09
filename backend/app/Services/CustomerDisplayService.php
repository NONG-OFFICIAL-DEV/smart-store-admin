<?php

namespace App\Services;

use App\Events\CustomerDisplayDeviceClaimed;
use App\Events\CustomerDisplayStateUpdated;
use App\Models\CustomerDisplayDevice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerDisplayService
{
    /**
     * Called by an unpaired device on first load (and again whenever it
     * needs a fresh code, e.g. the previous one expired). Backend owns
     * both ids so pairing_code uniqueness/retry lives in one place.
     */
    public function requestPairing(): CustomerDisplayDevice
    {
        return CustomerDisplayDevice::create([
            'device_session_id' => (string) Str::uuid(),
            'pairing_code' => $this->generateUniqueCode(),
            'code_expires_at' => now()->addMinutes(10),
        ]);
    }

    private function generateUniqueCode(): string
    {
        do {
            $code = (string) random_int(100000, 999999);
        } while (
            CustomerDisplayDevice::where('pairing_code', $code)
                ->where('code_expires_at', '>', now())
                ->exists()
        );

        return $code;
    }

    /**
     * Cashier side — POS Settings > Customer display > "Pair device". The
     * authenticated user's own branch resolution already happened in the
     * controller (ResolvesBranchContext), so this only needs the code.
     */
    public function claimPairing(string $branchId, string $code, ?string $deviceName): CustomerDisplayDevice
    {
        return DB::transaction(function () use ($branchId, $code, $deviceName) {
            $device = CustomerDisplayDevice::withoutGlobalScopes()
                ->where('pairing_code', $code)
                ->where('code_expires_at', '>', now())
                ->whereNull('paired_at')
                ->lockForUpdate()
                ->first();

            if (! $device) {
                throw ValidationException::withMessages([
                    'code' => 'That code is invalid or has expired.',
                ]);
            }

            $device->update([
                'branch_id' => $branchId,
                'device_token' => Str::random(64),
                'device_name' => $deviceName ?: 'Customer display',
                'paired_at' => now(),
                'pairing_code' => null,
                'code_expires_at' => null,
                'last_seen_at' => now(),
            ]);

            event(new CustomerDisplayDeviceClaimed(
                deviceSessionId: $device->device_session_id,
                branchId: $device->branch_id,
                deviceId: $device->id,
                deviceToken: $device->device_token,
            ));

            return $device;
        });
    }

    public function devicesForBranch(string $branchId)
    {
        return CustomerDisplayDevice::where('branch_id', $branchId)
            ->whereNotNull('paired_at')
            ->orderByDesc('paired_at')
            ->get();
    }

    public function rename(CustomerDisplayDevice $device, string $name): CustomerDisplayDevice
    {
        $device->update(['device_name' => $name]);

        return $device;
    }

    public function unpair(CustomerDisplayDevice $device): void
    {
        $device->delete();
    }

    /**
     * Relays the POS's full-state payload to the paired device — see
     * CustomerDisplayStateUpdated's docblock for why this is fire-and-
     * forget with no persistence.
     */
    public function broadcastState(string $branchId, string $deviceId, array $state): void
    {
        event(new CustomerDisplayStateUpdated($branchId, $deviceId, $state));
    }

    /**
     * Used by the device-token broadcasting-auth route (no JWT involved —
     * the tablet isn't a logged-in user). Confirms the token belongs to a
     * paired device AND the channel it's asking to join is that device's
     * own channel, before the controller hands back a signed auth response.
     */
    public function deviceForToken(string $token): ?CustomerDisplayDevice
    {
        return CustomerDisplayDevice::where('device_token', $token)
            ->whereNotNull('paired_at')
            ->first();
    }
}
