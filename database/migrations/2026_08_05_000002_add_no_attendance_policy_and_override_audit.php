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
        if (Schema::hasTable('payroll_policies')) {
            Schema::table('payroll_policies', function (Blueprint $table) {
                if (!Schema::hasColumn('payroll_policies', 'no_attendance_policy')) {
                    $table->string('no_attendance_policy')->default('zero_pay_requires_review')->after('rounding_mode');
                }
            });
        }

        if (Schema::hasTable('pay_headers')) {
            Schema::table('pay_headers', function (Blueprint $table) {
                if (!Schema::hasColumn('pay_headers', 'requires_review')) {
                    $table->boolean('requires_review')->default(false)->after('status');
                }
                if (!Schema::hasColumn('pay_headers', 'review_reason')) {
                    $table->string('review_reason')->nullable()->after('requires_review');
                }
                if (!Schema::hasColumn('pay_headers', 'attendance_override_reason')) {
                    $table->string('attendance_override_reason')->nullable()->after('review_reason');
                }
                if (!Schema::hasColumn('pay_headers', 'attendance_override_by')) {
                    $table->foreignId('attendance_override_by')->nullable()->after('attendance_override_reason')->constrained('users')->onDelete('set null');
                }
                if (!Schema::hasColumn('pay_headers', 'attendance_override_at')) {
                    $table->timestamp('attendance_override_at')->nullable()->after('attendance_override_by');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('payroll_policies')) {
            Schema::table('payroll_policies', function (Blueprint $table) {
                if (Schema::hasColumn('payroll_policies', 'no_attendance_policy')) {
                    $table->dropColumn('no_attendance_policy');
                }
            });
        }

        if (Schema::hasTable('pay_headers')) {
            Schema::table('pay_headers', function (Blueprint $table) {
                $columns = ['requires_review', 'review_reason', 'attendance_override_reason', 'attendance_override_by', 'attendance_override_at'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('pay_headers', $column)) {
                        if ($column === 'attendance_override_by') {
                            $table->dropForeign(['attendance_override_by']);
                        }
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
