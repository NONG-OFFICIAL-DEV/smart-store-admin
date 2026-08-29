<?php

namespace App\Services;

use App\Enums\PlanFeatureValueType;
use App\Models\Plan;
use App\Models\PlanFeatureListing;
use App\Repositories\Contracts\PlanFeatureListingRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class PlanFeatureListingService extends BaseService
{
    public function __construct(PlanFeatureListingRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function all(): Collection
    {
        return $this->repository->all();
    }

    public function create(array $data): PlanFeatureListing
    {
        return $this->repository->create($data);
    }

    public function update(PlanFeatureListing $listing, array $data): PlanFeatureListing
    {
        return $this->repository->update($listing, $data);
    }

    /**
     * Soft delete only — a plan's own `plan_features` row for this key is
     * not touched or cleaned up (no FK, `key` is a soft string reference).
     * resolveForPlan() below simply stops surfacing a value whose key
     * isn't in the *active* catalog anymore; that skip is load-bearing.
     */
    public function delete(PlanFeatureListing $listing): bool
    {
        return $this->repository->delete($listing);
    }

    /**
     * Joins a plan's own `plan_features` rows against the live active
     * catalog (ordered for display) — label text, value type, and this
     * plan's value, pre-resolved so no consumer has to guess. A catalog
     * key the plan hasn't saved a value for yet defaults per type: false
     * for boolean, empty text for text.
     */
    public function resolveForPlan(Plan $plan): array
    {
        $values = $plan->features()->get()->keyBy('key');

        return $this->repository->query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(function (PlanFeatureListing $listing) use ($values) {
                $isBoolean = $listing->value_type === PlanFeatureValueType::Boolean;
                $default = $isBoolean ? false : ['en' => '', 'km' => null];

                return [
                    'key' => $listing->key,
                    'value_type' => $listing->value_type->value,
                    'label' => ['en' => $listing->label_en, 'km' => $listing->label_km],
                    'value' => $values->get($listing->key)?->value ?? $default,
                ];
            })
            ->values()
            ->all();
    }
}
