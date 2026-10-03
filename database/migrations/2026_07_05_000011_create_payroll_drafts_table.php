<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees_master')->onDelete('cascade');
            
            // Attendance Data (HR Input)
            $table->decimal('days_worked', 5, 2)->default(0);
            $table->decimal('days_absent', 5, 2)->default(0);
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->decimal('late_hours', 5, 2)->default(0);
            $table->decimal('undertime_hours', 5, 2)->default(0);
            
            // Income Computations
            $table->decimal('basic_pay', 10, 2)->default(0);
            $table->decimal('overtime_pay', 10, 2)->default(0);
            $table->decimal('total_allowances', 10, 2)->default(0);
            $table->decimal('total_bonuses', 10, 2)->default(0);
            $table->decimal('other_income', 10, 2)->default(0);
            $table->decimal('gross_pay', 10, 2)->default(0);
            
            // Deduction Computations
            $table->decimal('sss_contribution', 10, 2)->default(0);
            $table->decimal('philhealth_contribution', 10, 2)->default(0);
            $table->decimal('pagibig_contribution', 10, 2)->default(0);
            $table->decimal('withholding_tax', 10, 2)->default(0);
            $table->decimal('sss_loan_deduction', 10, 2)->default(0);
            $table->decimal('pagibig_loan_deduction', 10, 2)->default(0);
            $table->decimal('vale_loan_deduction', 10, 2)->default(0);
            $table->decimal('goods_purchase_deduction', 10, 2)->default(0);
            $table->decimal('late_deduction', 10, 2)->default(0);
            $table->decimal('undertime_deduction', 10, 2)->default(0);
            $table->decimal('other_deductions', 10, 2)->default(0);
            $table->decimal('total_deductions', 10, 2)->default(0);
            
            // Net Pay
            $table->decimal('net_pay', 10, 2)->default(0);
            
            // Workflow Status
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'rejected'])->default('draft');
            $table->foreignId('prepared_by')->nullable()->constrained('users')->onDelete('set null'); // HR who prepared
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null'); // Accounting who approved
            $table->timestamp('submitted_at')->nullable(); // When HR submitted
            $table->timestamp('approved_at')->nullable(); // When Accounting approved
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            $table->index('company_id');
            $table->index('payroll_period_id');
            $table->index('employee_id');
            $table->index('status');
            $table->unique(['payroll_period_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_drafts');
    }
};
