<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\UpdateReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Models\Branch;
use App\Models\Reservation;
use App\Models\Table;
use App\Services\ReservationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    use ApiResponse;

    public function __construct(private ReservationService $reservations)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->reservations->list($request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'date', 'status', 'table_id', 'branch_id',
        ]));

        return $this->paginated($paginator);
    }

    public function byBranch(Request $request, Branch $branch): JsonResponse
    {
        $paginator = $this->reservations->byBranch($branch, $request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'date', 'status',
        ]));

        return $this->paginated($paginator);
    }

    public function byTable(Request $request, Table $table): JsonResponse
    {
        $paginator = $this->reservations->byTable($table, $request->only([
            'search', 'sortBy', 'sortDesc', 'perPage', 'date', 'status',
        ]));

        return $this->paginated($paginator);
    }

    public function store(StoreReservationRequest $request): JsonResponse
    {
        try {
            $reservation = $this->reservations->create($request->validated());
        } catch (ValidationException $e) {
            return $this->tableConflictResponse($e);
        }

        return $this->created(new ReservationResource($reservation), 'Reservation created successfully.');
    }

    public function show(Reservation $reservation): JsonResponse
    {
        return $this->success(new ReservationResource($reservation->load(['table', 'branch'])));
    }

    public function update(UpdateReservationRequest $request, Reservation $reservation): JsonResponse
    {
        try {
            $reservation = $this->reservations->update($reservation, $request->validated());
        } catch (ValidationException $e) {
            return $this->tableConflictResponse($e);
        }

        return $this->success(new ReservationResource($reservation), 'Reservation updated successfully.');
    }

    public function destroy(Reservation $reservation): JsonResponse
    {
        $this->reservations->delete($reservation);

        return $this->noContent('Reservation deleted successfully.');
    }

    public function confirm(Reservation $reservation): JsonResponse
    {
        return $this->success(new ReservationResource($this->reservations->confirm($reservation)), 'Reservation confirmed.');
    }

    public function seat(Request $request, Reservation $reservation): JsonResponse
    {
        $request->validate(['table_id' => ['required', 'uuid', 'exists:tables,id']]);

        try {
            $reservation = $this->reservations->seat($reservation, $request->table_id);
        } catch (ValidationException $e) {
            return $this->tableConflictResponse($e);
        }

        return $this->success(new ReservationResource($reservation), 'Reservation seated.');
    }

    public function cancel(Reservation $reservation): JsonResponse
    {
        return $this->success(new ReservationResource($this->reservations->cancel($reservation)), 'Reservation cancelled.');
    }

    public function noShow(Reservation $reservation): JsonResponse
    {
        return $this->success(new ReservationResource($this->reservations->noShow($reservation)), 'Reservation marked as no-show.');
    }

    private function paginated($paginator): JsonResponse
    {
        return $this->success(
            ReservationResource::collection($paginator->items()),
            meta: [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ]
        );
    }

    // error_code is the stable, machine-readable contract with the
    // frontend (see Reservation.vue's resolveErrorMessage, keyed off
    // response.data.code) — it translates this via vue-i18n. The English
    // message is only a fallback for non-UI API consumers (Postman, logs).
    private function tableConflictResponse(ValidationException $e): JsonResponse
    {
        // ValidationException::withMessages() sets a generic top-level
        // message ("The given data was invalid.") — the real text lives in
        // the field errors, which is what both the API-consumer fallback
        // and the frontend's error UI actually want to show.
        $message = $e->errors()['table_id'][0] ?? $e->getMessage();

        return $this->error($message, 422, $e->errors(), 'reservation_table_conflict');
    }
}
