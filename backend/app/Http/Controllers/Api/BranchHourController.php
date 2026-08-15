<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchHourRequest;
use App\Http\Requests\UpdateBranchHourRequest;
use App\Http\Resources\BranchHourResource;
use App\Models\Branch;
use App\Models\BranchHour;
use App\Services\BranchHourService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class BranchHourController extends Controller
{
    use ApiResponse;

    public function __construct(private BranchHourService $hours)
    {
    }

    public function index(Branch $branch): JsonResponse
    {
        return $this->success(BranchHourResource::collection($this->hours->forBranch($branch)));
    }

    public function store(StoreBranchHourRequest $request, Branch $branch): JsonResponse
    {
        $hour = $this->hours->create($branch, $request->validated());

        return $this->created(new BranchHourResource($hour), 'Branch hour created successfully.');
    }

    public function update(UpdateBranchHourRequest $request, Branch $branch, BranchHour $hour): JsonResponse
    {
        $hour = $this->hours->update($hour, $request->validated());

        return $this->success(new BranchHourResource($hour), 'Branch hour updated successfully.');
    }

    public function destroy(Branch $branch, BranchHour $hour): JsonResponse
    {
        $this->hours->delete($hour);

        return $this->noContent('Branch hour deleted successfully.');
    }
}
