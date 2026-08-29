<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlanFeatureListingRequest;
use App\Http\Requests\UpdatePlanFeatureListingRequest;
use App\Http\Resources\PlanFeatureListingResource;
use App\Models\PlanFeatureListing;
use App\Services\PlanFeatureListingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class PlanFeatureListingController extends Controller
{
    use ApiResponse;

    public function __construct(private PlanFeatureListingService $listings)
    {
    }

    public function index(): JsonResponse
    {
        return $this->success(PlanFeatureListingResource::collection($this->listings->all()));
    }

    public function store(StorePlanFeatureListingRequest $request): JsonResponse
    {
        $listing = $this->listings->create($request->validated());

        return $this->created(new PlanFeatureListingResource($listing), 'Feature created successfully.');
    }

    public function update(UpdatePlanFeatureListingRequest $request, PlanFeatureListing $plan_feature_listing): JsonResponse
    {
        $listing = $this->listings->update($plan_feature_listing, $request->validated());

        return $this->success(new PlanFeatureListingResource($listing), 'Feature updated successfully.');
    }

    public function destroy(PlanFeatureListing $plan_feature_listing): JsonResponse
    {
        $this->listings->delete($plan_feature_listing);

        return $this->noContent('Feature deleted successfully.');
    }
}
