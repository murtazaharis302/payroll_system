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
        Schema::table('payroll_drafts', function (Blueprint $table) {
            $table->text('custom_deductions')->nullable()->after('other_deductions');
        });

        Schema::table('payroll_transactions', function (Blueprint $table) {
            $table->text('custom_deductions')->nullable()->after('other_deductions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_drafts', function (Blueprint $table) {
            $table->dropColumn('custom_deductions');
        });

        Schema::table('payroll_transactions', function (Blueprint $table) {
            $table->dropColumn('custom_deductions');
        });
    }
};
