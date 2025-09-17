<?php

namespace App\Http\Repositories;

use App\Http\Interfaces\MenuItemRepositoryInterface;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MenuItemRepository implements MenuItemRepositoryInterface
{
    public function getAvailableItems(): Collection
    {
        return MenuItem::where('available_quantity', '>', 0)->get();
    }

    public function findById(int $id): ?MenuItem
    {
        return MenuItem::find($id);
    }

    public function updateAvailableQuantity(int $id, int $quantity): bool
    {
        return MenuItem::where('id', $id)->update(['available_quantity' => $quantity]);
    }

    public function resetDailyQuantities(): void
    {
        MenuItem::query()->update(['available_quantity' => DB::raw('daily_quantity')]);
    }

    public function decrementAvailableQuantity(int $id, int $quantity): bool
    {
        return MenuItem::where('id', $id)
            ->where('available_quantity', '>=', $quantity)
            ->decrement('available_quantity', $quantity) > 0;
    }

    public function checkAvailability(int $id, int $requestedQuantity): bool
    {
        $menuItem = $this->findById($id);
        return $menuItem && $menuItem->available_quantity >= $requestedQuantity;
    }
}
