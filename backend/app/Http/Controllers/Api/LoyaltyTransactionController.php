<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoyaltyTransactionResource;
use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Services\LoyaltyTransactionService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyTransactionController extends Controller
{
    use ApiResponse;

    public function __construct(private LoyaltyTransactionService $loyaltyTransactions)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->loyaltyTransactions->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'customer_id', 'branch_id', 'type',
        ]));

        return $this->paginated($paginator);
    }

    public function byCustomer(Request $request, Customer $customer): JsonResponse
    {
        $paginator = $this->loyaltyTransactions->byCustomer($customer->id, $request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'type',
        ]));

        return $this->paginated($paginator);
    }

    public function show(LoyaltyTransaction $loyalty_transaction): JsonResponse
    {
        return $this->success(new LoyaltyTransactionResource($loyalty_transaction));
    }

    private function paginated($paginator): JsonResponse
    {
        return $this->success(
            LoyaltyTransactionResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }
}
