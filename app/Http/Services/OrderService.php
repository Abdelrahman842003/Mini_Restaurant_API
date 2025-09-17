<?php

namespace App\Http\Services;

use App\Http\Interfaces\MenuItemRepositoryInterface;
use App\Http\Interfaces\OrderRepositoryInterface;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private MenuItemRepositoryInterface $menuItemRepository
    ) {}

    /**
     * Create a new order
     */
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $orderItems = $data['items'];

            // Validate all items availability first
            foreach ($orderItems as $item) {
                if (!$this->menuItemRepository->checkAvailability(
                    $item['menu_item_id'],
                    $item['quantity']
                )) {
                    $menuItem = $this->menuItemRepository->findById($item['menu_item_id']);
                    throw new \Exception(
                        "Insufficient quantity available for {$menuItem->name}. Available: {$menuItem->available_quantity}"
                    );
                }
            }

            // Calculate total cost with discounts
            $totalCost = $this->calculateOrderTotal($orderItems);

            // Create the order
            $order = $this->orderRepository->create([
                'user_id' => $data['user_id'],
                'reservation_id' => $data['reservation_id'] ?? null,
                'total_amount' => $totalCost,
                'status' => 'pending'
            ]);

            // Create order items and update inventory
            foreach ($orderItems as $item) {
                $menuItem = $this->menuItemRepository->findById($item['menu_item_id']);
                $discount = $item['discount'] ?? 0;
                $finalPrice = $menuItem->price - $discount;

                OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $item['menu_item_id'],
                    'quantity' => $item['quantity'],
                    'price' => $finalPrice,
                    'discount' => $discount
                ]);

                // Decrement available quantity
                $this->menuItemRepository->decrementAvailableQuantity(
                    $item['menu_item_id'],
                    $item['quantity']
                );
            }

            return $this->orderRepository->findById($order->id);
        });
    }

    /**
     * Calculate order total with discounts
     */
    private function calculateOrderTotal(array $orderItems): float
    {
        $total = 0;

        foreach ($orderItems as $item) {
            $menuItem = $this->menuItemRepository->findById($item['menu_item_id']);
            $discount = $item['discount'] ?? 0;
            $finalPrice = $menuItem->price - $discount;
            $total += $finalPrice * $item['quantity'];
        }

        return $total;
    }

    /**
     * Get user orders
     */
    public function getUserOrders(int $userId)
    {
        return $this->orderRepository->getUserOrders($userId);
    }

    /**
     * Get order by ID with user verification
     */
    public function getOrderForUser(int $orderId, int $userId): ?Order
    {
        $order = $this->orderRepository->findById($orderId);

        if (!$order || $order->user_id !== $userId) {
            return null;
        }

        return $order;
    }
}
