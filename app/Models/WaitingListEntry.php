<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        ];
    }

    /**
     * Indicates if the model should be timestamped.
     * Only created_at is used for waiting list entries.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at'];

    // Relationships

    /**
     * Get the user that owns this waiting list entry.
     */
    public function user()
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
    public function getPositionAttribute()
    {
        return self::where('created_at', '<', $this->created_at)->count() + 1;
    }

    /**
     * Remove this entry from the waiting list.
     */
    public function remove()
    {
        $this->delete();
    }

    /**
     * Get the next entry in the waiting list that can accommodate the given table capacity.
     */
    public static function getNextForTable(Table $table)
    {
        return self::forGuestCount($table->capacity)
            ->byCreationOrder()
            ->first();
    }
}
