<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventoryReportService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryReportController extends Controller
{
    use ApiResponse;

    public function __construct(private InventoryReportService $reports)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->success($this->reports->report($request->only(['branch_id', 'date_from', 'date_to'])));
    }
}
