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
        Schema::table('payroll_transactions', function (Blueprint $table) {
            $table->decimal('late_deduction', 12, 2)->default(0)->after('goods_deduction');
            $table->decimal('undertime_deduction', 12, 2)->default(0)->after('late_deduction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_transactions', function (Blueprint $table) {
            $table->dropColumn(['late_deduction', 'undertime_deduction']);
        });
    }
};
