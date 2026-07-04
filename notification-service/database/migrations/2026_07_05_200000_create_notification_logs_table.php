<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // order_confirmed, order_cancelled, ticket_issued, etc.
            $table->string('channel'); // email, sms
            $table->string('recipient'); // email address or phone number
            $table->string('subject')->nullable();
            $table->json('payload');
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->string('provider_reference')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['type', 'status']);
            $table->index('recipient');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
