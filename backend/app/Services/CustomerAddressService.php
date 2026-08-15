<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Repositories\Contracts\CustomerAddressRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CustomerAddressService extends BaseService
{
    public function __construct(CustomerAddressRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function create(Customer $customer, array $data): CustomerAddress
    {
        $data['customer_id'] = $customer->id;
        $this->clearOtherDefaults($customer, $data);

        return $this->repository->create($data);
    }

    public function update(CustomerAddress $address, array $data): CustomerAddress
    {
        $this->clearOtherDefaults($address->customer, $data, $address->id);

        return $this->repository->update($address, $data);
    }

    public function delete(CustomerAddress $address): bool
    {
        return $this->repository->delete($address);
    }

    // Only one default address per customer.
    private function clearOtherDefaults(Customer $customer, array $data, ?string $exceptId = null): void
    {
        if (empty($data['is_default'])) {
            return;
        }

        CustomerAddress::where('customer_id', $customer->id)
            ->when($exceptId, fn($q) => $q->where('id', '!=', $exceptId))
            ->update(['is_default' => false]);
    }
}
