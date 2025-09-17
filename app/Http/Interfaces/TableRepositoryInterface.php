<?php

namespace App\Http\Interfaces;

use App\Models\Table;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

interface TableRepositoryInterface
{
    public function getAll(): Collection;
    public function getAvailableTables(Carbon $date, string $time, int $numberOfGuests): Collection;
    public function findById(int $id): ?Table;
    public function isTableAvailable(int $tableId, Carbon $date, string $time): bool;
    public function getByMinimumCapacity(int $capacity): Collection;
    public function create(array $data): Table;
    public function update(int $id, array $data): Table;
    public function delete(int $id): bool;
}
