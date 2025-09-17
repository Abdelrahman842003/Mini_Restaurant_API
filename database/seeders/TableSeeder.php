<?php

namespace Database\Seeders;

use App\Models\Table;
use Illuminate\Database\Seeder;

class TableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tables = [
            ['table_number' => 'T001', 'capacity' => 2, 'status' => 'available'],
            ['table_number' => 'T002', 'capacity' => 2, 'status' => 'available'],
            ['table_number' => 'T003', 'capacity' => 4, 'status' => 'available'],
            ['table_number' => 'T004', 'capacity' => 4, 'status' => 'available'],
            ['table_number' => 'T005', 'capacity' => 4, 'status' => 'available'],
            ['table_number' => 'T006', 'capacity' => 6, 'status' => 'available'],
            ['table_number' => 'T007', 'capacity' => 6, 'status' => 'available'],
            ['table_number' => 'T008', 'capacity' => 8, 'status' => 'available'],
            ['table_number' => 'T009', 'capacity' => 8, 'status' => 'available'],
            ['table_number' => 'T010', 'capacity' => 10, 'status' => 'available'],
        ];

        foreach ($tables as $table) {
            Table::create($table);
        }
    }
}
