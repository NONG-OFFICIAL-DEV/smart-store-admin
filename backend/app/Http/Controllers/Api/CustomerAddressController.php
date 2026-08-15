<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerAddressRequest;
use App\Http\Requests\UpdateCustomerAddressRequest;
use App\Http\Resources\CustomerAddressResource;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\CustomerAddressService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAddressController extends Controller
{
    use ApiResponse;

    public function __construct(private CustomerAddressService $addresses)
    {
    }

    public function index(Request $request, Customer $customer): JsonResponse
    {
        $paginator = $this->addresses->list(array_merge(
            $request->only(['search', 'sortBy', 'sortDesc', 'perPage']),
            ['customer_id' => $customer->id]
        ));

        return $this->success(
            CustomerAddressResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreCustomerAddressRequest $request, Customer $customer): JsonResponse
    {
        $address = $this->addresses->create($customer, $request->validated());

        return $this->created(new CustomerAddressResource($address), 'Address created successfully.');
    }

    public function show(CustomerAddress $address): JsonResponse
    {
        return $this->success(new CustomerAddressResource($address));
    }

    public function update(UpdateCustomerAddressRequest $request, CustomerAddress $address): JsonResponse
    {
        $address = $this->addresses->update($address, $request->validated());

        return $this->success(new CustomerAddressResource($address), 'Address updated successfully.');
    }

    public function destroy(CustomerAddress $address): JsonResponse
    {
        $this->addresses->delete($address);

        return $this->noContent('Address deleted successfully.');
    }
}
