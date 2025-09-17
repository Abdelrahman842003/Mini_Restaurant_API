<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->integer('payment_option')->comment('1: Full Service (14% tax + 20% service), 2: Service Only (15% service)');
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('service_charge_amount', 10, 2)->default(0);
            $table->decimal('final_amount', 10, 2);

            // حقول بوابات الدفع - PayPal و Stripe فقط
            $table->enum('payment_gateway', ['paypal', 'stripe'])->nullable()->comment('Payment gateway: paypal or stripe only');
            $table->enum('payment_status', ['pending', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->string('transaction_id')->nullable()->comment('External transaction ID from payment gateway');
            $table->json('payment_details')->nullable()->comment('Additional payment details and webhook data');

            $table->timestamps();

            // Indexes للأداء
            $table->index('payment_gateway');
            $table->index('payment_status');
            $table->index('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
