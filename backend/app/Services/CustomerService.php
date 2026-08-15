<?php

namespace App\Services;

use App\Models\Customer;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class CustomerService extends BaseService
{
    public function __construct(
        CustomerRepositoryInterface $repository,
        private TenantResolver $tenantResolver
    ) {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function create(array $data, Request $request): Customer
    {
        $data['tenant_id'] = $this->tenantResolver->resolve($request);

        return $this->repository->create($data);
    }

    public function update(Customer $customer, array $data): Customer
    {
        return $this->repository->update($customer, $data);
    }

    public function delete(Customer $customer): bool
    {
        return $this->repository->delete($customer);
    }

    public function addLoyaltyPoints(Customer $customer, int $points, ?string $orderId = null, string $type = 'earn'): Customer
    {
        $customer->addPoints($points, $orderId, $type);

        return $customer->refresh();
    }

    public function redeemLoyaltyPoints(Customer $customer, int $points, ?string $orderId = null): array
    {
        $redeemed = $customer->redeemPoints($points, $orderId);

        return ['redeemed' => $redeemed, 'customer' => $customer->refresh()];
    }
}
