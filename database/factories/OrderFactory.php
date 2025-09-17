<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reservation_id' => null, // Optional
            'status' => $this->faker->randomElement(['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled', 'paid']),
            'total_amount' => $this->faker->randomFloat(2, 20, 500),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * إنشاء طلب معلق (في انتظار الدفع)
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * إنشاء طلب مدفوع
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
        ]);
    }

    /**
     * إنشاء طلب ملغي
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * إنشاء طلب بمبلغ محدد
     */
    public function withAmount(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'total_amount' => $amount,
        ]);
    }

    /**
     * إنشاء طلب لمستخدم محدد
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * إنشاء طلب مع حجز
     */
    public function withReservation(): static
    {
        return $this->state(fn (array $attributes) => [
            'reservation_id' => Reservation::factory(),
        ]);
    }
}
