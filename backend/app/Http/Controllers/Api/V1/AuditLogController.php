<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Services\ActivityLogService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use ApiResponse;

    public function __construct(private ActivityLogService $activityLogs)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->activityLogs->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'user_id', 'action', 'entity_type', 'date_from', 'date_to',
        ]));

        return $this->paginated($paginator);
    }

    public function show(ActivityLog $activityLog): JsonResponse
    {
        return $this->success(new ActivityLogResource($activityLog));
    }

    private function paginated($paginator): JsonResponse
    {
        return $this->success(
            ActivityLogResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }
}
