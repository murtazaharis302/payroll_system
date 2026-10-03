<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees_master')->onDelete('cascade');
            $table->foreignId('deduction_type_id')->constrained('deduction_types')->onDelete('cascade'); // GOODS type
            $table->string('purchase_number')->unique(); // GOODS-2026-001
            $table->string('item_description'); // Description of purchased goods
            $table->decimal('total_amount', 10, 2); // Total purchase amount
            $table->decimal('total_paid', 10, 2)->default(0); // Amount paid
            $table->decimal('balance', 10, 2); // Remaining balance
            $table->decimal('installment_amount', 10, 2); // Per payroll deduction
            $table->integer('installment_count')->default(0);
            $table->date('purchase_date');
            $table->date('start_deduction_date')->nullable();
            $table->enum('status', ['active', 'paid', 'cancelled'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->index('employee_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_purchases');
    }
};
