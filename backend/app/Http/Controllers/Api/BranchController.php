<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Services\BranchService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BranchController extends Controller
{
    use ApiResponse;

    public function __construct(private BranchService $branches)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->branches->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'tenant', 'branch_type', 'is_active',
        ]));

        return $this->success(
            BranchResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        $branch = $this->branches->create($request->validated(), $request);

        return $this->created(new BranchResource($branch), 'Branch created successfully.');
    }

    public function show(Branch $branch): JsonResponse
    {
        $detail = $this->branches->detail($branch);

        return $this->success([
            'branch' => new BranchResource($detail['branch']),
            'stats' => $detail['stats'],
            'table_summary' => $detail['table_summary'],
        ]);
    }

    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        $validated = $request->validated();

        // Same pattern as TenantController::updateProfile()'s logo — a
        // newly-uploaded file always wins over a hand-typed URL. Based off
        // whatever's already in $validated (or, failing that, the branch's
        // current settings) rather than assigned bare, so a file upload
        // alone never wipes the other customer_display_settings keys.
        if ($request->hasFile('customer_display_promo_image') || $request->hasFile('customer_display_qr_image')) {
            $settings = $validated['customer_display_settings'] ?? $branch->customer_display_settings ?? [];

            // Storage::url() (no disk specified) resolves against
            // FILESYSTEM_DISK's default ('local' here), which has no
            // explicit 'url' config and falls back to a request-context-
            // sensitive route — it came out as a bare relative path
            // (/storage/...) under real HTTP requests, breaking every
            // <img> tag. The 'public' disk's own 'url' config is a plain
            // string concat (env('APP_URL').'/storage'), always absolute —
            // ask for it explicitly rather than relying on whichever disk
            // happens to be default.
            if ($request->hasFile('customer_display_promo_image')) {
                $path = $request->file('customer_display_promo_image')->store('customer-display', 'public');
                $settings['promo_image_url'] = Storage::disk('public')->url($path);
            }
            if ($request->hasFile('customer_display_qr_image')) {
                $path = $request->file('customer_display_qr_image')->store('customer-display', 'public');
                $settings['merchant_qr_image_url'] = Storage::disk('public')->url($path);
            }

            $validated['customer_display_settings'] = $settings;
        }

        $branch = $this->branches->update($branch, $validated);

        return $this->success(new BranchResource($branch), 'Branch updated successfully.');
    }

    public function destroy(Branch $branch): JsonResponse
    {
        $this->branches->delete($branch);

        return $this->noContent('Branch deleted successfully.');
    }

    public function toggleOpen(Branch $branch): JsonResponse
    {
        $branch = $this->branches->toggleOpen($branch);

        return $this->success(['id' => $branch->id, 'is_open' => $branch->is_open]);
    }
}
