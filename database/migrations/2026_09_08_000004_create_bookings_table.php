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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recurrence_rule_id')->nullable()->constrained('recurrence_rules')->nullOnDelete();
            $table->string('title');
            $table->dateTime('start_time')->comment('UTC timestamp for start time');
            $table->dateTime('end_time')->comment('UTC timestamp for end time');
            $table->string('status')->default('confirmed')->comment('confirmed, cancelled');
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            // Composite index for ultra-fast overlap conflict detection and room schedule querying
            $table->index(['room_id', 'status', 'start_time', 'end_time'], 'idx_room_status_time');
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
