<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'table_id',
        'number_of_guests',
        'reservation_time',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reservation_time' => 'datetime',
            'number_of_guests' => 'integer',
        ];
    }

    // Relationships

    /**
     * Get the user that owns the reservation.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the table for this reservation.
     */
    public function table()
    {
        return $this->belongsTo(Table::class);
    }

    /**
     * Get the orders associated with this reservation.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    // Scopes

    /**
     * Scope a query to only include confirmed reservations.
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Scope a query to only include today's reservations.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('reservation_time', today());
    }

    /**
     * Scope a query to only include upcoming reservations.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('reservation_time', '>', now());
    }

    // Helper methods

    /**
     * Check if the reservation is confirmed.
     */
    public function isConfirmed()
    {
        return $this->status === 'confirmed';
    }

    /**
     * Check if the reservation is cancelled.
     */
    public function isCancelled()
    {
        return $this->status === 'cancelled';
    }

    /**
     * Confirm the reservation.
     */
    public function confirm()
    {
        $this->update(['status' => 'confirmed']);
    }

    /**
     * Cancel the reservation.
     */
    public function cancel()
    {
        $this->update(['status' => 'cancelled']);
    }
}
