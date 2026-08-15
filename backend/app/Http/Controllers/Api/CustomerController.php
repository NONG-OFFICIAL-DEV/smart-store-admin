<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ApiResponse;

    public function __construct(private CustomerService $customers)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->customers->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage',
        ]));

        return $this->success(
            CustomerResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = $this->customers->create($request->validated(), $request);

        return $this->created(new CustomerResource($customer), 'Customer created successfully.');
    }

    public function show(Customer $customer): JsonResponse
    {
        return $this->success(new CustomerResource($customer));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $customer = $this->customers->update($customer, $request->validated());

        return $this->success(new CustomerResource($customer), 'Customer updated successfully.');
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $this->customers->delete($customer);

        return $this->noContent('Customer deleted successfully.');
    }

    public function addPoints(Request $request, Customer $customer): JsonResponse
    {
        $request->validate([
            'points' => ['required', 'integer', 'min:1'],
            'order_id' => ['nullable', 'uuid'],
            'type' => ['nullable', 'in:earn,adjust'],
        ]);

        $customer = $this->customers->addLoyaltyPoints(
            $customer,
            (int) $request->integer('points'),
            $request->input('order_id'),
            $request->input('type', 'earn')
        );

        return $this->success(new CustomerResource($customer), 'Loyalty points added.');
    }

    public function redeemPoints(Request $request, Customer $customer): JsonResponse
    {
        $request->validate([
            'points' => ['required', 'integer', 'min:1'],
            'order_id' => ['nullable', 'uuid'],
        ]);

        $result = $this->customers->redeemLoyaltyPoints(
            $customer,
            (int) $request->integer('points'),
            $request->input('order_id')
        );

        if (! $result['redeemed']) {
            return $this->error('Insufficient loyalty points.', 422, [], 'INSUFFICIENT_POINTS');
        }

        return $this->success(new CustomerResource($result['customer']), 'Loyalty points redeemed.');
    }
}
