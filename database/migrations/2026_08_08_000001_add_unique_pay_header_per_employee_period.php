<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicatePairs = DB::table('pay_headers')
            ->select([
                'payroll_period_id',
                'employee_id',
                DB::raw('COUNT(*) as header_count'),
            ])
            ->groupBy('payroll_period_id', 'employee_id')
            ->havingRaw('COUNT(*) > 1');

        $duplicatePairCount = DB::query()
            ->fromSub($duplicatePairs, 'duplicate_pay_headers')
            ->count();

        if ($duplicatePairCount > 0) {
            $sample = (clone $duplicatePairs)
                ->orderBy('payroll_period_id')
                ->orderBy('employee_id')
                ->limit(10)
                ->get()
                ->map(fn ($pair) => "period {$pair->payroll_period_id} / employee {$pair->employee_id} ({$pair->header_count} headers)")
                ->implode(', ');

            throw new \RuntimeException(
                "Cannot add pay_headers_period_employee_unique: found {$duplicatePairCount} duplicate payroll-period/employee pair(s). "
                . "Resolve the duplicate PayHeaders deliberately, then re-run this migration. Sample: {$sample}"
            );
        }

        Schema::table('pay_headers', function (Blueprint $table) {
            $table->unique(['payroll_period_id', 'employee_id'], 'pay_headers_period_employee_unique');
        });
    }

    public function down(): void
    {
        Schema::table('pay_headers', function (Blueprint $table) {
            $table->dropUnique('pay_headers_period_employee_unique');
        });
    }
};
