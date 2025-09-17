<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Table extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'capacity',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    // Relationships

    /**
     * Get the reservations for this table.
     */
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Get the current reservation for this table (if any).
     */
    public function currentReservation()
    {
        return $this->hasOne(Reservation::class)
            ->where('status', 'confirmed')
            ->where('reservation_time', '<=', now())
            ->orderBy('reservation_time', 'desc');
    }

    /**
     * Check if the table is available at a specific time.
     */
    public function isAvailableAt($dateTime)
    {
        return !$this->reservations()
            ->where('status', 'confirmed')
            ->where('reservation_time', $dateTime)
            ->exists();
    }
}
