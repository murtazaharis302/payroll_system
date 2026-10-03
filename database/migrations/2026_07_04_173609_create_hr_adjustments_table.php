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
        Schema::create('hr_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees_master')->onDelete('cascade');
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->enum('adjustment_type', ['attendance', 'income', 'deduction']);
            
            // For attendance adjustments
            $table->date('attendance_date')->nullable();
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->decimal('hours_worked', 5, 2)->nullable();
            $table->decimal('overtime_hours', 5, 2)->nullable();
            
            // For income/deduction adjustments
            $table->foreignId('income_type_id')->nullable()->constrained('income_types')->onDelete('cascade');
            $table->foreignId('deduction_type_id')->nullable()->constrained('deduction_types')->onDelete('cascade');
            $table->decimal('amount', 10, 2)->nullable();
            
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->date('effective_date');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hr_adjustments');
    }
};
