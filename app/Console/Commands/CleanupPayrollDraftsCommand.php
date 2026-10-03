<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\GovernmentContribution;
use App\Models\PayHeader;
use App\Models\PayIn;
use App\Models\PayOut;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupPayrollDraftsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:cleanup-drafts
                            {--company= : The ID of the target company}
                            {--period= : The ID of the target payroll period}
                            {--dry-run : Inspect and output matching draft counts without deleting}
                            {--confirm-token= : Token required to execute deletion (CLEANUP-DRAFTS-CONFIRM)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely cleanup draft payroll records for a specific company and period in non-production environments.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // 1. Refuse execution in production environment
        if (app()->environment('production') || config('app.env') === 'production') {
            $this->error('Draft cleanup command is strictly disabled in production environments.');
            return 1;
        }

        // 2. Validate required options
        $companyId = $this->option('company');
        $periodId = $this->option('period');

        if (!$companyId || !$periodId) {
            $this->error('Both --company and --period options are required.');
            return 1;
        }

        // 3. Query matching DRAFT records only
        $draftHeadersQuery = PayHeader::withoutGlobalScopes()
            ->where('payroll_period_id', $periodId)
            ->where('status', 'draft')
            ->whereHas('employee', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            });

        $draftIds = $draftHeadersQuery->pluck('id');

        $payInCount = PayIn::whereIn('pay_id', $draftIds)->count();
        $payOutCount = PayOut::whereIn('pay_id', $draftIds)->count();

        // 4. Handle Dry-Run Mode
        if ($this->option('dry-run')) {
            $this->info("DRY-RUN INSPECTION RESULT:");
            $this->line("- Target Company ID: {$companyId}");
            $this->line("- Target Period ID: {$periodId}");
            $this->line("- Draft PayHeader count: {$draftIds->count()}");
            $this->line("- Draft PayIn count: {$payInCount}");
            $this->line("- Draft PayOut count: {$payOutCount}");

            ActivityLog::log(
                'dry_run_cleanup',
                'payroll_cleanup',
                "Dry run draft cleanup for Company #{$companyId}, Period #{$periodId}: {$draftIds->count()} drafts found."
            );

            return 0;
        }

        // 5. Require Confirmation Token for Deletion
        $confirmToken = $this->option('confirm-token');
        if ($confirmToken !== 'CLEANUP-DRAFTS-CONFIRM') {
            $this->error("Confirmation token '--confirm-token=CLEANUP-DRAFTS-CONFIRM' is required to execute deletion.");
            return 1;
        }

        if ($draftIds->isEmpty()) {
            $this->info("No draft records found for Company #{$companyId} in Period #{$periodId}. No action taken.");
            return 0;
        }

        // 6. Perform Transactional Deletion
        $deletedCount = 0;
        DB::transaction(function () use ($draftIds, $companyId, $periodId, &$deletedCount) {
            PayIn::whereIn('pay_id', $draftIds)->delete();
            PayOut::whereIn('pay_id', $draftIds)->delete();
            PayHeader::whereIn('id', $draftIds)->delete();
            $deletedCount = $draftIds->count();
        });

        // 7. Audit Log Execution Result
        ActivityLog::log(
            'cleanup_drafts',
            'payroll_cleanup',
            "Deleted {$deletedCount} draft headers, {$payInCount} pay_ins, {$payOutCount} pay_outs for Company #{$companyId}, Period #{$periodId} via CLI command."
        );

        $this->info("Successfully deleted {$deletedCount} draft payroll records for Company #{$companyId}, Period #{$periodId}.");

        return 0;
    }
}
