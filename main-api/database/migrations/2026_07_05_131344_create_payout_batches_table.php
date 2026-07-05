<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number')->unique();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->decimal('gross_amount', 10, 2)->comment('Sum of order item subtotals');
            $table->decimal('platform_fee', 10, 2);
            $table->decimal('net_amount', 10, 2)->comment('Amount to be paid to vendor');
            $table->unsignedInteger('order_count');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('payment_reference')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_batches');
    }
};
