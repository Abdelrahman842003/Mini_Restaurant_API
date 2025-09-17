<?php

namespace App\Http\Repositories;

use App\Http\Interfaces\InvoiceRepositoryInterface;
use App\Models\Invoice;

class InvoiceRepository implements InvoiceRepositoryInterface
{
    public function create(array $data): Invoice
    {
        return Invoice::create($data);
    }

    public function findById(int $id): ?Invoice
    {
        return Invoice::with('order')->find($id);
    }

    public function findByOrderId(int $orderId): ?Invoice
    {
        return Invoice::where('order_id', $orderId)->first();
    }
}
