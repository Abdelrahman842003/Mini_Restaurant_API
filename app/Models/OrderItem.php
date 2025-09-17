<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'menu_item_id',
        'quantity',
        'price',
        'discount',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'price' => 'decimal:2',
            'discount' => 'decimal:2',
        ];
    }

    // Relationships

    /**
     * Get the order that owns this item.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the menu item for this order item.
     */
    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    /**
     * Get the total price for this item (price * quantity - discount)
     */
    public function getTotalPriceAttribute(): float
    {
        return ($this->price * $this->quantity) - $this->discount;
    }

    /**
     * Get the discounted unit price
     */
    public function getDiscountedPriceAttribute(): float
    {
        return $this->price - ($this->discount / $this->quantity);
    }

    // Helper methods

    /**
     * Calculate the subtotal for this order item.
     */
    public function getSubtotalAttribute()
    {
        return ($this->price * $this->quantity) - $this->discount;
    }

    /**
     * Calculate the total discount percentage.
     */
    public function getDiscountPercentageAttribute()
    {
        $total = $this->price * $this->quantity;
        return $total > 0 ? ($this->discount / $total) * 100 : 0;
    }
}
