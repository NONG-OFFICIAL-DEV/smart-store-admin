<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReceivePurchaseOrderRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\UpdatePurchaseOrderRequest;
use App\Http\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;
use App\Services\TenantResolver;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    use ApiResponse;

    public function __construct(
        private PurchaseOrderService $purchaseOrders,
        private TenantResolver $tenantResolver,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantResolver->resolve($request);

        $paginator = $this->purchaseOrders->list(array_merge(
            $request->only(['search', 'sortBy', 'sortDesc', 'perPage', 'branch_id', 'supplier_id', 'status']),
            ['tenant_id' => $tenantId]
        ));

        return $this->success(
            PurchaseOrderResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $tenantId = $this->tenantResolver->resolve($request);
        $order = $this->purchaseOrders->create($request->validated(), $tenantId);

        return $this->created(new PurchaseOrderResource($order), 'Purchase order created successfully.');
    }

    public function show(PurchaseOrder $purchase_order): JsonResponse
    {
        return $this->success(new PurchaseOrderResource(
            $purchase_order->load(['supplier:id,name,phone,email,contact_person', 'branch:id,name', 'items.ingredient:id,name,unit'])
        ));
    }

    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchase_order): JsonResponse
    {
        $tenantId = $this->tenantResolver->resolve($request);
        $purchase_order = $this->purchaseOrders->update($purchase_order, $request->validated(), $tenantId);

        return $this->success(new PurchaseOrderResource($purchase_order), 'Purchase order updated successfully.');
    }

    public function submit(PurchaseOrder $purchase_order): JsonResponse
    {
        try {
            $purchase_order = $this->purchaseOrders->submit($purchase_order);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'PO_NOT_SUBMITTABLE');
        }

        return $this->success(new PurchaseOrderResource($purchase_order), 'Purchase order submitted.');
    }

    public function confirm(PurchaseOrder $purchase_order): JsonResponse
    {
        try {
            $purchase_order = $this->purchaseOrders->confirm($purchase_order);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'PO_NOT_CONFIRMABLE');
        }

        return $this->success(new PurchaseOrderResource($purchase_order), 'Purchase order confirmed.');
    }

    public function receive(ReceivePurchaseOrderRequest $request, PurchaseOrder $purchase_order): JsonResponse
    {
        try {
            $purchase_order = $this->purchaseOrders->receive(
                $purchase_order,
                $request->validated('items'),
                $request->user()->staff?->id,
            );
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'PO_NOT_RECEIVABLE');
        }

        return $this->success(new PurchaseOrderResource($purchase_order), 'Items received successfully.');
    }

    public function cancel(PurchaseOrder $purchase_order): JsonResponse
    {
        try {
            $purchase_order = $this->purchaseOrders->cancel($purchase_order);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'PO_NOT_CANCELLABLE');
        }

        return $this->success(new PurchaseOrderResource($purchase_order), 'Purchase order cancelled.');
    }

    public function destroy(PurchaseOrder $purchase_order): JsonResponse
    {
        try {
            $this->purchaseOrders->delete($purchase_order);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors(), 'PO_NOT_DELETABLE');
        }

        return $this->noContent('Purchase order deleted successfully.');
    }
}
