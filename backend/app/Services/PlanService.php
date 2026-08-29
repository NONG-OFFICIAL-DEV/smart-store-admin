<?php

namespace App\Services;

use App\Models\Plan;
use App\Repositories\Contracts\PlanRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanService extends BaseService
{
    public function __construct(PlanRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }

    public function list(): Collection
    {
        return $this->repository->allOrdered();
    }

    public function publicPlans(bool $includeInactive): Collection
    {
        return $this->repository->allOrdered(activeOnly: ! $includeInactive);
    }

    public function create(array $data): Plan
    {
        return DB::transaction(function () use ($data) {
            $plan = $this->repository->create([
                'name' => $data['name'],
                'code' => strtolower($data['code']),
                'price_usd' => $data['price_usd'],
                'price_khr' => $data['price_khr'] ?? 0,
                'seats' => $data['seats'],
                'storage_gb' => $data['storage_gb'],
                'api_limit' => $data['api_limit'] ?? 0,
                'trial_days' => $data['trial_days'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            foreach ($data['billing_cycles'] as $cycle) {
                $plan->billingCycles()->create($cycle);
            }

            foreach ($data['features'] ?? [] as $i => $feature) {
                $plan->features()->create([
                    'key' => $feature['key'],
                    'value' => $feature['value'],
                    'sort_order' => $feature['sort_order'] ?? $i,
                ]);
            }

            return $plan->load(['billingCycles', 'features']);
        });
    }

    public function update(Plan $plan, array $data): Plan
    {
        return DB::transaction(function () use ($data, $plan) {
            $plan->update([
                'name' => $data['name'],
                'code' => strtolower($data['code']),
                'price_usd' => $data['price_usd'],
                'price_khr' => $data['price_khr'] ?? $plan->price_khr,
                'seats' => $data['seats'],
                'storage_gb' => $data['storage_gb'],
                'api_limit' => $data['api_limit'] ?? $plan->api_limit,
                'trial_days' => $data['trial_days'] ?? $plan->trial_days,
                'is_active' => $data['is_active'] ?? $plan->is_active,
            ]);

            // Sync billing cycles — delete removed, upsert the rest.
            $incomingCycleIds = collect($data['billing_cycles'])->pluck('id')->filter()->values();

            $plan->billingCycles()->whereNotIn('id', $incomingCycleIds)->delete();

            foreach ($data['billing_cycles'] as $cycle) {
                $plan->billingCycles()->updateOrCreate(
                    ['id' => $cycle['id'] ?? null],
                    [
                        'label' => $cycle['label'],
                        'months' => $cycle['months'],
                        'discount_percent' => $cycle['discount_percent'],
                        'is_active' => $cycle['is_active'] ?? true,
                    ]
                );
            }

            // Sync features — delete removed, upsert the rest.
            $incomingFeatureIds = collect($data['features'] ?? [])->pluck('id')->filter()->values();

            $plan->features()->whereNotIn('id', $incomingFeatureIds)->delete();

            foreach ($data['features'] ?? [] as $i => $feature) {
                $plan->features()->updateOrCreate(
                    ['id' => $feature['id'] ?? null],
                    [
                        'key' => $feature['key'],
                        'value' => $feature['value'],
                        'sort_order' => $feature['sort_order'] ?? $i,
                    ]
                );
            }

            return $plan->fresh(['billingCycles', 'features']);
        });
    }

    public function delete(Plan $plan): void
    {
        $activeCount = $plan->subscriptions()->whereIn('status', ['active', 'trial'])->count();

        if ($activeCount > 0) {
            throw ValidationException::withMessages([
                'plan' => "Cannot delete plan with {$activeCount} active subscription(s). Deactivate it instead.",
            ]);
        }

        $this->repository->delete($plan);
    }

    public function toggleActive(Plan $plan): Plan
    {
        $plan->is_active = ! $plan->is_active;
        $plan->save();

        return $plan;
    }
}
