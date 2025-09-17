<?php

namespace App\Http\Repositories;

use App\Http\Interfaces\OrderRepositoryInterface;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

class OrderRepository implements OrderRepositoryInterface
{
    public function create(array $data): Order
    {
        return Order::create($data);
    }

    public function findById(int $id): ?Order
    {
        return Order::with(['orderItems.menuItem', 'user', 'reservation'])->find($id);
    }

    public function getUserOrders(int $userId): Collection
    {
        return Order::where('user_id', $userId)
            ->with(['orderItems.menuItem', 'reservation'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function updateStatus(int $orderId, string $status): bool
    {
        return Order::where('id', $orderId)->update(['status' => $status]);
    }

    public function update(int $id, array $data): bool
    {
        return Order::where('id', $id)->update($data);
    }

    public function delete(int $id): bool
    {
        return Order::destroy($id) > 0;
    }

    public function getPendingOrders(): Collection
    {
        return Order::where('status', 'pending')
            ->with(['orderItems.menuItem', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getPaidOrders(): Collection
    {
        return Order::where('status', 'paid')
            ->with(['orderItems.menuItem', 'user'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
