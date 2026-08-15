<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Guarantees every migrated controller's API response follows the target
 * envelope: { "success": bool, "message": string, "code": string|null,
 * "params": array, "data": mixed, "meta": array }, plus a top-level
 * "errors" object on error() responses.
 *
 * Replaces the three inconsistent shapes previously in use across
 * controllers (see CLAUDE.md). `code` is a stable machine-readable
 * identifier reserved for future frontend i18n mapping (no consumer wired
 * up yet — `params` carries values baked into `message` for when one is).
 *
 * `errors` is top-level, not nested under `meta` — several already-built
 * dialogs (CategoryDialog, ModifierGroupDialog, ModifierOptionDialog,
 * TenantCreate) read `err.response.data.errors` directly for field-level
 * validation messages; nesting it under `meta.errors` would silently
 * break that display for every one of them.
 */
trait ApiResponse
{
    protected function success(mixed $data = null, string $message = '', int $status = 200, array $meta = [], ?string $code = null, array $params = []): JsonResponse
    {
        $payload = $data instanceof JsonResource || $data instanceof ResourceCollection
            ? $data->response()->getData(true)
            : ['data' => $data];

        return response()->json([
            'success' => true,
            'message' => $message,
            'code' => $code,
            'params' => $params,
            'data' => $payload['data'] ?? $data,
            'meta' => array_merge($payload['meta'] ?? [], $meta),
        ], $status);
    }

    protected function created(mixed $data = null, string $message = 'Created successfully.', ?string $code = 'CREATED', array $params = []): JsonResponse
    {
        return $this->success($data, $message, 201, [], $code, $params);
    }

    protected function noContent(string $message = 'Deleted successfully.', ?string $code = 'DELETED'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'code' => $code,
            'params' => [],
            'data' => null,
            'meta' => [],
        ], 200);
    }

    protected function error(string $message = 'Something went wrong.', int $status = 400, array $errors = [], ?string $code = null, array $params = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => $code,
            'params' => $params,
            'data' => null,
            'meta' => [],
            'errors' => $errors,
        ], $status);
    }
}
