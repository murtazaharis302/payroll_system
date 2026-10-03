<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees_master', function (Blueprint $table) {
            if (!Schema::hasColumn('employees_master', 'monthly_rate')) {
                $table->decimal('monthly_rate', 12, 2)->nullable()->after('department');
            }
            if (!Schema::hasColumn('employees_master', 'daily_rate')) {
                $table->decimal('daily_rate', 12, 2)->nullable()->after('monthly_rate');
            }
            if (!Schema::hasColumn('employees_master', 'hourly_rate')) {
                $table->decimal('hourly_rate', 12, 2)->nullable()->after('daily_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees_master', function (Blueprint $table) {
            $columns = ['monthly_rate', 'daily_rate', 'hourly_rate'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('employees_master', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
