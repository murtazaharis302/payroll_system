<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('vale_loan_payments');
    }
};
