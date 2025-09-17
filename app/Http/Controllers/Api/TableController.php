<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Interfaces\TableRepositoryInterface;
use App\Http\Requests\CheckAvailabilityRequest;
use App\Http\Resources\TableResource;
use App\Http\Traits\ApiResponseTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TableController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private TableRepositoryInterface $tableRepository
    ) {}

    /**
     * Get all tables
     */
    public function index()
    {
        try {
            $tables = $this->tableRepository->getAll();

            return $this->apiResponse(
                200,
                'Tables retrieved successfully',
                null,
                TableResource::collection($tables)
            );
        } catch (\Exception $e) {
            return $this->apiResponse(500, 'Failed to retrieve tables', $e->getMessage());
        }
    }

    /**
     * Get a specific table
     */
    public function show($id)
    {
        try {
            $table = $this->tableRepository->findById($id);

            if (!$table) {
                return $this->apiResponse(404, 'Table not found');
            }

            return $this->apiResponse(
                200,
                'Table retrieved successfully',
                null,
                new TableResource($table)
            );
        } catch (\Exception $e) {
            return $this->apiResponse(500, 'Failed to retrieve table', $e->getMessage());
        }
    }

    /**
     * Create a new table
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'capacity' => 'required|integer|min:1|max:20'
            ]);

            if ($validator->fails()) {
                return $this->apiResponse(422, 'Validation failed', null, $validator->errors());
            }

            $table = $this->tableRepository->create($request->only(['capacity']));

            return $this->apiResponse(
                201,
                'Table created successfully',
                null,
                new TableResource($table)
            );
        } catch (\Exception $e) {
            return $this->apiResponse(500, 'Failed to create table', $e->getMessage());
        }
    }

    /**
     * Update an existing table
     */
    public function update(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'capacity' => 'required|integer|min:1|max:20'
            ]);

            if ($validator->fails()) {
                return $this->apiResponse(422, 'Validation failed', null, $validator->errors());
            }

            $table = $this->tableRepository->findById($id);

            if (!$table) {
                return $this->apiResponse(404, 'Table not found');
            }

            $updatedTable = $this->tableRepository->update($id, $request->only(['capacity']));

            return $this->apiResponse(
                200,
                'Table updated successfully',
                null,
                new TableResource($updatedTable)
            );
        } catch (\Exception $e) {
            return $this->apiResponse(500, 'Failed to update table', $e->getMessage());
        }
    }

    /**
     * Delete a table
     */
    public function destroy($id)
    {
        try {
            $table = $this->tableRepository->findById($id);

            if (!$table) {
                return $this->apiResponse(404, 'Table not found');
            }

            $this->tableRepository->delete($id);

            return $this->apiResponse(200, 'Table deleted successfully');
        } catch (\Exception $e) {
            return $this->apiResponse(500, 'Failed to delete table', $e->getMessage());
        }
    }

    /**
     * Check table availability for given date, time and number of guests.
     */
    public function checkAvailability(CheckAvailabilityRequest $request)
    {
        try {
            $carbonDate = Carbon::parse($request->date);
            $availableTables = $this->tableRepository->getAvailableTables(
                $carbonDate,
                $request->time,
                $request->number_of_guests
            );

            if ($availableTables->isEmpty()) {
                return $this->apiResponse(
                    404,
                    'No available tables found for the selected criteria',
                    null,
                    []
                );
            }

            return $this->apiResponse(
                200,
                'Available tables retrieved successfully',
                null,
                TableResource::collection($availableTables)
            );
        } catch (\Exception $e) {
            return $this->apiResponse(500, 'Failed to check table availability', $e->getMessage());
        }
    }
}
