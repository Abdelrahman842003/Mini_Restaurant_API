<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class MenuItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $menuItems = [
            [
                'name' => 'Grilled Chicken',
                'description' => 'Delicious grilled chicken with herbs and spices',
                'price' => 25.99,
                'daily_quantity' => 50,
                'available_quantity' => 50,
            ],
            [
                'name' => 'Beef Steak',
                'description' => 'Premium beef steak cooked to perfection',
                'price' => 45.99,
                'daily_quantity' => 30,
                'available_quantity' => 30,
            ],
            [
                'name' => 'Fish & Chips',
                'description' => 'Fresh fish with crispy golden chips',
                'price' => 18.99,
                'daily_quantity' => 40,
                'available_quantity' => 40,
            ],
            [
                'name' => 'Vegetarian Pizza',
                'description' => 'Wood-fired pizza with fresh vegetables',
                'price' => 22.99,
                'daily_quantity' => 60,
                'available_quantity' => 60,
            ],
            [
                'name' => 'Caesar Salad',
                'description' => 'Fresh romaine lettuce with caesar dressing',
                'price' => 12.99,
                'daily_quantity' => 80,
                'available_quantity' => 80,
            ],
            [
                'name' => 'Chocolate Cake',
                'description' => 'Rich chocolate cake with chocolate frosting',
                'price' => 8.99,
                'daily_quantity' => 20,
                'available_quantity' => 20,
            ],
        ];

        foreach ($menuItems as $item) {
            MenuItem::create($item);
        }
    }
}
