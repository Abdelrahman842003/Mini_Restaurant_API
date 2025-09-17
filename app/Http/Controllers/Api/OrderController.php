<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Interfaces\MenuItemRepositoryInterface;
use App\Http\Interfaces\OrderRepositoryInterface;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Http\Traits\ApiResponseTrait;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private MenuItemRepositoryInterface $menuItemRepository
    ) {}

    /**
     * Store a newly created order.
     */
    public function store(StoreOrderRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $validated = $request->validated();
                $orderItems = $validated['items'];

                // Validate all items availability first
                foreach ($orderItems as $item) {
                    if (!$this->menuItemRepository->checkAvailability($item['menu_item_id'], $item['quantity'])) {
                        $menuItem = $this->menuItemRepository->findById($item['menu_item_id']);
                        throw new \Exception("Insufficient quantity available for {$menuItem->name}. Available: {$menuItem->available_quantity}");
                    }
                }

                // Calculate total amount
                $totalAmount = 0;
                foreach ($orderItems as $item) {
                    $menuItem = $this->menuItemRepository->findById($item['menu_item_id']);
                    $totalAmount += $menuItem->price * $item['quantity'];
                }

                // Create the order with correct field name
                $order = $this->orderRepository->create([
                    'user_id' => auth()->id(),
                    'total_amount' => $totalAmount,
                    'status' => 'pending',
                    'notes' => $validated['special_instructions'] ?? null // استخدام 'notes' بدلاً من 'special_instructions'
                ]);

                // Create order items and update inventory
                foreach ($orderItems as $item) {
                    $menuItem = $this->menuItemRepository->findById($item['menu_item_id']);

                    // Create order item with correct field names matching the database schema
                    OrderItem::create([
                        'order_id' => $order->id,
                        'menu_item_id' => $item['menu_item_id'],
                        'quantity' => $item['quantity'],
                        'price' => $menuItem->price, // استخدام 'price' بدلاً من 'unit_price'
                        'discount' => 0 // إضافة حقل discount المطلوب
                    ]);

                    // Decrement available quantity
                    $this->menuItemRepository->decrementAvailableQuantity(
                        $item['menu_item_id'],
                        $item['quantity']
                    );
                }

                // Reload order with relationships
                $order = $this->orderRepository->findById($order->id);

                return $this->apiResponse(
                    201,
                    'Order created successfully',
                    null,
                    new OrderResource($order)
                );
            });
        } catch (\Exception $e) {
            \Log::error('Order creation failed: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->apiResponse(400, 'Failed to create order', $e->getMessage());
        }
    }

    /**
     * Display the authenticated user's orders.
     */
    public function index(Request $request)
    {
        try {
            $orders = $this->orderRepository->getUserOrders(auth()->id());

            return $this->apiResponse(
                200,
                'User orders retrieved successfully',
                null,
                OrderResource::collection($orders)
            );
        } catch (\Exception $e) {
            return $this->apiResponse(500, 'Failed to retrieve orders', $e->getMessage());
        }
    }

    /**
     * Display the specified order.
     */
    public function show(int $id)
    {
        try {
            $order = $this->orderRepository->findById($id);

            if (!$order) {
                return $this->apiResponse(404, 'Order not found');
            }

            // Check if the order belongs to the authenticated user
            if ($order->user_id !== auth()->id()) {
                return $this->apiResponse(403, 'Unauthorized to view this order');
            }

            return $this->apiResponse(
                200,
                'Order retrieved successfully',
                null,
                new OrderResource($order)
            );
        } catch (\Exception $e) {
            return $this->apiResponse(500, 'Failed to retrieve order', $e->getMessage());
        }
    }
}
