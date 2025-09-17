<?php

namespace App\Http\Repositories;

use App\Http\Interfaces\WaitingListRepositoryInterface;
use App\Models\WaitingListEntry;
use Illuminate\Database\Eloquent\Collection;

class WaitingListRepository implements WaitingListRepositoryInterface
{
    public function create(array $data): WaitingListEntry
    {
        return WaitingListEntry::create($data);
    }

    public function getUserEntries(int $userId): Collection
    {
        return WaitingListEntry::where('user_id', $userId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getEntriesByDateAndTime(\Carbon\Carbon $date, string $time): Collection
    {
        return WaitingListEntry::whereDate('requested_date', $date->toDateString())
            ->where('requested_time', $time)
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function getOrderedList(): Collection
    {
        return WaitingListEntry::with('user')
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function findById(int $id): ?WaitingListEntry
    {
        return WaitingListEntry::find($id);
    }

    public function isUserInWaitingList(int $userId): bool
    {
        return WaitingListEntry::where('user_id', $userId)->exists();
    }

    public function removeUser(int $userId): bool
    {
        return WaitingListEntry::where('user_id', $userId)->delete() > 0;
    }

    public function delete(int $id): bool
    {
        return WaitingListEntry::destroy($id) > 0;
    }
}
