<?php

namespace App\Http\Services;

use App\Http\Interfaces\ReservationRepositoryInterface;
use App\Http\Interfaces\TableRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TableService
{
    public function __construct(
        private TableRepositoryInterface $tableRepository,
        private ReservationRepositoryInterface $reservationRepository
    ) {}

    /**
     * Find available tables for given criteria
     */
    public function findAvailableTables(string $date, string $time, int $numberOfGuests): Collection
    {
        $reservationDateTime = Carbon::parse($date . ' ' . $time);

        // Get all tables that can accommodate the number of guests
        $suitableTables = $this->tableRepository->getByMinimumCapacity($numberOfGuests);

        // Get all reservations for the requested time (2-hour window)
        $reservedTableIds = $this->reservationRepository->getReservedTableIds(
            $reservationDateTime->subHours(2),
            $reservationDateTime->addHours(2)
        );

        // Filter out reserved tables
        return $suitableTables->whereNotIn('id', $reservedTableIds);
    }

    /**
     * Check if a specific table is available at given time
     */
    public function isTableAvailable(int $tableId, string $date, string $time): bool
    {
        $reservationDateTime = Carbon::parse($date . ' ' . $time);

        return !$this->reservationRepository->isTableReserved(
            $tableId,
            $reservationDateTime->subHours(2),
            $reservationDateTime->addHours(2)
        );
    }
}
