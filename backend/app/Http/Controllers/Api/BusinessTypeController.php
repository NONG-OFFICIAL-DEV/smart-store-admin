<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBusinessTypeRequest;
use App\Http\Requests\UpdateBusinessTypeRequest;
use App\Http\Resources\BusinessTypeResource;
use App\Models\BusinessType;
use App\Services\BusinessTypeService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class BusinessTypeController extends Controller
{
    use ApiResponse;

    public function __construct(private BusinessTypeService $businessTypes)
    {
    }

    public function index(): JsonResponse
    {
        return $this->success(BusinessTypeResource::collection($this->businessTypes->list()));
    }

    public function branchTypes(BusinessType $business_type): JsonResponse
    {
        return $this->success($this->businessTypes->branchTypesFor($business_type->id));
    }

    public function store(StoreBusinessTypeRequest $request): JsonResponse
    {
        $businessType = $this->businessTypes->create($request->validated());

        return $this->created(new BusinessTypeResource($businessType), 'Business type created successfully.');
    }

    public function show(BusinessType $business_type): JsonResponse
    {
        return $this->success(new BusinessTypeResource($business_type));
    }

    public function update(UpdateBusinessTypeRequest $request, BusinessType $business_type): JsonResponse
    {
        $business_type = $this->businessTypes->update($business_type, $request->validated());

        return $this->success(new BusinessTypeResource($business_type), 'Business type updated successfully.');
    }

    public function destroy(BusinessType $business_type): JsonResponse
    {
        $this->businessTypes->delete($business_type);

        return $this->noContent('Business type deleted successfully.');
    }
}
