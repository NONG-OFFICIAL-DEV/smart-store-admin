<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionPlanHistoryResource;
use App\Services\SubscriptionPlanHistoryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only — the report side of subscriptions, separate from
 * TenantSubscriptionController (the action side: assign/renew/cancel/
 * toggle). History rows are written by TenantSubscriptionService as a
 * side effect of those actions, never created directly here.
 */
class SubscriptionPlanHistoryController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly SubscriptionPlanHistoryService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|uuid|exists:tenants,id',
        ]);

        $paginator = $this->service->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'tenant_id',
        ]));

        return $this->success(
            SubscriptionPlanHistoryResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }
}
