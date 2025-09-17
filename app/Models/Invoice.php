<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'payment_option',
        'tax_amount',
        'service_charge_amount',
        'final_amount',
        'payment_gateway',
        'payment_status',
        'transaction_id',
        'payment_details',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tax_amount' => 'decimal:2',
            'service_charge_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'payment_details' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // Relationships

    /**
     * Get the order that owns this invoice.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // Status checks

    /**
     * Check if the payment is completed.
     */
    public function isPaid(): bool
    {
        return $this->payment_status === 'completed';
    }

    /**
     * Check if the payment is pending.
     */
    public function isPending(): bool
    {
        return $this->payment_status === 'pending';
    }

    /**
     * Check if the payment has failed.
     */
    public function isFailed(): bool
    {
        return $this->payment_status === 'failed';
    }

    /**
     * Check if the payment was cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->payment_status === 'cancelled';
    }

    // Accessors

    /**
     * Get the payment gateway name.
     */
    public function getPaymentGatewayNameAttribute(): string
    {
        return match($this->payment_gateway) {
            'paypal' => 'PayPal',
            'stripe' => 'Stripe',
            default => 'Unknown'
        };
    }

    /**
     * Get the payment status description.
     */
    public function getPaymentStatusDescriptionAttribute(): string
    {
        return match($this->payment_status) {
            'pending' => 'Payment is being processed',
            'completed' => 'Payment completed successfully',
            'failed' => 'Payment failed',
            'cancelled' => 'Payment was cancelled',
            default => 'Unknown status'
        };
    }

    /**
     * Get the masked transaction ID for display.
     */
    public function getMaskedTransactionIdAttribute(): string
    {
        if (!$this->transaction_id) {
            return 'N/A';
        }

        $id = $this->transaction_id;
        if (strlen($id) > 8) {
            return substr($id, 0, 4) . '****' . substr($id, -4);
        }

        return $id;
    }

    // Scopes

    /**
     * Scope for paid invoices.
     */
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'completed');
    }

    /**
     * Scope for pending invoices.
     */
    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    /**
     * Scope for a specific payment gateway.
     */
    public function scopeByGateway($query, string $gateway)
    {
        return $query->where('payment_gateway', $gateway);
    }
}
