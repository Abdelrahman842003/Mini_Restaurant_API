<?php

namespace App\Http\Services;

use App\Http\Interfaces\WaitingListRepositoryInterface;
use App\Models\WaitingListEntry;
use Carbon\Carbon;

class WaitingListService
{
    public function __construct(
        private WaitingListRepositoryInterface $waitingListRepository
    ) {}

    /**
     * Add user to waiting list
     */
    public function addToWaitingList(int $userId, int $numberOfGuests): WaitingListEntry
    {
        // Check if user is already in waiting list
        if ($this->waitingListRepository->isUserInWaitingList($userId)) {
            throw new \Exception('User is already in the waiting list.');
        }

        return $this->waitingListRepository->create([
            'user_id' => $userId,
            'number_of_guests' => $numberOfGuests
        ]);
    }

    /**
     * Get waiting list entries
     */
    public function getWaitingList()
    {
        return $this->waitingListRepository->getOrderedList();
    }

    /**
     * Remove user from waiting list
     */
    public function removeFromWaitingList(int $userId): bool
    {
        return $this->waitingListRepository->removeUser($userId);
    }

    public function getUserWaitingListEntries(int $userId)
    {
        return $this->waitingListRepository->getUserEntries($userId);
    }

    public function getWaitingListByDateAndTime(string $date, string $time)
    {
        $carbonDate = Carbon::parse($date);
        return $this->waitingListRepository->getEntriesByDateAndTime($carbonDate, $time);
    }
}
