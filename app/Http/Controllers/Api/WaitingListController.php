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
     * Display the authenticated user's waiting list entries.
     */
    public function index(Request $request)
    {
        try {
            // Get authenticated user ID
            $userId = auth()->id();

            if (!$userId) {
                return $this->apiResponse(401, 'Authentication required');
            }

            // Get user's waiting list entries
            $waitingListEntries = $this->waitingListService->getUserWaitingListEntries($userId);

            // If no entries found, return empty array with success message
            if ($waitingListEntries->isEmpty()) {
                return $this->apiResponse(
                    200,
                    'No waiting list entries found',
                    null,
                    []
                );
            }

            return $this->apiResponse(
                200,
                'Waiting list entries retrieved successfully',
                null,
                WaitingListResource::collection($waitingListEntries)
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to retrieve waiting list entries', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->apiResponse(500, 'Failed to retrieve waiting list entries', $e->getMessage());
        }
    }

    /**
     * Store a newly created waiting list entry.
     */
    public function store(StoreWaitingListRequest $request)
    {
        try {
            $validated = $request->validated();
            $userId = auth()->id();

            if (!$userId) {
                return $this->apiResponse(401, 'Authentication required');
            }

            // Check if user is already in waiting list
            $existingEntry = $this->waitingListService->getUserWaitingListEntries($userId)->first();
            if ($existingEntry) {
                return $this->apiResponse(
                    400,
                    'You are already in the waiting list',
                    null,
                    new WaitingListResource($existingEntry)
                );
            }

            $waitingListEntry = $this->waitingListService->addToWaitingList(
                $userId,
                $validated['number_of_guests']
            );

            \Illuminate\Support\Facades\Log::info('User added to waiting list', [
                'user_id' => $userId,
                'entry_id' => $waitingListEntry->id,
                'number_of_guests' => $validated['number_of_guests']
            ]);

            return $this->apiResponse(
                201,
                'Added to waiting list successfully',
                null,
                new WaitingListResource($waitingListEntry->load('user'))
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to add to waiting list', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return $this->apiResponse(500, 'Failed to add to waiting list', $e->getMessage());
        }
    }

    /**
     * Remove the authenticated user from waiting list.
     */
    public function destroy(Request $request, $id = null)
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                return $this->apiResponse(401, 'Authentication required');
            }

            // If ID is provided, check ownership
            if ($id) {
                $entry = \App\Models\WaitingListEntry::find($id);
                if (!$entry) {
                    return $this->apiResponse(404, 'Waiting list entry not found');
                }

                if ($entry->user_id !== $userId) {
                    return $this->apiResponse(403, 'Unauthorized to delete this entry');
                }

                $removed = $entry->delete();
            } else {
                // Remove by user ID
                $removed = $this->waitingListService->removeFromWaitingList($userId);
            }

            if (!$removed) {
                return $this->apiResponse(404, 'No waiting list entry found for this user');
            }

            \Illuminate\Support\Facades\Log::info('User removed from waiting list', [
                'user_id' => $userId,
                'entry_id' => $id
            ]);

            return $this->apiResponse(200, 'Removed from waiting list successfully');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to remove from waiting list', [
                'user_id' => auth()->id(),
                'entry_id' => $id,
                'error' => $e->getMessage()
            ]);

            return $this->apiResponse(500, 'Failed to remove from waiting list', $e->getMessage());
        }
    }
}
