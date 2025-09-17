<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class TableManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password123')
        ]);
    }

    /** @test */
    public function test_can_get_all_tables_without_authentication()
    {
        // Create some test tables
        Table::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/tables');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        '*' => [
                            'id',
                            'capacity',
                            'created_at',
                            'updated_at'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function test_can_check_table_availability_without_authentication()
    {
        Table::factory()->create(['capacity' => 4]);

        $response = $this->getJson('/api/v1/tables/availability?' . http_build_query([
            'date' => now()->addDay()->format('Y-m-d'),
            'time' => '18:00',
            'number_of_guests' => 2
        ]));

        $response->assertStatus(200);
    }

    /** @test */
    public function test_authenticated_user_can_create_table()
    {
        Sanctum::actingAs($this->user);

        $tableData = [
            'capacity' => 6
        ];

        $response = $this->postJson('/api/v1/tables', $tableData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data' => [
                        'id',
                        'capacity',
                        'created_at',
                        'updated_at'
                    ]
                ]);

        $this->assertDatabaseHas('tables', ['capacity' => 6]);
    }

    /** @test */
    public function test_authenticated_user_can_get_specific_table()
    {
        Sanctum::actingAs($this->user);

        $table = Table::factory()->create(['capacity' => 4]);

        $response = $this->getJson("/api/v1/tables/{$table->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 200,
                    'message' => 'Table retrieved successfully',
                    'data' => [
                        'id' => $table->id,
                        'capacity' => 4
                    ]
                ]);
    }

    /** @test */
    public function test_authenticated_user_can_update_table()
    {
        Sanctum::actingAs($this->user);

        $table = Table::factory()->create(['capacity' => 4]);

        $updateData = [
            'capacity' => 8
        ];

        $response = $this->putJson("/api/v1/tables/{$table->id}", $updateData);

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 200,
                    'message' => 'Table updated successfully',
                    'data' => [
                        'id' => $table->id,
                        'capacity' => 8
                    ]
                ]);

        $this->assertDatabaseHas('tables', [
            'id' => $table->id,
            'capacity' => 8
        ]);
    }

    /** @test */
    public function test_authenticated_user_can_delete_table()
    {
        Sanctum::actingAs($this->user);

        $table = Table::factory()->create(['capacity' => 4]);

        $response = $this->deleteJson("/api/v1/tables/{$table->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'status' => 200,
                    'message' => 'Table deleted successfully'
                ]);

        $this->assertDatabaseMissing('tables', ['id' => $table->id]);
    }

    /** @test */
    public function test_validation_fails_for_invalid_capacity()
    {
        Sanctum::actingAs($this->user);

        // Test with capacity too low
        $response = $this->postJson('/api/v1/tables', ['capacity' => 0]);
        $response->assertStatus(422);

        // Test with capacity too high
        $response = $this->postJson('/api/v1/tables', ['capacity' => 25]);
        $response->assertStatus(422);

        // Test with missing capacity
        $response = $this->postJson('/api/v1/tables', []);
        $response->assertStatus(422);
    }

    /** @test */
    public function test_cannot_access_crud_operations_without_authentication()
    {
        $table = Table::factory()->create();

        // Test POST
        $response = $this->postJson('/api/v1/tables', ['capacity' => 4]);
        $response->assertStatus(401);

        // Test PUT
        $response = $this->putJson("/api/v1/tables/{$table->id}", ['capacity' => 6]);
        $response->assertStatus(401);

        // Test DELETE
        $response = $this->deleteJson("/api/v1/tables/{$table->id}");
        $response->assertStatus(401);

        // Test GET specific (protected)
        $response = $this->getJson("/api/v1/tables/{$table->id}");
        $response->assertStatus(401);
    }

    /** @test */
    public function test_returns_404_for_non_existent_table()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/tables/999');
        $response->assertStatus(404);

        $response = $this->putJson('/api/v1/tables/999', ['capacity' => 4]);
        $response->assertStatus(404);

        $response = $this->deleteJson('/api/v1/tables/999');
        $response->assertStatus(404);
    }

    /** @test */
    public function test_complete_table_management_workflow()
    {
        Sanctum::actingAs($this->user);

        // 1. Create a table
        $createResponse = $this->postJson('/api/v1/tables', ['capacity' => 6]);
        $createResponse->assertStatus(201);
        $tableId = $createResponse->json('data.id');

        // 2. Get all tables (should include our new table)
        $indexResponse = $this->getJson('/api/v1/tables');
        $indexResponse->assertStatus(200);
        $this->assertCount(1, $indexResponse->json('data'));

        // 3. Get specific table
        $showResponse = $this->getJson("/api/v1/tables/{$tableId}");
        $showResponse->assertStatus(200)
                    ->assertJson(['data' => ['capacity' => 6]]);

        // 4. Update the table
        $updateResponse = $this->putJson("/api/v1/tables/{$tableId}", ['capacity' => 8]);
        $updateResponse->assertStatus(200)
                      ->assertJson(['data' => ['capacity' => 8]]);

        // 5. Check availability (public endpoint)
        $availabilityResponse = $this->getJson('/api/v1/tables/availability?' . http_build_query([
            'date' => now()->addDay()->format('Y-m-d'),
            'time' => '19:00',
            'number_of_guests' => 4
        ]));
        $availabilityResponse->assertStatus(200);

        // 6. Delete the table
        $deleteResponse = $this->deleteJson("/api/v1/tables/{$tableId}");
        $deleteResponse->assertStatus(200);

        // 7. Verify table is deleted
        $this->assertDatabaseMissing('tables', ['id' => $tableId]);
    }
}
