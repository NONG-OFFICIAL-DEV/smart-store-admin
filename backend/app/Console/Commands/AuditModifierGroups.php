<?php

namespace App\Console\Commands;

use App\Models\ModifierGroup;
use Illuminate\Console\Command;

/**
 * Read-only diagnostic for the three data-integrity problems the Options
 * editor now prevents going forward (see StoreModifierGroupRequest /
 * UpdateModifierGroupRequest), but that may already exist from before those
 * checks were added. Lists only — never modifies data; see the user-facing
 * spec this was requested from ("Show me the list. Don't change data
 * automatically.").
 */
class AuditModifierGroups extends Command
{
    protected $signature = 'modifier-groups:audit';

    protected $description = 'List option groups with no choices, required groups with no available choice, and multiple-select groups where max exceeds the choice count — across every tenant.';

    public function handle(): int
    {
        $groups = ModifierGroup::withoutGlobalScopes()
            ->with(['tenant:id,name', 'options'])
            ->get();

        $noChoices = $groups->filter(fn (ModifierGroup $g) => $g->options->isEmpty());

        $requiredNoAvailable = $groups->filter(
            fn (ModifierGroup $g) => $g->is_required
                && $g->options->isNotEmpty()
                && $g->options->every(fn ($o) => ! $o->is_available)
        );

        $maxTooHigh = $groups->filter(
            fn (ModifierGroup $g) => $g->selection_type === 'multiple'
                && $g->max_selections
                && $g->max_selections > $g->options->count()
        );

        $this->printSection(
            'Groups with no choices at all',
            $noChoices,
            fn (ModifierGroup $g) => []
        );

        $this->printSection(
            'Required groups with no available choice',
            $requiredNoAvailable,
            fn (ModifierGroup $g) => ['Choices' => $g->options->count().' (all unavailable)']
        );

        $this->printSection(
            'Pick-several groups where max exceeds the choice count',
            $maxTooHigh,
            fn (ModifierGroup $g) => ['Max' => $g->max_selections, 'Choices' => $g->options->count()]
        );

        $total = $noChoices->count() + $requiredNoAvailable->count() + $maxTooHigh->count();
        if ($total === 0) {
            $this->info('No data-integrity issues found.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, ModifierGroup>  $groups
     * @param  callable(ModifierGroup): array<string, mixed>  $extraColumns
     */
    private function printSection(string $title, $groups, callable $extraColumns): void
    {
        $this->newLine();
        $this->line("<fg=yellow;options=bold>{$title}</> ({$groups->count()})");

        if ($groups->isEmpty()) {
            $this->line('  none');

            return;
        }

        $headers = ['Tenant', 'Group', 'Group ID'];
        $extraHeaders = array_keys($extraColumns($groups->first()));
        $rows = $groups->map(function (ModifierGroup $g) use ($extraColumns) {
            return array_merge([
                $g->tenant?->name ?? '(no tenant)',
                $g->name,
                $g->id,
            ], array_values($extraColumns($g)));
        })->all();

        $this->table(array_merge($headers, $extraHeaders), $rows);
    }
}
