<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'price',
        'daily_quantity',
        'available_quantity',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'daily_quantity' => 'integer',
            'available_quantity' => 'integer',
        ];
    }

    // Relationships

    /**
     * Get the order items for this menu item.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the orders that include this menu item.
     */
    public function orders()
    {
        return $this->belongsToMany(Order::class, 'order_items')
            ->withPivot('quantity', 'price', 'discount')
            ->withTimestamps();
    }

    // Helper methods

    /**
     * Check if the item is available.
     */
    public function isAvailable()
    {
        return $this->available_quantity > 0;
    }

    /**
     * Reduce available quantity by specified amount.
     */
    public function reduceQuantity($quantity)
    {
        if ($this->available_quantity >= $quantity) {
            $this->decrement('available_quantity', $quantity);
            return true;
        }
        return false;
    }

    /**
     * Reset daily quantity (typically called at start of day).
     */
    public function resetDailyQuantity()
    {
        $this->update(['available_quantity' => $this->daily_quantity]);
    }
}
