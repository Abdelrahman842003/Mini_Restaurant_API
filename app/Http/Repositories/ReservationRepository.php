<?php

namespace App\Http\Repositories;

use App\Http\Interfaces\ReservationRepositoryInterface;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ReservationRepository implements ReservationRepositoryInterface
{
    public function create(array $data): Reservation
    {
        return Reservation::create($data);
    }

    public function findById(int $id): ?Reservation
    {
        return Reservation::find($id);
    }

    public function getUserReservations(int $userId): Collection
    {
        return Reservation::where('user_id', $userId)
            ->with('table')
            ->orderBy('reservation_time', 'desc')
            ->get();
    }

    public function getReservedTableIds(Carbon $startTime, Carbon $endTime): array
    {
        return Reservation::where('status', 'confirmed')
            ->whereBetween('reservation_time', [$startTime, $endTime])
            ->pluck('table_id')
            ->toArray();
    }

    public function isTableReserved(int $tableId, Carbon $startTime, Carbon $endTime): bool
    {
        return Reservation::where('table_id', $tableId)
            ->where('status', 'confirmed')
            ->whereBetween('reservation_time', [$startTime, $endTime])
            ->exists();
    }

    public function updateStatus(int $id, string $status): bool
    {
        return Reservation::where('id', $id)->update(['status' => $status]);
    }

    public function update(int $id, array $data): bool
    {
        return Reservation::where('id', $id)->update($data);
    }

    public function delete(int $id): bool
    {
        return Reservation::destroy($id) > 0;
    }
}
