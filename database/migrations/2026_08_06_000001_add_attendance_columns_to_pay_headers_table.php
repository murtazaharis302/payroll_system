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
        if (Schema::hasTable('pay_headers')) {
            Schema::table('pay_headers', function (Blueprint $table) {
                if (!Schema::hasColumn('pay_headers', 'days_worked')) {
                    $table->decimal('days_worked', 5, 2)->default(0.00)->after('employee_id');
                }
                if (!Schema::hasColumn('pay_headers', 'days_absent')) {
                    $table->decimal('days_absent', 5, 2)->default(0.00)->after('days_worked');
                }
                if (!Schema::hasColumn('pay_headers', 'overtime_hours')) {
                    $table->decimal('overtime_hours', 5, 2)->default(0.00)->after('days_absent');
                }
                if (!Schema::hasColumn('pay_headers', 'late_hours')) {
                    $table->decimal('late_hours', 5, 2)->default(0.00)->after('overtime_hours');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pay_headers')) {
            Schema::table('pay_headers', function (Blueprint $table) {
                $columns = ['days_worked', 'days_absent', 'overtime_hours', 'late_hours'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('pay_headers', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
