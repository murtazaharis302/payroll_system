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
        Schema::create('sss_contributions', function (Blueprint $table) {
            $table->id();
            $table->decimal('range_from', 10, 2); // Minimum compensation range
            $table->decimal('range_to', 10, 2)->nullable(); // Maximum compensation range (null for unlimited)
            $table->decimal('monthly_salary_credit', 10, 2); // MSC (Monthly Salary Credit)
            $table->decimal('employee_contribution', 10, 2); // Employee's share
            $table->decimal('employer_contribution', 10, 2); // Employer's share
            $table->decimal('total_contribution', 10, 2); // Total contribution
            $table->decimal('employee_provident_fund', 10, 2)->default(0); // Employee Provident Fund
            $table->decimal('employer_ec', 10, 2)->default(0); // Employer EC (Employees Compensation)
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable(); // When this rate becomes effective
            $table->date('effective_to')->nullable(); // When this rate expires
            $table->timestamps();
            
            // Indexes for faster lookups
            $table->index(['range_from', 'range_to', 'is_active']);
            $table->index('effective_from');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sss_contributions');
    }
};
