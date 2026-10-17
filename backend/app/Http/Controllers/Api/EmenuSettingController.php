<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EmenuSettingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmenuSettingController extends Controller
{
    use ApiResponse;

    public function __construct(private EmenuSettingService $emenu)
    {
    }

    public function show(): JsonResponse
    {
        return $this->success($this->emenu->getSettings());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'base_url' => 'sometimes|nullable|url|max:255',
        ]);

        return $this->success($this->emenu->updateSettings($data), 'E-menu settings updated.');
    }
}
