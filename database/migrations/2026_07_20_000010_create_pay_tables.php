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
        Schema::create('pay_headers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees_master')->onDelete('cascade');
            $table->decimal('days_worked', 5, 2)->default(0.00);
            $table->decimal('days_absent', 5, 2)->default(0.00);
            $table->decimal('overtime_hours', 5, 2)->default(0.00);
            $table->decimal('late_hours', 5, 2)->default(0.00);
            $table->enum('status', ['draft', 'approved', 'posted', 'paid', 'cancelled'])->default('draft');
            $table->decimal('total_gross_pay', 12, 2)->default(0.00);
            $table->decimal('total_deductions', 12, 2)->default(0.00);
            $table->decimal('net_pay', 12, 2)->default(0.00);
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('pay_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pay_id')->constrained('pay_headers')->onDelete('cascade');
            $table->foreignId('income_type_id')->constrained('income_types')->onDelete('cascade');
            $table->decimal('number_input', 8, 2)->default(1.00);
            $table->decimal('rate', 12, 2)->default(0.00);
        });

        Schema::create('pay_outs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pay_id')->constrained('pay_headers')->onDelete('cascade');
            $table->foreignId('deduction_type_id')->constrained('deduction_types')->onDelete('cascade');
            $table->foreignId('loan_id')->nullable()->constrained('emp_loans')->onDelete('set null');
            $table->decimal('number_input', 8, 2)->default(1.00);
            $table->decimal('rate', 12, 2)->default(0.00);
        });

        Schema::table('loan_payments', function (Blueprint $table) {
            $table->foreign('pay_id')->references('id')->on('pay_headers')->onDelete('set null');
        });

        Schema::table('government_contributions', function (Blueprint $table) {
            $table->dropForeign(['payroll_draft_id']);
            $table->foreign('payroll_draft_id')
                ->references('id')
                ->on('pay_headers')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('government_contributions', function (Blueprint $table) {
            $table->dropForeign(['payroll_draft_id']);
            $table->foreign('payroll_draft_id')
                ->references('id')
                ->on('payroll_drafts')
                ->onDelete('set null');
        });

        Schema::table('loan_payments', function (Blueprint $table) {
            $table->dropForeign(['pay_id']);
        });

        Schema::dropIfExists('pay_outs');
        Schema::dropIfExists('pay_ins');
        Schema::dropIfExists('pay_headers');
    }
};
