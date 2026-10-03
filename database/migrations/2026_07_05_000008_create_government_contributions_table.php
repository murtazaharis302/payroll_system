<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('government_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees_master')->onDelete('cascade');
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->foreignId('payroll_period_id')->nullable()->constrained('payroll_periods')->onDelete('set null');
            $table->enum('type', ['sss', 'philhealth', 'pagibig']); // SSS, PhilHealth, Pag-IBIG
            $table->decimal('employee_share', 10, 2)->default(0);
            $table->decimal('employer_share', 10, 2)->default(0);
            $table->decimal('total_contribution', 10, 2)->default(0);
            $table->date('period_start');
            $table->date('period_end');
            $table->date('payment_date')->nullable();
            $table->enum('status', ['pending', 'paid', 'submitted'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('employee_id');
            $table->index('company_id');
            $table->index('type');
            $table->index('period_start');
            $table->index(['employee_id', 'type', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('government_contributions');
    }
};
