<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRefundRequest;
use App\Http\Resources\RefundResource;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\RefundService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RefundController extends Controller
{
    use ApiResponse;

    public function __construct(private RefundService $refunds)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->refunds->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'status',
        ]));

        return $this->paginated($paginator);
    }

    public function show(Refund $refund): JsonResponse
    {
        return $this->success(new RefundResource($refund));
    }

    // Only reachable via POST payments/{payment}/refund — refunds are not
    // a flat, independently-creatable resource (see routes/api.php).
    public function store(StoreRefundRequest $request, Payment $payment): JsonResponse
    {
        try {
            $refund = $this->refunds->create($payment, $request->validated());
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'VALIDATION_FAILED');
        }

        return $this->created(new RefundResource($refund), 'Refund processed successfully.');
    }

    private function paginated($paginator): JsonResponse
    {
        return $this->success(
            RefundResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }
}
