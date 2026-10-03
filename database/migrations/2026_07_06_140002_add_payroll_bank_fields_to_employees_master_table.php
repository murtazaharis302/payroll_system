<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees_master', function (Blueprint $table) {
            if (!Schema::hasColumn('employees_master', 'dept_id')) {
                $table->foreignId('dept_id')->nullable()->after('department')->constrained('departments')->nullOnDelete();
            }
            if (!Schema::hasColumn('employees_master', 'sub_dept_id')) {
                $table->foreignId('sub_dept_id')->nullable()->after('dept_id')->constrained('sub_departments')->nullOnDelete();
            }
            if (!Schema::hasColumn('employees_master', 'acct_num')) {
                $table->string('acct_num', 50)->nullable()->after('sub_dept_id');
            }
            if (!Schema::hasColumn('employees_master', 'atm_num')) {
                $table->string('atm_num', 50)->nullable()->after('acct_num');
            }
            if (!Schema::hasColumn('employees_master', 'tax_code')) {
                $table->string('tax_code', 20)->nullable()->after('tin_number');
            }
            if (!Schema::hasColumn('employees_master', 'remarks')) {
                $table->text('remarks')->nullable()->after('tax_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees_master', function (Blueprint $table) {
            foreach (['sub_dept_id', 'dept_id'] as $col) {
                if (Schema::hasColumn('employees_master', $col)) {
                    $table->dropForeign([$col]);
                    $table->dropColumn($col);
                }
            }
            foreach (['acct_num', 'atm_num', 'tax_code', 'remarks'] as $col) {
                if (Schema::hasColumn('employees_master', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
