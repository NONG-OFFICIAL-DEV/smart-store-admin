<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateDailySalesSummaryRequest;
use App\Http\Resources\DailySalesSummaryResource;
use App\Models\Branch;
use App\Services\DailySalesSummaryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailySalesSummaryController extends Controller
{
    use ApiResponse;

    public function __construct(private DailySalesSummaryService $summaries)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->summaries->list($request->only([
            'sortBy', 'sortDesc', 'perPage', 'branch_id', 'date_from', 'date_to',
        ]));

        return $this->paginated($paginator);
    }

    public function byBranch(Request $request, Branch $branch): JsonResponse
    {
        $paginator = $this->summaries->byBranch($branch->id, $request->only(['sortBy', 'sortDesc', 'perPage', 'date_from', 'date_to']));

        return $this->paginated($paginator);
    }

    public function show(string $date): JsonResponse
    {
        return $this->success(DailySalesSummaryResource::collection($this->summaries->forDate($date)));
    }

    public function generate(GenerateDailySalesSummaryRequest $request, Branch $branch): JsonResponse
    {
        $summary = $this->summaries->generate($branch->id, $request->validated('date'));

        return $this->success(new DailySalesSummaryResource($summary), 'Daily sales summary generated successfully.');
    }

    public function revenue(Request $request): JsonResponse
    {
        return $this->success($this->summaries->revenue($request->only(['branch_id', 'date_from', 'date_to'])));
    }

    public function topCustomers(Request $request): JsonResponse
    {
        return $this->success($this->summaries->topCustomers($request->only(['branch_id', 'date_from', 'date_to', 'limit'])));
    }

    private function paginated($paginator): JsonResponse
    {
        return $this->success(
            DailySalesSummaryResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }
}
