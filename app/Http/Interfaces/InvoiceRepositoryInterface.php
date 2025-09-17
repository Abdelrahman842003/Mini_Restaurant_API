<?php

namespace App\Http\Interfaces;

use App\Models\Invoice;

interface InvoiceRepositoryInterface
{
    public function create(array $data): Invoice;
    public function findById(int $id): ?Invoice;
    public function findByOrderId(int $orderId): ?Invoice;
}
