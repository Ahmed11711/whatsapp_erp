<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            // sender_id: null for inbound (customer), user_id for outbound (agent)
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            // receiver_id: user_id for inbound (agent), null for outbound (customer)
            $table->foreignId('receiver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('content');
            // direction: 'inbound' (customer -> agent) or 'outbound' (agent -> customer)
            $table->string('direction')->default('outbound');
            // status: 'sent', 'delivered', 'read', 'received' (for inbound)
            $table->string('status')->default('sent');
            // Twilio message SID for tracking
            $table->string('twilio_message_sid')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};

