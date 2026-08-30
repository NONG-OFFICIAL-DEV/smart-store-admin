<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\TransferTenantOwnershipRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Services\TenantService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TenantController extends Controller
{
    use ApiResponse;

    public function __construct(private TenantService $tenants)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->tenants->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'business_type', 'plan', 'is_active',
        ]));

        return $this->success(
            TenantResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function show(Request $request, Tenant $tenant): JsonResponse
    {
        // This route isn't superadmin-gated (owners/staff need to read
        // their own tenant's profile) — enforce tenant isolation here
        // instead, otherwise any authenticated user could view another
        // tenant's invoices/owner PII by guessing a UUID.
        if (! $this->tenants->isVisibleTo($tenant, $request->user())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $this->success($this->tenants->detail($tenant));
    }

    public function edit(Tenant $tenant): JsonResponse
    {
        return $this->success($this->tenants->editShape($tenant));
    }

    public function toggleActive(Tenant $tenant): JsonResponse
    {
        $tenant = $this->tenants->toggleActive($tenant);

        return $this->success($tenant, $tenant->is_active ? 'Tenant activated' : 'Tenant suspended');
    }

    public function resetOwnerPassword(Tenant $tenant): JsonResponse
    {
        try {
            $temporaryPassword = $this->tenants->resetOwnerPassword($tenant);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 404, $e->errors(), 'NO_OWNER_ACCOUNT');
        }

        return $this->success(['temporary_password' => $temporaryPassword]);
    }

    public function transferOwnership(TransferTenantOwnershipRequest $request, Tenant $tenant): JsonResponse
    {
        try {
            $tenant = $this->tenants->transferOwnership($tenant, $request->validated());
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'TRANSFER_FAILED');
        }

        return $this->success($tenant, 'Ownership transferred successfully');
    }

    /**
     * Delete tenant — cascades to all related data.
     */
    public function destroy(Tenant $tenant): JsonResponse
    {
        $this->tenants->delete($tenant);

        return $this->success(null, 'Tenant deleted');
    }

    /**
     * CREATE (Admin creates tenant + owner in one step).
     */
    public function store(StoreTenantRequest $request): JsonResponse
    {
        $logoUrl = null;
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
            $logoUrl = Storage::url($path);
        }

        // Same route serves both the superadmin-only admin panel (POST
        // /tenants, authenticated) and the public self-service signup form
        // (POST /business-register, no auth at all) — distinguished here by
        // whether a user is actually authenticated, since StoreTenantRequest
        // itself must authorize() both.
        if (! $request->user()) {
            $result = $this->tenants->registerSelfService($request->validated(), $logoUrl, $request);

            return $this->created($result, 'Account created successfully');
        }

        $result = $this->tenants->create($request->validated(), $logoUrl);

        return $this->created($result, 'Tenant created successfully');
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): JsonResponse
    {
        $this->tenants->update($tenant, $request->validated());

        return $this->success(null, 'Tenant updated successfully');
    }

    public function getSubscriptionByTenant(Request $request, Tenant $tenant): JsonResponse
    {
        // Same tenant-isolation rule as show() — this exposes invoices/plan
        // history, so it must not be readable across tenants.
        if (! $this->tenants->isVisibleTo($tenant, $request->user())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $this->success($this->tenants->subscriptionDetail($tenant));
    }
}
