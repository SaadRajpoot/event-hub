<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['waiting', 'notified', 'converted', 'expired'])->default('waiting');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['attendee_id', 'ticket_type_id']);
            $table->index(['ticket_type_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlists');
    }
};
