<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlanRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Services\PlanService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PlanController extends Controller
{
    use ApiResponse;

    public function __construct(private PlanService $plans)
    {
    }

    public function index(): JsonResponse
    {
        return $this->success(PlanResource::collection($this->plans->list()));
    }

    public function publicPlans(Request $request): JsonResponse
    {
        return $this->success(PlanResource::collection($this->plans->publicPlans($request->boolean('all'))));
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = $this->plans->create($request->validated());

        return $this->created(new PlanResource($plan), 'Plan created successfully.');
    }

    public function show(Plan $plan): JsonResponse
    {
        return $this->success(new PlanResource($plan->load(['billingCycles', 'features'])));
    }

    public function update(UpdatePlanRequest $request, Plan $plan): JsonResponse
    {
        $plan = $this->plans->update($plan, $request->validated());

        return $this->success(new PlanResource($plan), 'Plan updated successfully.');
    }

    public function destroy(Plan $plan): JsonResponse
    {
        try {
            $this->plans->delete($plan);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'PLAN_HAS_ACTIVE_SUBSCRIPTIONS');
        }

        return $this->noContent('Plan deleted successfully.');
    }

    public function toggleActive(Plan $plan): JsonResponse
    {
        $plan = $this->plans->toggleActive($plan);

        return $this->success(new PlanResource($plan));
    }
}
