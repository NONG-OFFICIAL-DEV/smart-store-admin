<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Repositories\Contracts\CashRegisterRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CashRegisterService extends BaseService
{
    public function __construct(CashRegisterRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function create(array $data): CashRegister
    {
        return $this->repository->create([
            'branch_id' => $data['branch_id'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function update(CashRegister $register, array $data): CashRegister
    {
        return $this->repository->update($register, $data);
    }

    public function delete(CashRegister $register): bool
    {
        return $this->repository->delete($register);
    }
}
