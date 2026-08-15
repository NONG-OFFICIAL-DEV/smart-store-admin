<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(private PaymentService $payments)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->payments->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'branch_id', 'status', 'payment_method',
        ]));

        return $this->paginated($paginator);
    }

    public function byOrder(Request $request, Order $order): JsonResponse
    {
        $paginator = $this->payments->byOrder($order, $request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'status', 'payment_method',
        ]));

        return $this->paginated($paginator);
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $payment = $this->payments->create($request->validated());

        return $this->created(new PaymentResource($payment), 'Payment recorded successfully.');
    }

    public function show(Payment $payment): JsonResponse
    {
        return $this->success(new PaymentResource($payment));
    }

    public function update(UpdatePaymentRequest $request, Payment $payment): JsonResponse
    {
        $payment = $this->payments->update($payment, $request->validated());

        return $this->success(new PaymentResource($payment), 'Payment updated successfully.');
    }

    public function destroy(Payment $payment): JsonResponse
    {
        $this->payments->delete($payment);

        return $this->noContent('Payment deleted successfully.');
    }

    private function paginated($paginator): JsonResponse
    {
        return $this->success(
            PaymentResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }
}
