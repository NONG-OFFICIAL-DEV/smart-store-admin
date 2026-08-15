<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReceiveMartPurchaseOrderRequest;
use App\Http\Requests\StoreMartPurchaseOrderRequest;
use App\Http\Requests\UpdateMartPurchaseOrderRequest;
use App\Http\Resources\MartPurchaseOrderResource;
use App\Models\MartPurchaseOrder;
use App\Services\MartPurchaseOrderService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MartPurchaseOrderController extends Controller
{
    use ApiResponse;

    public function __construct(private MartPurchaseOrderService $purchaseOrders)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->purchaseOrders->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'branch_id', 'status',
        ]));

        return $this->success(
            MartPurchaseOrderResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreMartPurchaseOrderRequest $request): JsonResponse
    {
        $order = $this->purchaseOrders->create($request->validated());

        return $this->created(new MartPurchaseOrderResource($order), 'Purchase order created successfully.');
    }

    public function show(MartPurchaseOrder $mart_purchase_order): JsonResponse
    {
        return $this->success(new MartPurchaseOrderResource(
            $mart_purchase_order->load([
                'supplier:id,name',
                'branch:id,name',
                'items.product:id,name,image_url,stock_quantity,unit',
                'items.productUnit:id,unit_name,unit_label,qty_per_base',
            ])
        ));
    }

    public function update(UpdateMartPurchaseOrderRequest $request, MartPurchaseOrder $mart_purchase_order): JsonResponse
    {
        try {
            $mart_purchase_order = $this->purchaseOrders->update($mart_purchase_order, $request->validated());
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'PO_NOT_EDITABLE');
        }

        return $this->success(new MartPurchaseOrderResource($mart_purchase_order), 'Purchase order updated successfully.');
    }

    public function receive(ReceiveMartPurchaseOrderRequest $request, MartPurchaseOrder $mart_purchase_order): JsonResponse
    {
        try {
            $mart_purchase_order = $this->purchaseOrders->receive(
                $mart_purchase_order,
                $request->validated('items'),
                $request->validated('notes'),
            );
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'PO_NOT_RECEIVABLE');
        }

        $allReceived = $mart_purchase_order->status === 'received';

        return $this->success(
            new MartPurchaseOrderResource($mart_purchase_order),
            $allReceived ? 'PO fully received.' : 'Partial receive recorded.'
        );
    }

    public function cancel(MartPurchaseOrder $mart_purchase_order): JsonResponse
    {
        try {
            $mart_purchase_order = $this->purchaseOrders->cancel($mart_purchase_order);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'PO_NOT_CANCELLABLE');
        }

        return $this->success(new MartPurchaseOrderResource($mart_purchase_order), 'PO cancelled.');
    }

    public function destroy(MartPurchaseOrder $mart_purchase_order): JsonResponse
    {
        try {
            $this->purchaseOrders->delete($mart_purchase_order);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'PO_NOT_DELETABLE');
        }

        return $this->noContent('PO deleted.');
    }
}
