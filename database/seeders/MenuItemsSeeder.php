<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MenuItem;

class MenuItemsSeeder extends Seeder
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
                'name' => 'Beef Burger',
                'description' => 'Juicy beef burger with cheese and vegetables',
                'price' => 18.50,
                'daily_quantity' => 30,
                'available_quantity' => 30,
            ],
            [
                'name' => 'Pasta Carbonara',
                'description' => 'Classic Italian pasta with cream sauce',
                'price' => 22.00,
                'daily_quantity' => 40,
                'available_quantity' => 40,
            ],
            [
                'name' => 'Caesar Salad',
                'description' => 'Fresh Caesar salad with crispy croutons',
                'price' => 15.75,
                'daily_quantity' => 25,
                'available_quantity' => 25,
            ],
            [
                'name' => 'Fish and Chips',
                'description' => 'Traditional fish and chips with tartar sauce',
                'price' => 28.00,
                'daily_quantity' => 20,
                'available_quantity' => 20,
            ],
            [
                'name' => 'Vegetable Stir Fry',
                'description' => 'Mixed vegetables stir-fried with Asian spices',
                'price' => 16.25,
                'daily_quantity' => 35,
                'available_quantity' => 35,
            ],
            [
                'name' => 'Chocolate Cake',
                'description' => 'Rich chocolate cake with vanilla ice cream',
                'price' => 12.50,
                'daily_quantity' => 15,
                'available_quantity' => 15,
            ],
            [
                'name' => 'Fresh Orange Juice',
                'description' => 'Freshly squeezed orange juice',
                'price' => 6.00,
                'daily_quantity' => 100,
                'available_quantity' => 100,
            ],
        ];

        foreach ($menuItems as $item) {
            MenuItem::create($item);
        }
    }
}
