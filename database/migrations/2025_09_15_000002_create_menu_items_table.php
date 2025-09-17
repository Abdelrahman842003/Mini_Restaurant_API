<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->decimal('price', 8, 2);
            $table->integer('daily_quantity');
            $table->integer('available_quantity');
            $table->timestamps();

            // Add indexes for performance
            $table->index('name');
            $table->index('price');
            $table->index('available_quantity');
        });
    }
    public function down(): void {
        Schema::dropIfExists('menu_items');
    }
};
