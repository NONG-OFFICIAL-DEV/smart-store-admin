<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CashTransactionResource;
use App\Repositories\Contracts\CashTransactionRepositoryInterface;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Read-only — cash in/out are created through CashDrawerController's
// cashIn()/cashOut() actions (which enforce the shift-open/balance
// validation), never through a raw POST here. No update/destroy at all:
// this is an append-only audit log per the spec ("never edit or delete
// completed cash transactions").
class CashTransactionController extends Controller
{
    use ApiResponse;

    public function __construct(private CashTransactionRepositoryInterface $repository)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->repository->paginateServer($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'cash_drawer_id', 'type',
        ]));

        return $this->success(
            CashTransactionResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }
}
