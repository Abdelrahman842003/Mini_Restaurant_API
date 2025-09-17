<?php

namespace App\Http\Repositories;

use App\Http\Interfaces\TableRepositoryInterface;
use App\Models\Reservation;
use App\Models\Table;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class TableRepository implements TableRepositoryInterface
{
    public function getAll(): Collection
    {
        return Table::all();
    }

    public function getAvailableTables(Carbon $date, string $time, int $numberOfGuests): Collection
    {
        $reservationDateTime = Carbon::parse($date->format('Y-m-d') . ' ' . $time);

        // Get reserved table IDs for the time window (2 hours)
        $reservedTableIds = Reservation::where('status', 'confirmed')
            ->whereBetween('reservation_time', [
                $reservationDateTime->copy()->subHours(2),
                $reservationDateTime->copy()->addHours(2)
            ])
            ->pluck('table_id')
            ->toArray();

        return Table::where('capacity', '>=', $numberOfGuests)
            ->whereNotIn('id', $reservedTableIds)
            ->get();
    }

    public function findById(int $id): ?Table
    {
        return Table::find($id);
    }

    public function isTableAvailable(int $tableId, Carbon $date, string $time): bool
    {
        $reservationDateTime = Carbon::parse($date->format('Y-m-d') . ' ' . $time);

        return !Reservation::where('table_id', $tableId)
            ->where('status', 'confirmed')
            ->whereBetween('reservation_time', [
                $reservationDateTime->copy()->subHours(2),
                $reservationDateTime->copy()->addHours(2)
            ])
            ->exists();
    }

    public function getByMinimumCapacity(int $capacity): Collection
    {
        return Table::where('capacity', '>=', $capacity)->get();
    }

    public function create(array $data): Table
    {
        return Table::create($data);
    }

    public function update(int $id, array $data): Table
    {
        $table = Table::findOrFail($id);
        $table->update($data);
        return $table->fresh();
    }

    public function delete(int $id): bool
    {
        return Table::destroy($id) > 0;
    }
}
