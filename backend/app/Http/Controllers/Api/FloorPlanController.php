<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFloorPlanRequest;
use App\Http\Requests\UpdateFloorPlanRequest;
use App\Http\Resources\FloorPlanResource;
use App\Models\Branch;
use App\Models\FloorPlan;
use App\Services\FloorPlanService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FloorPlanController extends Controller
{
    use ApiResponse;

    public function __construct(private FloorPlanService $floorPlans)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->floorPlans->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'branch_id',
        ]));

        return $this->paginated($paginator);
    }

    public function byBranch(Request $request, Branch $branch): JsonResponse
    {
        $paginator = $this->floorPlans->byBranch($branch, $request->only([
            'search', 'sortBy', 'sortDesc', 'perPage',
        ]));

        return $this->paginated($paginator);
    }

    public function store(StoreFloorPlanRequest $request): JsonResponse
    {
        $floorPlan = $this->floorPlans->create($request->validated());

        return $this->created(new FloorPlanResource($floorPlan), 'Floor plan created successfully.');
    }

    public function show(FloorPlan $floor_plan): JsonResponse
    {
        return $this->success(new FloorPlanResource($floor_plan));
    }

    public function update(UpdateFloorPlanRequest $request, FloorPlan $floor_plan): JsonResponse
    {
        $floor_plan = $this->floorPlans->update($floor_plan, $request->validated());

        return $this->success(new FloorPlanResource($floor_plan), 'Floor plan updated successfully.');
    }

    public function destroy(FloorPlan $floor_plan): JsonResponse
    {
        $this->floorPlans->delete($floor_plan);

        return $this->noContent('Floor plan deleted successfully.');
    }

    private function paginated($paginator): JsonResponse
    {
        return $this->success(
            FloorPlanResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }
}
