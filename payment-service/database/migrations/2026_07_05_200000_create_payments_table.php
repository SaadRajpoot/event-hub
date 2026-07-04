<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_reference')->unique();
            $table->string('order_reference');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('MYR');
            $table->string('payment_method')->nullable(); // fpx, card, etc.
            $table->string('status')->default('pending'); // pending, completed, failed, expired, refunded
            $table->string('provider_reference')->nullable();
            $table->json('provider_response')->nullable();
            $table->string('callback_url'); // main-api webhook URL
            $table->string('redirect_url')->nullable(); // frontend redirect after payment
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('webhook_delivered_at')->nullable();
            $table->unsignedTinyInteger('webhook_attempts')->default(0);
            $table->timestamps();

            $table->index('order_reference');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
