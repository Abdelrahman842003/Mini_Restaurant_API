<?php

namespace App\Http\Services;

use App\Http\Interfaces\ReservationRepositoryInterface;
use App\Http\Interfaces\TableRepositoryInterface;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReservationService
{
    public function __construct(
        private ReservationRepositoryInterface $reservationRepository,
        private TableRepositoryInterface $tableRepository,
        private TableService $tableService
    ) {}

    /**
     * Create a new reservation
     */
    public function createReservation(array $data): Reservation
    {
        return DB::transaction(function () use ($data) {
            // Verify table is still available
            if (!$this->tableService->isTableAvailable(
                $data['table_id'],
                $data['date'],
                $data['time']
            )) {
                throw new \Exception('Table is no longer available for the selected time.');
            }

            // Verify table capacity
            $table = $this->tableRepository->findById($data['table_id']);
            if (!$table || $table->capacity < $data['number_of_guests']) {
                throw new \Exception('Table capacity is insufficient for the number of guests.');
            }

            $reservationData = [
                'user_id' => $data['user_id'],
                'table_id' => $data['table_id'],
                'number_of_guests' => $data['number_of_guests'],
                'reservation_time' => Carbon::parse($data['date'] . ' ' . $data['time']),
                'status' => 'confirmed'
            ];

            return $this->reservationRepository->create($reservationData);
        });
    }

    /**
     * Get user reservations
     */
    public function getUserReservations(int $userId)
    {
        return $this->reservationRepository->getUserReservations($userId);
    }

    /**
     * Cancel a reservation
     */
    public function cancelReservation(int $reservationId, int $userId): bool
    {
        $reservation = $this->reservationRepository->findById($reservationId);

        if (!$reservation || $reservation->user_id !== $userId) {
            throw new \Exception('Reservation not found or unauthorized.');
        }

        if ($reservation->status === 'cancelled') {
            throw new \Exception('Reservation is already cancelled.');
        }

        return $this->reservationRepository->updateStatus($reservationId, 'cancelled');
    }
}
