<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWaitingListRequest;
use App\Http\Resources\WaitingListResource;
use App\Http\Services\WaitingListService;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class WaitingListController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private WaitingListService $waitingListService
    ) {}

    /**
     * Store a newly created waiting list entry.
     */
    public function store(StoreWaitingListRequest $request)
    {
        try {
            $validated = $request->validated();

            $waitingListEntry = $this->waitingListService->addToWaitingList(
                auth()->id(),
                $validated['number_of_guests']
            );

            return $this->apiResponse(
                201,
                'Added to waiting list successfully',
                null,
                new WaitingListResource($waitingListEntry)
            );
        } catch (\Exception $e) {
            return $this->apiResponse(400, 'Failed to add to waiting list', $e->getMessage());
        }
    }

    /**
     * Display the authenticated user's waiting list entries.
     */
    public function index(Request $request)
    {
        try {
            $waitingListEntries = $this->waitingListService->getUserWaitingListEntries(auth()->id());

            return $this->apiResponse(
                200,
                'Waiting list entries retrieved successfully',
                null,
                WaitingListResource::collection($waitingListEntries)
            );
        } catch (\Exception $e) {
            return $this->apiResponse(500, 'Failed to retrieve waiting list entries', $e->getMessage());
        }
    }

    /**
     * Remove the authenticated user from waiting list.
     */
    public function destroy()
    {
        try {
            $removed = $this->waitingListService->removeFromWaitingList(auth()->id());

            if (!$removed) {
                return $this->apiResponse(404, 'No waiting list entry found for this user');
            }

            return $this->apiResponse(200, 'Removed from waiting list successfully');
        } catch (\Exception $e) {
            return $this->apiResponse(500, 'Failed to remove from waiting list', $e->getMessage());
        }
    }
}
