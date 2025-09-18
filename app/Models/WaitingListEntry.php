<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitingListEntry extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'number_of_guests',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number_of_guests' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // Relationships

    /**
     * Get the user that owns this waiting list entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes

    /**
     * Scope a query to order by creation time (FIFO - First In, First Out).
     */
    public function scopeByCreationOrder($query)
    {
        return $query->orderBy('created_at', 'asc');
    }

    /**
     * Scope a query to find entries for a specific number of guests or less.
     */
    public function scopeForGuestCount($query, $maxGuests)
    {
        return $query->where('number_of_guests', '<=', $maxGuests);
    }

    // Helper methods

    /**
     * Get the position in the waiting list.
     */
    public function getPositionAttribute(): int
    {
        return self::where('created_at', '<', $this->created_at)->count() + 1;
    }

    /**
     * Get the estimated wait time in minutes.
     */
    public function getEstimatedWaitTimeAttribute(): int
    {
        // Rough estimate: 30 minutes per position ahead
        return max(0, ($this->position - 1) * 30);
    }
}
