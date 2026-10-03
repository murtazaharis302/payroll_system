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
        Schema::table('loan_payments', function (Blueprint $table) {
            $table->unique(['pay_id', 'emp_loan_id'], 'loan_payments_pay_id_emp_loan_id_unique');
        });

        Schema::table('pay_outs', function (Blueprint $table) {
            $table->unique(['pay_id', 'loan_id'], 'pay_outs_pay_id_loan_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pay_outs', function (Blueprint $table) {
            $table->dropUnique('pay_outs_pay_id_loan_id_unique');
        });

        Schema::table('loan_payments', function (Blueprint $table) {
            $table->dropUnique('loan_payments_pay_id_emp_loan_id_unique');
        });
    }
};
