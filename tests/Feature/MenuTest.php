<?php

use App\Models\User;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

it('can retrieve available menu items', function () {
    // Create menu items
    MenuItem::factory()->create([
        'name' => 'Pizza',
        'price' => 25.99,
        'available_quantity' => 10
    ]);

    MenuItem::factory()->create([
        'name' => 'Burger',
        'price' => 15.99,
        'available_quantity' => 0  // Not available
    ]);

    $response = $this->getJson('/api/v1/menu');

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'price',
                    'available_quantity',
                    'is_available'
                ]
            ]
        ])
        ->assertJsonCount(1, 'data'); // Only available items
});

it('returns empty array when no menu items are available', function () {
    // Create items with zero availability
    MenuItem::factory()->create(['available_quantity' => 0]);

    $response = $this->getJson('/api/v1/menu');

    $response->assertOk()
        ->assertJsonCount(0, 'data');
});
