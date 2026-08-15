<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Reservation;
use App\Models\Table;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class ReservationService extends BaseService
{
    // A reservation only "occupies" its table while it's in one of these
    // statuses — completed/cancelled/no_show reservations no longer hold
    // the table and must never block a new booking for that slot.
    private const OCCUPYING_STATUSES = ['pending', 'confirmed', 'seated'];

    public function __construct(ReservationRepositoryInterface $repository)
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

    public function byTable(Table $table, array $filters): LengthAwarePaginator
    {
        return $this->list(array_merge($filters, ['table_id' => $table->id]));
    }

    public function create(array $data): Reservation
    {
        if (! empty($data['table_id'])) {
            $start = Carbon::parse($data['reserved_at']);
            $end = $start->copy()->addMinutes($data['duration_minutes'] ?? 90);

            $this->assertNoTableConflict($data['table_id'], $start, $end);
        }

        $reservation = $this->repository->create($data);

        return $reservation->load(['table', 'branch']);
    }

    public function update(Reservation $reservation, array $data): Reservation
    {
        // Fall back to the record's current values for anything this
        // (possibly partial) request didn't include, so a status-only
        // update still gets checked against its own table/time, and a
        // full edit gets checked against whatever it's changing to.
        $effectiveTableId = array_key_exists('table_id', $data) ? $data['table_id'] : $reservation->table_id;
        $effectiveStatus = $data['status'] ?? $reservation->status;

        if ($effectiveTableId && in_array($effectiveStatus, self::OCCUPYING_STATUSES, true)) {
            $start = Carbon::parse($data['reserved_at'] ?? $reservation->reserved_at);
            $end = $start->copy()->addMinutes($data['duration_minutes'] ?? $reservation->duration_minutes ?? 90);

            $this->assertNoTableConflict($effectiveTableId, $start, $end, $reservation->id);
        }

        $reservation = $this->repository->update($reservation, $data);

        return $reservation->load(['table', 'branch']);
    }

    public function delete(Reservation $reservation): bool
    {
        return $this->repository->delete($reservation);
    }

    public function confirm(Reservation $reservation): Reservation
    {
        // pending -> confirmed: both already occupying statuses, table/time
        // don't change, so no new conflict can be introduced here.
        return $this->repository->update($reservation, ['status' => 'confirmed']);
    }

    /**
     * Seating can move the reservation to a different table than it was
     * booked against — unlike confirm(), this DOES need the conflict check
     * (the original static Reservation::seat() didn't have one at all,
     * which meant seating could silently double-book a table).
     */
    public function seat(Reservation $reservation, string $tableId): Reservation
    {
        $start = Carbon::parse($reservation->reserved_at);
        $end = $start->copy()->addMinutes($reservation->duration_minutes ?? 90);

        $this->assertNoTableConflict($tableId, $start, $end, $reservation->id);

        $reservation = $this->repository->update($reservation, ['status' => 'seated', 'table_id' => $tableId]);
        Table::updateStatus($tableId, Table::STATUS_OCCUPIED);

        return $reservation;
    }

    public function cancel(Reservation $reservation): Reservation
    {
        return $this->repository->update($reservation, ['status' => 'cancelled']);
    }

    public function noShow(Reservation $reservation): Reservation
    {
        return $this->repository->update($reservation, ['status' => 'no_show']);
    }

    /**
     * Whether some OTHER active reservation on this table overlaps the
     * given [start, end) window. $excludeId lets an update ignore its own
     * current row while checking.
     */
    private function assertNoTableConflict(string $tableId, Carbon $start, Carbon $end, ?string $excludeId = null): void
    {
        $candidates = Reservation::where('table_id', $tableId)
            ->whereIn('status', self::OCCUPYING_STATUSES)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            // Bounded scan, not a full table scan: any reservation that could
            // possibly overlap [start, end) must itself start within this
            // range (no booking here runs longer than a day).
            ->whereBetween('reserved_at', [$start->copy()->subDay(), $end])
            ->get(['reserved_at', 'duration_minutes']);

        foreach ($candidates as $existing) {
            $existingStart = Carbon::parse($existing->reserved_at);
            $existingEnd = $existingStart->copy()->addMinutes($existing->duration_minutes ?? 90);

            if ($start->lt($existingEnd) && $end->gt($existingStart)) {
                throw ValidationException::withMessages([
                    'table_id' => 'This table already has a reservation that overlaps this time — pick a different table or time.',
                ]);
            }
        }
    }
}
