<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Table;
use App\Repositories\Contracts\TableRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TableService extends BaseService
{
    public function __construct(
        TableRepositoryInterface $repository,
        private TableSessionService $tableSessions
    ) {
        parent::__construct($repository);
    }

    public function list(array $filters): LengthAwarePaginator
    {
        $paginator = $this->repository->paginateServer($filters);
        $this->loadOpenSessionSummaries($paginator->getCollection());

        return $paginator;
    }

    public function byBranch(Branch $branch, array $filters): LengthAwarePaginator
    {
        return $this->list(array_merge($filters, ['branch_id' => $branch->id]));
    }

    // Batch-loads each table's open session (if any) plus just enough of
    // its orders to compute an order count/total in TableResource, so the
    // Tables page's occupied/available + "N orders, $X" summary costs one
    // extra query for the whole page instead of one per table.
    private function loadOpenSessionSummaries($tables): void
    {
        $tables->load(['openSession' => function ($query) {
            $query->with(['orders' => function ($ordersQuery) {
                $ordersQuery->whereNotIn('status', ['cancelled', 'refunded'])->with('payments');
            }]);
        }]);
    }

    public function loadOpenSessionSummary(Table $table): Table
    {
        $this->loadOpenSessionSummaries(collect([$table]));

        return $table;
    }

    public function create(array $data): Table
    {
        return $this->repository->create($data);
    }

    public function update(Table $table, array $data): Table
    {
        return $this->repository->update($table, $data);
    }

    // Staff setting the status to anything other than "occupied" is a
    // deliberate "this table is done" signal — close out any lingering open
    // session so is_occupied (session-based) agrees with the status column
    // staff just set (was previously its own independent thing: marking a
    // table Available left any open session untouched, so the next fetch
    // could still show is_occupied: true on an "Available" table).
    // Only auto-closes when the session has no unpaid orders left —
    // closeSessionIfEmpty() already enforces that — so an order a customer
    // hasn't paid yet never silently loses its ability to be settled via
    // the session-pay endpoint.
    public function updateStatus(Table $table, string $status): Table
    {
        $table = $this->repository->update($table, ['status' => $status]);

        if ($status !== Table::STATUS_OCCUPIED) {
            $session = $this->tableSessions->getOpenSession($table->id);
            if ($session) {
                $this->tableSessions->closeSessionIfEmpty($session->id);
            }
        }

        return $table->fresh();
    }

    public function delete(Table $table): bool
    {
        return $this->repository->delete($table);
    }

    // Assigns sequential sort_order (0..n-1) matching the given id order —
    // TenantScope on Table filters the query automatically, so an id
    // belonging to another tenant just isn't in $tables and is skipped.
    public function reorder(array $orderedIds): void
    {
        $tables = Table::whereIn('id', $orderedIds)->get()->keyBy('id');

        DB::transaction(function () use ($orderedIds, $tables) {
            foreach ($orderedIds as $index => $id) {
                $tables->get($id)?->update(['sort_order' => $index]);
            }
        });
    }

    public function qrInfo(Table $table): array
    {
        $table->load('branch');

        if (! $table->qr_image_path) {
            $table->generateQrCode();
            $table->refresh();
        }

        return [
            'table_id' => $table->id,
            'table_number' => $table->table_number,
            'branch_name' => $table->branch?->name,
            'branch_slug' => $table->branch?->slug,
            'url' => $table->qr_code,
            'qr_image_url' => $table->qr_image_url,
        ];
    }

    public function regenerateQrCode(Table $table): array
    {
        $table->load('branch');
        $table->generateQrCode();
        $table->refresh();

        return [
            'table_id' => $table->id,
            'table_number' => $table->table_number,
            'url' => $table->qr_code,
            'qr_image_url' => $table->qr_image_url,
        ];
    }
}
