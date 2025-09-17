<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
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
            'order_id' => $this->order_id,
            'menu_item_id' => $this->menu_item_id,
            'quantity' => $this->quantity,
            'price' => $this->price,
            'discount' => $this->discount,
            'subtotal' => ($this->price * $this->quantity) - $this->discount,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'menu_item' => new MenuItemResource($this->whenLoaded('menuItem')),
        ];
    }
}
