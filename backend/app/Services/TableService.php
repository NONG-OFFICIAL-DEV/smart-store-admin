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
        return $this->repository->paginateServer($filters);
    }

    public function byBranch(Branch $branch, array $filters): LengthAwarePaginator
    {
        return $this->list(array_merge($filters, ['branch_id' => $branch->id]));
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
