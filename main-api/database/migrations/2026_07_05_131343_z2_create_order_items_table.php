<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('subtotal', 10, 2);
            $table->string('ticket_code')->unique()->comment('QR code value for ticket');
            $table->enum('status', ['active', 'used', 'cancelled', 'refunded'])->default('active');
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            $table->index(['order_id']);
            $table->index(['ticket_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
