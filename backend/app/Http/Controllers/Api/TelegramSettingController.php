<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TelegramService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramSettingController extends Controller
{
    use ApiResponse;

    public function __construct(private TelegramService $telegram)
    {
    }

    public function show(): JsonResponse
    {
        return $this->success($this->telegram->getSettings());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bot_token' => 'sometimes|nullable|string|max:255',
            'bot_username' => 'sometimes|nullable|string|max:255',
        ]);

        return $this->success($this->telegram->updateSettings($data), 'Telegram settings updated.');
    }

    public function test(): JsonResponse
    {
        $result = $this->telegram->testConnection();

        if (! $result['ok']) {
            return $this->error($result['error'], 422);
        }

        return $this->success($result);
    }
}
