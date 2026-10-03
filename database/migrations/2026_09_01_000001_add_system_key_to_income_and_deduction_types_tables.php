<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('income_types')) {
            Schema::table('income_types', function (Blueprint $table) {
                if (!Schema::hasColumn('income_types', 'system_key')) {
                    $table->string('system_key', 50)->nullable()->after('code');
                }
            });

            // Add unique index on company_id + system_key if not already present
            Schema::table('income_types', function (Blueprint $table) {
                $table->unique(['company_id', 'system_key'], 'income_types_company_system_key_unique');
            });

            // Backfill existing standard income types
            DB::table('income_types')
                ->where('code', 'BASIC')
                ->whereNull('system_key')
                ->update(['system_key' => 'basic']);

            DB::table('income_types')
                ->where('code', 'OVERTIME')
                ->whereNull('system_key')
                ->update(['system_key' => 'overtime']);
        }

        if (Schema::hasTable('deduction_types')) {
            Schema::table('deduction_types', function (Blueprint $table) {
                if (!Schema::hasColumn('deduction_types', 'system_key')) {
                    $table->string('system_key', 50)->nullable()->after('code');
                }
            });

            // Add unique index on company_id + system_key if not already present
            Schema::table('deduction_types', function (Blueprint $table) {
                $table->unique(['company_id', 'system_key'], 'deduction_types_company_system_key_unique');
            });

            // Backfill existing standard deduction types
            $statutoryMappings = [
                'SSS'        => 'sss',
                'PHILHEALTH' => 'philhealth',
                'PAGIBIG'    => 'pagibig',
                'TAX'        => 'tax',
                'TARDINESS'  => 'tardiness',
                'UNDERTIME'  => 'undertime',
            ];

            foreach ($statutoryMappings as $code => $key) {
                DB::table('deduction_types')
                    ->where('code', $code)
                    ->whereNull('system_key')
                    ->update(['system_key' => $key]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('income_types') && Schema::hasColumn('income_types', 'system_key')) {
            Schema::table('income_types', function (Blueprint $table) {
                $table->dropUnique('income_types_company_system_key_unique');
                $table->dropColumn('system_key');
            });
        }

        if (Schema::hasTable('deduction_types') && Schema::hasColumn('deduction_types', 'system_key')) {
            Schema::table('deduction_types', function (Blueprint $table) {
                $table->dropUnique('deduction_types_company_system_key_unique');
                $table->dropColumn('system_key');
            });
        }
    }
};
