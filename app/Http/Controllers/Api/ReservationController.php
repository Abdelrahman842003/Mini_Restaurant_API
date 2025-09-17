<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Interfaces\ReservationRepositoryInterface;
use App\Http\Interfaces\TableRepositoryInterface;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Http\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private ReservationRepositoryInterface $reservationRepository,
        private TableRepositoryInterface $tableRepository
    ) {}

    /**
     * Store a newly created reservation.
     */
    public function store(StoreReservationRequest $request)
    {
        try {
            $validated = $request->validated();

            // Convert date and time to reservation_time
            $reservationTime = Carbon::parse($validated['date'] . ' ' . $validated['time']);

            // Validate table availability
            if (!$this->tableRepository->isTableAvailable($validated['table_id'], Carbon::parse($validated['date']), $validated['time'])) {
                return $this->apiResponse(400, 'Table is not available for the selected date and time');
            }

            // Verify table capacity
            $table = $this->tableRepository->findById($validated['table_id']);
            if (!$table || $table->capacity < $validated['number_of_guests']) {
                return $this->apiResponse(400, 'Table capacity is insufficient for the number of guests');
            }

            $reservationData = [
                'user_id' => auth()->id(),
                'table_id' => $validated['table_id'],
                'number_of_guests' => $validated['number_of_guests'],
                'reservation_time' => $reservationTime,
                'status' => 'confirmed'
            ];

            $reservation = $this->reservationRepository->create($reservationData);

            // Load the table relationship for the response
            $reservation->load('table');

            return $this->apiResponse(
                201,
                'Reservation created successfully',
                null,
                new ReservationResource($reservation)
            );
        } catch (\Exception $e) {
            return $this->apiResponse(400, 'Failed to create reservation', $e->getMessage());
        }
    }

    /**
     * Display the authenticated user's reservations.
     */
    public function index(Request $request)
    {
        try {
            $reservations = $this->reservationRepository->getUserReservations(auth()->id());

            return $this->apiResponse(
                200,
                'User reservations retrieved successfully',
                null,
                ReservationResource::collection($reservations)
            );
        } catch (\Exception $e) {
            return $this->apiResponse(500, 'Failed to retrieve reservations', $e->getMessage());
        }
    }
}
