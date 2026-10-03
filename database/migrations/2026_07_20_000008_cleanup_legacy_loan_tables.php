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
        Schema::dropIfExists('vale_loan_payments');
        Schema::dropIfExists('vale_loans');
        Schema::dropIfExists('goods_purchase_payments');
        Schema::dropIfExists('goods_purchases');
        Schema::dropIfExists('government_loan_payments');
        Schema::dropIfExists('government_loans');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('vale_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees_master')->onDelete('cascade');
            $table->foreignId('deduction_type_id')->constrained('deduction_types')->onDelete('cascade');
            $table->string('loan_number')->unique();
            $table->decimal('principal_amount', 10, 2);
            $table->decimal('total_paid', 10, 2)->default(0);
            $table->decimal('balance', 10, 2);
            $table->decimal('installment_amount', 10, 2);
            $table->integer('installment_count')->default(0);
            $table->date('loan_date');
            $table->date('start_deduction_date')->nullable();
            $table->enum('status', ['active', 'paid', 'cancelled'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('employee_id');
            $table->index('status');
            $table->index('company_id');
        });

        Schema::create('vale_loan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vale_loan_id')->constrained('vale_loans')->onDelete('cascade');
            $table->foreignId('payroll_period_id')->nullable()->constrained('payroll_periods')->onDelete('set null');
            $table->decimal('amount_paid', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('vale_loan_id');
            $table->index('payroll_period_id');
            $table->index('payment_date');
        });

        Schema::create('goods_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('employees_master')->onDelete('cascade');
            $table->foreignId('deduction_type_id')->constrained('deduction_types')->onDelete('cascade');
            $table->string('purchase_number')->unique();
            $table->string('item_description');
            $table->decimal('total_amount', 10, 2);
            $table->decimal('total_paid', 10, 2)->default(0);
            $table->decimal('balance', 10, 2);
            $table->decimal('installment_amount', 10, 2);
            $table->integer('installment_count')->default(0);
            $table->date('purchase_date');
            $table->date('start_deduction_date')->nullable();
            $table->enum('status', ['active', 'paid', 'cancelled'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('employee_id');
            $table->index('status');
            $table->index('company_id');
        });

        Schema::create('goods_purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_purchase_id')->constrained('goods_purchases')->onDelete('cascade');
            $table->foreignId('payroll_period_id')->nullable()->constrained('payroll_periods')->onDelete('set null');
            $table->decimal('amount_paid', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('goods_purchase_id');
            $table->index('payroll_period_id');
            $table->index('payment_date');
        });

        Schema::create('government_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees_master')->onDelete('cascade');
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->enum('loan_type', ['sss', 'pagibig']);
            $table->string('loan_number')->nullable();
            $table->decimal('principal_amount', 10, 2);
            $table->decimal('balance', 10, 2);
            $table->decimal('monthly_deduction', 10, 2);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('term_months')->nullable();
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            
            $table->index('employee_id');
            $table->index('company_id');
            $table->index('loan_type');
            $table->index('status');
        });

        Schema::create('government_loan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('government_loan_id')->constrained('government_loans')->onDelete('cascade');
            $table->foreignId('payroll_period_id')->nullable()->constrained('payroll_periods')->onDelete('set null');
            $table->decimal('amount_paid', 10, 2);
            $table->decimal('balance_after', 10, 2);
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('government_loan_id');
            $table->index('payroll_period_id');
            $table->index('payment_date');
        });
    }
};
