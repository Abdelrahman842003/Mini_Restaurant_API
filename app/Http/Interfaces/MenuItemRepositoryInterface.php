<?php

namespace App\Http\Interfaces;

use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Collection;

interface MenuItemRepositoryInterface
{
    public function getAvailableItems(): Collection;
    public function findById(int $id): ?MenuItem;
    public function updateAvailableQuantity(int $id, int $quantity): bool;
    public function resetDailyQuantities(): void;
    public function decrementAvailableQuantity(int $id, int $quantity): bool;
    public function checkAvailability(int $id, int $requestedQuantity): bool;
}
