<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Table;

class TablesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tables = [
            ['table_number' => 'T001', 'capacity' => 2, 'location' => 'Window Side'],
            ['table_number' => 'T002', 'capacity' => 4, 'location' => 'Main Hall'],
            ['table_number' => 'T003', 'capacity' => 6, 'location' => 'Private Section'],
            ['table_number' => 'T004', 'capacity' => 2, 'location' => 'Balcony'],
            ['table_number' => 'T005', 'capacity' => 8, 'location' => 'VIP Section'],
            ['table_number' => 'T006', 'capacity' => 4, 'location' => 'Garden View'],
            ['table_number' => 'T007', 'capacity' => 2, 'location' => 'Corner'],
            ['table_number' => 'T008', 'capacity' => 6, 'location' => 'Main Hall'],
        ];

        foreach ($tables as $table) {
            Table::create($table);
        }
    }
}
