<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Interfaces\MenuItemRepositoryInterface;
use App\Http\Resources\MenuItemResource;
use App\Http\Traits\ApiResponseTrait;

class MenuItemController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private MenuItemRepositoryInterface $menuItemRepository
    ) {}

    /**
     * Display a listing of available menu items.
     */
    public function index()
    {
        try {
            $menuItems = $this->menuItemRepository->getAvailableItems();

            return $this->apiResponse(
                200,
                'Menu items retrieved successfully',
                null,
                MenuItemResource::collection($menuItems)
            );
        } catch (\Exception $e) {
            return $this->apiResponse(500, 'Failed to retrieve menu items', $e->getMessage());
        }
    }
}
