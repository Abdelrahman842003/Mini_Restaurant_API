<?php

namespace App\Http\Interfaces;

use App\Models\WaitingListEntry;
use Illuminate\Database\Eloquent\Collection;

interface WaitingListRepositoryInterface
{
    public function create(array $data): WaitingListEntry;
    public function getUserEntries(int $userId): Collection;
    public function getOrderedList(): Collection;
    public function findById(int $id): ?WaitingListEntry;
    public function isUserInWaitingList(int $userId): bool;
    public function removeUser(int $userId): bool;
    public function delete(int $id): bool;
}
