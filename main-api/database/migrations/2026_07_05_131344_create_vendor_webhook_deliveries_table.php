<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->json('payload');
            $table->string('url');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('response_body')->nullable();
            $table->unsignedTinyInteger('attempt_count')->default(0);
            $table->enum('status', ['pending', 'delivered', 'failed'])->default('pending');
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
            $table->index('next_retry_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_webhook_deliveries');
    }
};
