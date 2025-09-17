<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('table_id')->constrained('tables')->onDelete('cascade');
            $table->integer('number_of_guests');
            $table->dateTime('reservation_time');
            $table->string('status');
            $table->timestamps();

            // Add indexes for performance
            $table->index('reservation_time');
            $table->index('status');
            $table->index(['user_id', 'reservation_time']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('reservations');
    }
};
