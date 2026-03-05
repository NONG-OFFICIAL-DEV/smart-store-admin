<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 15), 100);
        $query = Tenant::query();
        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }
        $query->orderBy($request->get('sort_by', 'created_at'), $request->get('sort_order', 'desc'));
        $items = $query->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Tenants retrieved successfully.',
            'data'    => $items,
        ], 200);
    }

    /**
     * Create tenant + owner user in one transaction.
     * Only super admin can call this (super.admin middleware on route).
     */
    public function store(Request $request)
    {
        $request->validate([
            // ── Owner account ─────────────────────────────────
            'owner_first_name' => 'required|string|max:80',
            'owner_last_name'  => 'required|string|max:80',
            'owner_email'      => 'required|email|unique:users,email',
            'owner_password'   => 'required|string|min:6',
            'owner_phone'      => 'nullable|string|max:30',

            // ── Tenant info ───────────────────────────────────
            'name'          => 'required|string|max:150',
            'plan'          => 'nullable|in:free,starter,pro,enterprise',
            'timezone'      => 'nullable|string|max:60',
            'currency'      => 'nullable|string|size:3',
            'locale'        => 'nullable|string|max:10',
            'primary_color' => 'nullable|string|max:7',
            'logo_url'      => 'nullable|url',
        ]);

        return DB::transaction(function () use ($request) {

            // Step 1 — create the owner user account
            $owner = User::create([
                'first_name' => $request->owner_first_name,
                'last_name'  => $request->owner_last_name,
                'email'      => $request->owner_email,
                'password'   => $request->owner_password, // cast: 'hashed' in User model
                'phone'      => $request->owner_phone,
                'is_active'  => true,
            ]);

            // Step 2 — create the tenant
            // Pass as ARRAY so owner_user_id is set by us, never from request
            $tenant = Tenant::store([
                'name'          => $request->name,
                'slug'          => $this->generateSlug($request->name),
                'plan'          => $request->plan          ?? 'free',
                'timezone'      => $request->timezone      ?? 'UTC',
                'currency'      => $request->currency      ?? 'USD',
                'locale'        => $request->locale        ?? 'en-US',
                'primary_color' => $request->primary_color,
                'logo_url'      => $request->logo_url,
                'owner_user_id' => $owner->id,   // ← set explicitly, never from request
                'is_active'     => true,
            ]);


            return response()->json([
                'success' => true,
                'message'    => "Create tenant successfully",
            ], 201);
        });
    }

    /**
     * Display a single tenant.
     */
    public function show(string $id)
    {
        $tenant = Tenant::with('owner')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => $tenant,
        ]);
    }

    /**
     * Update tenant info only.
     * owner_user_id is blocked in Tenant::store() when called with Request.
     * Use transferOwnership() to change owner.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name'           => 'sometimes|string|max:150',
            'plan'           => 'sometimes|in:free,starter,pro,enterprise',
            'plan_expires_at'=> 'nullable|date',
            'timezone'       => 'nullable|string|max:60',
            'currency'       => 'nullable|string|size:3',
            'locale'         => 'nullable|string|max:10',
            'primary_color'  => 'nullable|string|max:7',
            'logo_url'       => 'nullable|url',
            'is_active'      => 'boolean',
            // ❌ owner_user_id intentionally excluded
            // ❌ slug intentionally excluded — use separate endpoint if needed
        ]);

        // Passes Request → Tenant::store() blocks owner_user_id automatically
        return Tenant::store($request, $id);
    }

    /**
     * Suspend / activate a tenant.
     */
    public function toggleActive(string $id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->is_active = !$tenant->is_active;
        $tenant->save();

        return response()->json([
            'success' => true,
            'data'    => $tenant->load('owner'),
            'message' => $tenant->is_active ? 'Tenant activated' : 'Tenant suspended',
        ]);
    }

    /**
     * Transfer ownership to a different user.
     * Separate explicit endpoint — never part of regular update.
     */
    public function transferOwnership(Request $request, string $id)
    {
        $request->validate([
            'new_owner_user_id' => 'required|uuid|exists:users,id',
        ]);

        $tenant = Tenant::findOrFail($id);

        $tenant->owner_user_id = $request->new_owner_user_id;
        $tenant->save();

        return response()->json([
            'success' => true,
            'data'    => $tenant->load('owner'),
            'message' => 'Ownership transferred',
        ]);
    }

    /**
     * Delete tenant — cascades to all related data.
     */
    public function destroy(string $id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tenant deleted',
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function generateSlug(string $name): string
    {
        $slug  = Str::slug($name);
        $count = Tenant::where('slug', 'like', $slug . '%')->count();

        return $count ? "{$slug}-{$count}" : $slug;
    }
}
