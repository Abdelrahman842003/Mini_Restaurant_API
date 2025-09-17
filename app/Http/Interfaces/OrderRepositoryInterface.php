<?php

namespace App\Http\Interfaces;

use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;

interface OrderRepositoryInterface
{
    public function create(array $data): Order;
    public function findById(int $id): ?Order;
    public function getUserOrders(int $userId): Collection;
    public function updateStatus(int $orderId, string $status): bool;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function getPendingOrders(): Collection;
    public function getPaidOrders(): Collection;
}
