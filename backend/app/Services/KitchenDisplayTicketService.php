<?php

namespace App\Services;

use App\Models\KitchenDisplayTicket;
use App\Repositories\Contracts\KitchenDisplayTicketRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class KitchenDisplayTicketService extends BaseService
{
    public function __construct(KitchenDisplayTicketRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function byOrder(string $orderId, array $filters): LengthAwarePaginator
    {
        return $this->list(array_merge($filters, ['order_id' => $orderId]));
    }

    public function create(array $data): KitchenDisplayTicket
    {
        return $this->repository->create($data);
    }

    public function start(KitchenDisplayTicket $ticket): KitchenDisplayTicket
    {
        return $this->repository->update($ticket, ['status' => 'in_progress', 'started_at' => now()]);
    }

    public function complete(KitchenDisplayTicket $ticket): KitchenDisplayTicket
    {
        return $this->repository->update($ticket, ['status' => 'done', 'completed_at' => now()]);
    }

    public function cancel(KitchenDisplayTicket $ticket): KitchenDisplayTicket
    {
        return $this->repository->update($ticket, ['status' => 'cancelled']);
    }

    public function update(KitchenDisplayTicket $ticket, array $data): KitchenDisplayTicket
    {
        return $this->repository->update($ticket, $data);
    }

    public function delete(KitchenDisplayTicket $ticket): bool
    {
        return $this->repository->delete($ticket);
    }
}
