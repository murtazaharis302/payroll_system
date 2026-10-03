<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emp_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees_master')->onDelete('cascade');
            $table->foreignId('deduction_type_id')->constrained('deduction_types')->onDelete('cascade');
            $table->decimal('amount', 10, 2)->default(0); // Fixed deduction amount
            $table->enum('frequency', ['monthly', 'per_payroll', 'one_time'])->default('per_payroll');
            $table->date('effective_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['employee_id', 'deduction_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emp_deductions');
    }
};
