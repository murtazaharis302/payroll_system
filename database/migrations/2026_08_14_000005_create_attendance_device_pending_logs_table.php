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
        Schema::create('attendance_device_pending_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_device_id')->constrained('attendance_devices')->cascadeOnDelete();
            $table->string('device_user_id');
            $table->dateTime('logged_at');
            $table->string('mode')->nullable();
            $table->integer('start_pos')->nullable();
            $table->integer('next_pos')->nullable();
            $table->string('status')->default('pending'); // 'pending', 'processed', 'ignored'
            $table->timestamp('processed_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['attendance_device_id', 'device_user_id', 'logged_at'], 'device_pending_log_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_device_pending_logs');
    }
};
