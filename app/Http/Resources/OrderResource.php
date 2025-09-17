<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'reservation_id' => $this->reservation_id,
            'table_id' => $this->table_id,
            'status' => $this->status,
            'total_amount' => $this->total_amount,
            'notes' => $this->notes,
            'special_instructions' => $this->special_instructions,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'user' => new UserResource($this->whenLoaded('user')),
            'order_items' => OrderItemResource::collection($this->whenLoaded('orderItems')),
            'invoice' => new InvoiceResource($this->whenLoaded('invoice')),
            'reservation' => new ReservationResource($this->whenLoaded('reservation')),
            'table' => new TableResource($this->whenLoaded('table')),

            // Computed attributes
            'total_items' => $this->when($this->relationLoaded('orderItems'), function () {
                return $this->orderItems->sum('quantity');
            }),

            'calculated_total' => $this->when($this->relationLoaded('orderItems'), function () {
                return $this->orderItems->sum(function ($item) {
                    return ($item->price * $item->quantity) - $item->discount;
                });
            }),

            'is_paid' => $this->status === 'paid',
            'is_pending' => $this->status === 'pending',
            'is_cancelled' => $this->status === 'cancelled',
        ];
    }
}
