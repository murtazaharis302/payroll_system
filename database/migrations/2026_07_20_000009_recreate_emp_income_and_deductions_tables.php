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
        Schema::dropIfExists('emp_income');
        Schema::dropIfExists('emp_deductions');

        Schema::create('emp_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees_master')->onDelete('cascade');
            $table->foreignId('deduction_type_id')->constrained('deduction_types')->onDelete('cascade');
            $table->decimal('rate', 10, 2)->default(0.00);
            $table->enum('frequency', ['monthly', 'per_payroll', 'one_time'])->default('per_payroll');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('effective_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['employee_id', 'deduction_type_id']);
        });

        Schema::create('emp_income', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees_master')->onDelete('cascade');
            $table->foreignId('income_type_id')->constrained('income_types')->onDelete('cascade');
            $table->decimal('rate', 10, 2)->default(0.00);
            $table->enum('frequency', ['monthly', 'daily', 'per_occurrence'])->default('monthly');
            $table->date('effective_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['employee_id', 'income_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emp_income');
        Schema::dropIfExists('emp_deductions');

        Schema::create('emp_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees_master')->onDelete('cascade');
            $table->foreignId('deduction_type_id')->constrained('deduction_types')->onDelete('cascade');
            $table->decimal('amount', 10, 2)->default(0);
            $table->enum('frequency', ['monthly', 'per_payroll', 'one_time'])->default('per_payroll');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('effective_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['employee_id', 'deduction_type_id']);
        });

        Schema::create('emp_income', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees_master')->onDelete('cascade');
            $table->foreignId('income_type_id')->constrained('income_types')->onDelete('cascade');
            $table->decimal('amount', 10, 2)->default(0);
            $table->enum('frequency', ['monthly', 'daily', 'per_occurrence'])->default('monthly');
            $table->date('effective_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['employee_id', 'income_type_id']);
        });
    }
};
