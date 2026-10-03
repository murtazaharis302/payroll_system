<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vale_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees_master')->onDelete('cascade');
            $table->foreignId('deduction_type_id')->constrained('deduction_types')->onDelete('cascade'); // LOAN or VALE type
            $table->string('loan_number')->unique(); // LOAN-2026-001
            $table->decimal('principal_amount', 10, 2); // Original loan amount
            $table->decimal('total_paid', 10, 2)->default(0); // Total paid so far
            $table->decimal('balance', 10, 2); // Remaining balance
            $table->decimal('installment_amount', 10, 2); // Per payroll deduction
            $table->integer('installment_count')->default(0); // Number of installments
            $table->date('loan_date'); // Date loan was granted
            $table->date('start_deduction_date')->nullable(); // When deduction starts
            $table->enum('status', ['active', 'paid', 'cancelled'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('employee_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vale_loans');
    }
};
