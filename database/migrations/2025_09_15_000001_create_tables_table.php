<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->string('table_number')->unique();
            $table->enum('status', ['available', 'occupied', 'reserved', 'maintenance'])->default('available');
            $table->integer('capacity');
            $table->timestamps();

            // Add indexes for performance
            $table->index('capacity');
        });
    }
    public function down(): void {
        Schema::dropIfExists('tables');
    }
};
