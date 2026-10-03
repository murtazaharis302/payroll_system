<?php

namespace App\Console\Commands;

use App\Models\LoanType;
use Illuminate\Console\Command;

class ReportUnmappedLoanTypes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:report-unmapped-loan-types';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan and report legacy unmapped Loan Types that require explicit Deduction Type mapping.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $unmappedQuery = LoanType::withoutGlobalScopes()
            ->with('company')
            ->whereNull('deduction_type_id');

        $unmappedTypes = $unmappedQuery->get();

        if ($unmappedTypes->isEmpty()) {
            $this->info('All Loan Types are properly mapped to a Deduction Type. Zero unmapped legacy loan types found.');
            return self::SUCCESS;
        }

        $this->warn("Found {$unmappedTypes->count()} unmapped legacy Loan Type record(s):");
        $this->newLine();

        $tableData = [];
        foreach ($unmappedTypes as $lt) {
            $companyName = $lt->company ? "{$lt->company->name} (ID: {$lt->company_id})" : 'Global Catalog';
            $tableData[] = [
                'Loan Type ID' => $lt->id,
                'Loan Type Name' => $lt->name,
                'Company' => $companyName,
                'Status' => $lt->is_active ? 'Active' : 'Inactive',
            ];
        }

        $this->table(['Loan Type ID', 'Loan Type Name', 'Company', 'Status'], $tableData);

        $this->newLine();
        $this->error('CRITICAL: Unmapped Loan Types will cause payroll generation to fail with configuration errors until mapped in Loan Setup.');

        return self::FAILURE;
    }
}
