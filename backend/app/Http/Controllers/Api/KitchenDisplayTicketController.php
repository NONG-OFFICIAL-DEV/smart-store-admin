<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKitchenDisplayTicketRequest;
use App\Http\Requests\UpdateKitchenDisplayTicketRequest;
use App\Http\Resources\KitchenDisplayTicketResource;
use App\Models\KitchenDisplayTicket;
use App\Models\Order;
use App\Services\KitchenDisplayTicketService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KitchenDisplayTicketController extends Controller
{
    use ApiResponse;

    public function __construct(private KitchenDisplayTicketService $tickets)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->tickets->list($request->only([
            'sortBy', 'sortDesc', 'perPage', 'branch_id', 'order_id', 'status', 'station',
        ]));

        return $this->paginated($paginator);
    }

    public function byOrder(Request $request, Order $order): JsonResponse
    {
        $paginator = $this->tickets->byOrder($order->id, $request->only(['sortBy', 'sortDesc', 'perPage', 'status']));

        return $this->paginated($paginator);
    }

    public function store(StoreKitchenDisplayTicketRequest $request): JsonResponse
    {
        $ticket = $this->tickets->create($request->validated());

        return $this->created(new KitchenDisplayTicketResource($ticket), 'Kitchen ticket created successfully.');
    }

    public function show(KitchenDisplayTicket $kitchen_ticket): JsonResponse
    {
        return $this->success(new KitchenDisplayTicketResource($kitchen_ticket));
    }

    public function update(UpdateKitchenDisplayTicketRequest $request, KitchenDisplayTicket $kitchen_ticket): JsonResponse
    {
        $kitchen_ticket = $this->tickets->update($kitchen_ticket, $request->validated());

        return $this->success(new KitchenDisplayTicketResource($kitchen_ticket), 'Kitchen ticket updated successfully.');
    }

    public function start(KitchenDisplayTicket $ticket): JsonResponse
    {
        $ticket = $this->tickets->start($ticket);

        return $this->success(new KitchenDisplayTicketResource($ticket), 'Kitchen ticket started.');
    }

    public function complete(KitchenDisplayTicket $ticket): JsonResponse
    {
        $ticket = $this->tickets->complete($ticket);

        return $this->success(new KitchenDisplayTicketResource($ticket), 'Kitchen ticket completed.');
    }

    public function cancel(KitchenDisplayTicket $ticket): JsonResponse
    {
        $ticket = $this->tickets->cancel($ticket);

        return $this->success(new KitchenDisplayTicketResource($ticket), 'Kitchen ticket cancelled.');
    }

    public function destroy(KitchenDisplayTicket $kitchen_ticket): JsonResponse
    {
        $this->tickets->delete($kitchen_ticket);

        return $this->noContent('Kitchen ticket deleted successfully.');
    }

    private function paginated($paginator): JsonResponse
    {
        return $this->success(
            KitchenDisplayTicketResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }
}
