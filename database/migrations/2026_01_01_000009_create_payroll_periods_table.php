<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('period_name'); // "January 2026 - 1st Cutoff"
            $table->date('period_start');
            $table->date('period_end');
            $table->date('pay_date')->nullable(); // When employees get paid
            $table->enum('status', ['open', 'processing', 'completed', 'cancelled'])->default('open');
            $table->foreignId('processed_by')->nullable()->constrained('users'); // Who generated payroll
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index('company_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
