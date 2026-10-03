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
        Schema::table('loan_types', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_types', 'default_amortization_per_cutoff')) {
                $table->decimal('default_amortization_per_cutoff', 12, 2)->default(0.00)->after('name');
            }
            if (!Schema::hasColumn('loan_types', 'default_installments_count')) {
                $table->integer('default_installments_count')->nullable()->after('default_amortization_per_cutoff');
            }
            if (!Schema::hasColumn('loan_types', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('default_installments_count');
            }
            if (!Schema::hasColumn('loan_types', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete()->after('is_active');
            }
            if (!Schema::hasColumn('loan_types', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete()->after('created_by');
            }
        });

        Schema::table('emp_loans', function (Blueprint $table) {
            if (!Schema::hasColumn('emp_loans', 'total_amount')) {
                $table->decimal('total_amount', 12, 2)->nullable()->after('loan_type_id');
            }
            if (!Schema::hasColumn('emp_loans', 'per_cutoff_amount')) {
                $table->decimal('per_cutoff_amount', 12, 2)->nullable()->default(0.00)->after('total_amount');
            }
            if (!Schema::hasColumn('emp_loans', 'amount_paid')) {
                $table->decimal('amount_paid', 12, 2)->default(0.00)->after('per_cutoff_amount');
            }
            if (!Schema::hasColumn('emp_loans', 'status')) {
                $table->string('status', 20)->default('ACTIVE')->after('current_balance');
            }
            if (!Schema::hasColumn('emp_loans', 'start_date')) {
                $table->date('start_date')->nullable()->after('status');
            }
            if (!Schema::hasColumn('emp_loans', 'notes')) {
                $table->text('notes')->nullable()->after('start_date');
            }
        });

        Schema::table('loan_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_payments', 'pay_header_id')) {
                $table->foreignId('pay_header_id')->nullable()->constrained('pay_headers')->nullOnDelete()->after('emp_loan_id');
            }
            if (!Schema::hasColumn('loan_payments', 'notes')) {
                $table->string('notes')->nullable()->after('amount_paid');
            }
            if (!Schema::hasColumn('loan_payments', 'source')) {
                $table->string('source', 50)->default('PAYROLL')->after('notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_payments', function (Blueprint $table) {
            if (Schema::hasColumn('loan_payments', 'pay_header_id')) {
                $table->dropForeign(['pay_header_id']);
                $table->dropColumn(['pay_header_id', 'notes', 'source']);
            }
        });

        Schema::table('emp_loans', function (Blueprint $table) {
            if (Schema::hasColumn('emp_loans', 'total_amount')) {
                $table->dropColumn(['total_amount', 'per_cutoff_amount', 'amount_paid', 'status', 'start_date', 'notes']);
            }
        });

        Schema::table('loan_types', function (Blueprint $table) {
            if (Schema::hasColumn('loan_types', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropForeign(['updated_by']);
                $table->dropColumn(['default_amortization_per_cutoff', 'default_installments_count', 'is_active', 'created_by', 'updated_by']);
            }
        });
    }
};
