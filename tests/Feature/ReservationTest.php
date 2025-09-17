<?php

use App\Models\User;
use App\Models\Table;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->table = Table::factory()->create(['capacity' => 4]);
});

it('can check table availability', function () {
    $response = $this->getJson('/api/v1/tables/availability?' . http_build_query([
        'date' => now()->addDay()->format('Y-m-d'),
        'time' => '19:00',
        'number_of_guests' => 2
    ]));

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                '*' => [
                    'id',
                    'capacity'
                ]
            ]
        ]);
});

it('can create a reservation for authenticated user', function () {
    $reservationData = [
        'table_id' => $this->table->id,
        'date' => now()->addDay()->format('Y-m-d'),
        'time' => '19:00',
        'number_of_guests' => 2
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/reservations', $reservationData);

    $response->assertCreated()
        ->assertJsonStructure([
            'status',
            'message',
            'data' => [
                'id',
                'user_id',
                'table',
                'number_of_guests',
                'reservation_time',
                'status'
            ]
        ]);

    $this->assertDatabaseHas('reservations', [
        'user_id' => $this->user->id,
        'table_id' => $this->table->id,
        'number_of_guests' => 2,
        'status' => 'confirmed'
    ]);
});

it('cannot create reservation without authentication', function () {
    $reservationData = [
        'table_id' => $this->table->id,
        'date' => now()->addDay()->format('Y-m-d'),
        'time' => '19:00',
        'number_of_guests' => 2
    ];

    $response = $this->postJson('/api/v1/reservations', $reservationData);

    $response->assertUnauthorized();
});

it('cannot reserve unavailable table', function () {
    // Create existing reservation
    Reservation::factory()->create([
        'table_id' => $this->table->id,
        'reservation_time' => now()->addDay()->setTime(19, 0),
        'status' => 'confirmed'
    ]);

    $reservationData = [
        'table_id' => $this->table->id,
        'date' => now()->addDay()->format('Y-m-d'),
        'time' => '19:00',
        'number_of_guests' => 2
    ];

    $response = $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/v1/reservations', $reservationData);

    $response->assertStatus(400);
});
