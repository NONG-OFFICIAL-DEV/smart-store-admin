<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Table;
use App\Repositories\Contracts\TableRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TableService extends BaseService
{
    public function __construct(TableRepositoryInterface $repository)
    {
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

    public function updateStatus(Table $table, string $status): Table
    {
        return $this->repository->update($table, ['status' => $status]);
    }

    public function delete(Table $table): bool
    {
        return $this->repository->delete($table);
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
