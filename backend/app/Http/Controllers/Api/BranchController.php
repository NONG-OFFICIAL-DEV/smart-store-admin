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
        $branch = $this->branches->update($branch, $request->validated());

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
