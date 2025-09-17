<?php

namespace App\Http\Interfaces;

use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

interface ReservationRepositoryInterface
{
    public function create(array $data): Reservation;
    public function findById(int $id): ?Reservation;
    public function getUserReservations(int $userId): Collection;
    public function getReservedTableIds(Carbon $startTime, Carbon $endTime): array;
    public function isTableReserved(int $tableId, Carbon $startTime, Carbon $endTime): bool;
    public function updateStatus(int $id, string $status): bool;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}
