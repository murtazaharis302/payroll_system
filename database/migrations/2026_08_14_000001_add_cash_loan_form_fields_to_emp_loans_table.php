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
        Schema::table('emp_loans', function (Blueprint $table) {
            if (!Schema::hasColumn('emp_loans', 'purpose')) {
                $table->string('purpose')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('emp_loans', 'terms_months')) {
                $table->integer('terms_months')->nullable()->default(1)->after('purpose');
            }
            if (!Schema::hasColumn('emp_loans', 'monthly_interest_rate')) {
                $table->decimal('monthly_interest_rate', 5, 2)->nullable()->default(0.00)->after('terms_months');
            }
            if (!Schema::hasColumn('emp_loans', 'interest_amount')) {
                $table->decimal('interest_amount', 12, 2)->nullable()->default(0.00)->after('monthly_interest_rate');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emp_loans', function (Blueprint $table) {
            if (Schema::hasColumn('emp_loans', 'purpose')) {
                $table->dropColumn(['purpose', 'terms_months', 'monthly_interest_rate', 'interest_amount']);
            }
        });
    }
};
