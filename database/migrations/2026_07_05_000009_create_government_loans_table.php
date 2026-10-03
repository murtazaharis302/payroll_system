<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('government_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees_master')->onDelete('cascade');
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->enum('loan_type', ['sss', 'pagibig']); // SSS Loan, Pag-IBIG Loan
            $table->string('loan_number')->nullable(); // Official loan reference number
            $table->decimal('principal_amount', 10, 2);
            $table->decimal('balance', 10, 2);
            $table->decimal('monthly_deduction', 10, 2);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('term_months')->nullable(); // Loan term in months
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
    }

    public function down(): void
    {
        Schema::dropIfExists('government_loans');
    }
};
