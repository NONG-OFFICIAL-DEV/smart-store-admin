<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTableRequest;
use App\Http\Requests\UpdateTableRequest;
use App\Http\Resources\TableResource;
use App\Models\Branch;
use App\Models\Table;
use App\Services\TableService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TableController extends Controller
{
    use ApiResponse;

    public function __construct(private TableService $tables)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->tables->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'branch_id', 'floor_plan_id', 'status',
        ]));

        return $this->paginated($paginator);
    }

    public function byBranch(Request $request, Branch $branch): JsonResponse
    {
        $paginator = $this->tables->byBranch($branch, $request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'floor_plan_id', 'status',
        ]));

        return $this->paginated($paginator);
    }

    public function store(StoreTableRequest $request): JsonResponse
    {
        $table = $this->tables->create($request->validated());

        return $this->created(new TableResource($table), 'Table created successfully.');
    }

    public function show(Table $table): JsonResponse
    {
        return $this->success(new TableResource($table));
    }

    public function update(UpdateTableRequest $request, Table $table): JsonResponse
    {
        $table = $this->tables->update($table, $request->validated());

        return $this->success(new TableResource($table), 'Table updated successfully.');
    }

    public function updateStatus(Request $request, Table $table): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'in:available,occupied,reserved,cleaning,inactive'],
        ]);

        $table = $this->tables->updateStatus($table, $request->status);

        return $this->success(new TableResource($table), 'Table status updated successfully.');
    }

    public function destroy(Table $table): JsonResponse
    {
        $this->tables->delete($table);

        return $this->noContent('Table deleted successfully.');
    }

    public function qrCode(Table $table): JsonResponse
    {
        return $this->success($this->tables->qrInfo($table));
    }

    public function downloadQrCode(Table $table): mixed
    {
        $this->tables->qrInfo($table);

        $path = 'qrcodes/tables/'.$table->id.'.svg';
        $filename = 'QR-Table-'.$table->table_number.'-'.$table->branch->name.'.svg';
        $filename = preg_replace('/[^A-Za-z0-9\-_.]/', '-', $filename);

        return response()->streamDownload(function () use ($path) {
            echo Storage::disk('public')->get($path);
        }, $filename, [
            'Content-Type' => 'image/svg',
            'Content-Disposition' => 'attachment',
        ]);
    }

    public function regenerateQrCode(Table $table): JsonResponse
    {
        return $this->success($this->tables->regenerateQrCode($table));
    }

    private function paginated($paginator): JsonResponse
    {
        return $this->success(
            TableResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }
}
