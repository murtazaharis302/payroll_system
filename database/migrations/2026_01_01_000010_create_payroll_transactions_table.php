<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees_master')->onDelete('cascade');
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            
            // Attendance & Work Days
            $table->decimal('total_days_worked', 5, 2)->default(0);
            $table->decimal('total_hours_worked', 8, 2)->default(0);
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->integer('absences')->default(0);
            $table->integer('lates')->default(0);
            
            // Income Breakdown
            $table->decimal('basic_pay', 12, 2)->default(0);
            $table->decimal('overtime_pay', 12, 2)->default(0);
            $table->decimal('allowances', 12, 2)->default(0);
            $table->decimal('bonuses', 12, 2)->default(0);
            $table->decimal('other_income', 12, 2)->default(0);
            $table->decimal('gross_income', 12, 2)->default(0);
            
            // Deduction Breakdown
            $table->decimal('sss_contribution', 12, 2)->default(0);
            $table->decimal('philhealth_contribution', 12, 2)->default(0);
            $table->decimal('pagibig_contribution', 12, 2)->default(0);
            $table->decimal('withholding_tax', 12, 2)->default(0);
            $table->decimal('sss_loan', 12, 2)->default(0);
            $table->decimal('pagibig_loan', 12, 2)->default(0);
            $table->decimal('vale_deduction', 12, 2)->default(0);
            $table->decimal('goods_deduction', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            
            // Net Pay
            $table->decimal('net_pay', 12, 2)->default(0);
            
            // Status & Audit
            $table->enum('status', ['draft', 'approved', 'paid', 'cancelled'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            $table->index('payroll_period_id');
            $table->index('employee_id');
            $table->index('company_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_transactions');
    }
};
