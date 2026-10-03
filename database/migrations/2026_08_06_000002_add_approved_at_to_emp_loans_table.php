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
        if (!Schema::hasTable('emp_loans') || Schema::hasColumn('emp_loans', 'approved_at')) {
            return;
        }

        Schema::table('emp_loans', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('current_balance');
            $table->index(['employee_id', 'approved_at'], 'emp_loans_employee_approved_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('emp_loans') || !Schema::hasColumn('emp_loans', 'approved_at')) {
            return;
        }

        Schema::table('emp_loans', function (Blueprint $table) {
            $table->dropIndex('emp_loans_employee_approved_at_index');
            $table->dropColumn('approved_at');
        });
    }
};
