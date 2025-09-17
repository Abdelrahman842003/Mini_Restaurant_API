<?php

namespace App\Http\Services;

use App\Http\Interfaces\MenuItemRepositoryInterface;

class MenuService
{
    public function __construct(
        private MenuItemRepositoryInterface $menuItemRepository
    ) {}

    /**
     * Get all available menu items
     */
    public function getAvailableMenuItems()
    {
        return $this->menuItemRepository->getAvailableItems();
    }

    /**
     * Reset daily quantities for all menu items
     */
    public function resetDailyQuantities(): void
    {
        $this->menuItemRepository->resetDailyQuantities();
    }

    /**
     * Check if menu item is available in requested quantity
     */
    public function checkItemAvailability(int $menuItemId, int $quantity): bool
    {
        return $this->menuItemRepository->checkAvailability($menuItemId, $quantity);
    }
}
