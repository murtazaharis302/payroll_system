<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees_master')->onDelete('cascade');
            $table->string('biometric_id'); // Device user ID
            $table->dateTime('log_datetime'); // Timestamp from biometric device
            $table->date('log_date'); // Date only (for grouping)
            $table->enum('log_type', ['in', 'out', 'unknown'])->default('unknown');
            $table->string('device_name')->nullable(); // Which device
            $table->enum('import_status', ['pending', 'processed'])->default('pending');
            $table->timestamps();
            
            $table->index(['employee_id', 'log_date']);
            $table->index('log_date');
            $table->index('import_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
