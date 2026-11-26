<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCashRegisterRequest;
use App\Http\Requests\UpdateCashRegisterRequest;
use App\Http\Resources\CashRegisterResource;
use App\Models\CashRegister;
use App\Services\CashRegisterService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashRegisterController extends Controller
{
    use ApiResponse;

    public function __construct(private CashRegisterService $cashRegisters)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->cashRegisters->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'branch_id', 'is_active',
        ]));

        return $this->success(
            CashRegisterResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    public function store(StoreCashRegisterRequest $request): JsonResponse
    {
        $register = $this->cashRegisters->create($request->validated());

        return $this->created(new CashRegisterResource($register), 'Cash register created successfully.');
    }

    public function show(CashRegister $cash_register): JsonResponse
    {
        return $this->success(new CashRegisterResource($cash_register));
    }

    public function update(UpdateCashRegisterRequest $request, CashRegister $cash_register): JsonResponse
    {
        $cash_register = $this->cashRegisters->update($cash_register, $request->validated());

        return $this->success(new CashRegisterResource($cash_register), 'Cash register updated successfully.');
    }

    public function destroy(CashRegister $cash_register): JsonResponse
    {
        $this->cashRegisters->delete($cash_register);

        return $this->noContent('Cash register deleted successfully.');
    }
}
