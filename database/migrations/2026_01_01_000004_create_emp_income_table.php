<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emp_income', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees_master')->onDelete('cascade');
            $table->foreignId('income_type_id')->constrained('income_types')->onDelete('cascade');
            $table->decimal('amount', 10, 2)->default(0); // Fixed amount (like basic salary)
            $table->enum('frequency', ['monthly', 'daily', 'per_occurrence'])->default('monthly');
            $table->date('effective_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['employee_id', 'income_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emp_income');
    }
};
