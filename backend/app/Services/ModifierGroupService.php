<?php

namespace App\Services;

use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Repositories\Contracts\ModifierGroupRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ModifierGroupService extends BaseService
{
    public function __construct(
        ModifierGroupRepositoryInterface $repository,
        private TenantResolver $tenantResolver
    ) {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return $this->repository->paginateServer($filters);
    }

    // Group + every choice save together in one transaction — the editor
    // has a single "Save group" action, not separate group/option requests.
    public function create(array $data, Request $request): ModifierGroup
    {
        $data['tenant_id'] = $this->tenantResolver->resolve($request);

        return DB::transaction(function () use ($data) {
            $group = $this->repository->create($this->groupAttributes($data));
            $this->syncOptions($group, $data['options'] ?? []);

            return $group->load('options');
        });
    }

    public function update(ModifierGroup $modifierGroup, array $data): ModifierGroup
    {
        return DB::transaction(function () use ($modifierGroup, $data) {
            $modifierGroup = $this->repository->update(
                $modifierGroup,
                $this->groupAttributes($data, $modifierGroup)
            );

            if (array_key_exists('options', $data)) {
                $this->syncOptions($modifierGroup, $data['options']);
            }

            return $modifierGroup->load('options');
        });
    }

    public function delete(ModifierGroup $modifierGroup): bool
    {
        return $this->repository->delete($modifierGroup);
    }

    // min_selections is never client-settable — derived from is_required
    // (Required -> 1, Optional -> 0). max_selections is forced to 1 for
    // "single" regardless of what was sent, since the editor never shows a
    // stepper for Pick one in the first place.
    private function groupAttributes(array $data, ?ModifierGroup $existing = null): array
    {
        $selectionType = $data['selection_type'] ?? $existing?->selection_type ?? 'single';
        $isRequired = array_key_exists('is_required', $data)
            ? (bool) $data['is_required']
            : (bool) ($existing?->is_required ?? false);

        $attributes = $data;
        unset($attributes['options']);

        $attributes['min_selections'] = $isRequired ? 1 : 0;
        $attributes['max_selections'] = $selectionType === 'single'
            ? 1
            : ($data['max_selections'] ?? $existing?->max_selections);

        return $attributes;
    }

    // Create/update each submitted choice, then remove whichever existing
    // choices weren't resubmitted (the inline editor sends the full current
    // list every save, same as a form, not incremental patches).
    private function syncOptions(ModifierGroup $group, array $options): void
    {
        $keepIds = [];

        foreach ($options as $index => $option) {
            $attributes = [
                'group_id' => $group->id,
                'name' => $option['name'],
                'price_adjustment' => $option['price_adjustment'] ?? 0,
                'is_available' => $option['is_available'] ?? true,
                'is_default' => $option['is_default'] ?? false,
                'sort_order' => $option['sort_order'] ?? $index,
            ];

            $existing = ! empty($option['id']) ? $group->options()->find($option['id']) : null;

            if ($existing) {
                $existing->update($attributes);
                $keepIds[] = $existing->id;
            } else {
                $keepIds[] = $group->options()->create($attributes)->id;
            }
        }

        $removedIds = $group->options()->whereNotIn('id', $keepIds)->pluck('id');

        foreach ($removedIds as $id) {
            try {
                ModifierOption::whereKey($id)->delete();
            } catch (QueryException $e) {
                // SQLSTATE 23503 — referenced by a past order's line-item
                // snapshot (order_item_modifiers.modifier_option_id is
                // restrictOnDelete). Can't hard-delete without breaking that
                // history, so hide it from future use instead.
                if ($e->getCode() === '23503') {
                    ModifierOption::whereKey($id)->update(['is_available' => false]);
                    continue;
                }

                throw $e;
            }
        }
    }
}
