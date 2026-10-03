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
        if (!Schema::hasTable('payroll_policies')) {
            Schema::create('payroll_policies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('cascade');
                $table->string('policy_name')->default('Default Company Policy');
                $table->string('pay_frequency')->default('semi_monthly');
                $table->decimal('workdays_per_month', 8, 2)->default(26.00);
                $table->decimal('hours_per_day', 8, 2)->default(8.00);
                $table->decimal('regular_ot_multiplier', 8, 4)->default(1.2500);
                $table->decimal('rest_day_ot_multiplier', 8, 4)->default(1.3000);
                $table->decimal('holiday_ot_multiplier', 8, 4)->default(2.0000);
                $table->string('rounding_mode')->default('round_half_up');
                $table->string('no_attendance_policy')->default('zero_pay_requires_review');
                $table->date('effective_date')->default('2020-01-01');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['company_id', 'effective_date', 'is_active']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payroll_policies');
    }
};
