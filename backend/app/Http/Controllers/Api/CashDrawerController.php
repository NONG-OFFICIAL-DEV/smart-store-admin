<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CloseCashDrawerRequest;
use App\Http\Requests\OpenCashDrawerRequest;
use App\Http\Requests\UpdateCashDrawerRequest;
use App\Http\Resources\CashDrawerResource;
use App\Models\CashDrawer;
use App\Services\CashDrawerService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashDrawerController extends Controller
{
    use ApiResponse;

    public function __construct(private CashDrawerService $cashDrawers)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->cashDrawers->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'branch_id', 'staff_id', 'is_open',
        ]));

        return $this->success(
            CashDrawerResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(OpenCashDrawerRequest $request): JsonResponse
    {
        return $this->open($request);
    }

    public function open(OpenCashDrawerRequest $request): JsonResponse
    {
        $drawer = $this->cashDrawers->open($request->validated());

        return $this->created(new CashDrawerResource($drawer), 'Cash drawer opened successfully.');
    }

    public function show(CashDrawer $cash_drawer): JsonResponse
    {
        return $this->success(new CashDrawerResource($cash_drawer));
    }

    public function update(UpdateCashDrawerRequest $request, CashDrawer $cash_drawer): JsonResponse
    {
        $cash_drawer = $this->cashDrawers->update($cash_drawer, $request->validated());

        return $this->success(new CashDrawerResource($cash_drawer), 'Cash drawer updated successfully.');
    }

    public function close(CloseCashDrawerRequest $request, CashDrawer $drawer): JsonResponse
    {
        $drawer = $this->cashDrawers->close($drawer, (float) $request->validated('actual_cash'), $request->validated('notes'));

        return $this->success(new CashDrawerResource($drawer), 'Cash drawer closed successfully.');
    }

    public function destroy(CashDrawer $cash_drawer): JsonResponse
    {
        $this->cashDrawers->delete($cash_drawer);

        return $this->noContent('Cash drawer deleted successfully.');
    }
}
