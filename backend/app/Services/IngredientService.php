<?php

namespace App\Services;

use App\Models\Ingredient;
use App\Repositories\Contracts\IngredientRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class IngredientService extends BaseService
{
    public function __construct(
        IngredientRepositoryInterface $repository,
        private TenantResolver $tenantResolver
    ) {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    public function create(array $data, Request $request): Ingredient
    {
        $data['tenant_id'] = $this->tenantResolver->resolve($request);

        return $this->repository->create($data);
    }

    public function update(Ingredient $ingredient, array $data): Ingredient
    {
        return $this->repository->update($ingredient, $data);
    }

    public function delete(Ingredient $ingredient): bool
    {
        return $this->repository->delete($ingredient);
    }
}
