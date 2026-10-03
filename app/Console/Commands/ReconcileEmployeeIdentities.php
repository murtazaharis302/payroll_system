<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeMaster;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ReconcileEmployeeIdentities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payroll:reconcile-employee-identities
                            {--company= : Filter reconciliation by Company ID or Code}
                            {--dry-run : Report proposed reconciliation actions without writing to the database}
                            {--apply : Apply proposed reconciliation changes to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely and idempotently reconcile unlinked User and EmployeeMaster identities without creating default credentials.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isApply = (bool)$this->option('apply');
        $isDryRun = !$isApply;
        $companyOption = $this->option('company');

        $companyId = null;
        if ($companyOption) {
            $company = Company::where('id', $companyOption)
                ->orWhere('company_code', strtoupper(trim($companyOption)))
                ->first();

            if (!$company) {
                $this->error("Company '{$companyOption}' not found.");
                return self::FAILURE;
            }
            $companyId = $company->id;
        }

        $modeLabel = $isDryRun ? 'DRY-RUN (No Database Writes)' : 'APPLY (Writing Changes)';
        $this->info("Starting Employee Identity Reconciliation - Mode: {$modeLabel}");
        if ($companyId) {
            $this->info("Scoped to Company ID: {$companyId}");
        }

        $proposedUserLinks = 0;
        $proposedMasterLinks = 0;
        $ambiguousEmailsSkipped = 0;
        $unresolvableOwnersSkipped = 0;
        $appliedWrites = 0;

        // Task 1: Reconcile unlinked User records (roles HR or Employee missing an Employee row)
        $userQuery = User::withoutGlobalScopes()
            ->where(function ($q) {
                $q->whereIn('role_id', [3, 4]);
                if (Schema::hasColumn('users', 'role')) {
                    $q->orWhereIn('role', ['hr', 'employee']);
                }
            })
            ->whereDoesntHave('employees');

        if ($companyId) {
            $userQuery->where('company_id', $companyId);
        }

        $unlinkedUsers = $userQuery->get();

        foreach ($unlinkedUsers as $unlinkedUser) {
            $ownerUserId = $unlinkedUser->company?->user_id;

            if (!$ownerUserId) {
                $this->warn("Skipping User ID {$unlinkedUser->id} ({$unlinkedUser->email}): Company owner user ID could not be resolved.");
                Log::warning("Reconciliation skipped User ID {$unlinkedUser->id}: Unresolvable company owner.");
                $unresolvableOwnersSkipped++;
                continue;
            }

            $proposedUserLinks++;
            $this->line("Unlinked User found: ID {$unlinkedUser->id} ({$unlinkedUser->email})");

            if ($isApply) {
                DB::transaction(function () use ($unlinkedUser, $ownerUserId, &$appliedWrites) {
                    Employee::create([
                        'owner_user_id' => $ownerUserId,
                        'e_user_id'     => $unlinkedUser->id,
                        'designation'   => ucfirst($unlinkedUser->role ?? 'Employee'),
                        'id_number'     => Employee::generateIdNumber(),
                        'address'       => 'N/A',
                        'status'        => 1,
                    ]);

                    $appliedWrites++;
                    Log::info("Reconciliation applied: Linked User ID {$unlinkedUser->id} to new Employee row.");
                });
            }
        }

        // Task 2: Reconcile unlinked EmployeeMaster records
        $masterQuery = EmployeeMaster::withoutGlobalScopes()
            ->whereNotNull('email')
            ->where('email', '!=', '');

        if ($companyId) {
            $masterQuery->where('company_id', $companyId);
        }

        $unlinkedMasters = $masterQuery->get();

        // Check for ambiguous emails
        $emailCounts = $unlinkedMasters->groupBy('email')->map->count();

        foreach ($unlinkedMasters as $master) {
            if ($emailCounts->get($master->email, 0) > 1) {
                $this->warn("Skipping ambiguous email '{$master->email}' (associated with multiple EmployeeMaster records).");
                $ambiguousEmailsSkipped++;
                continue;
            }

            $existingUsers = User::withoutGlobalScopes()->where('email', $master->email)->get();
            if ($existingUsers->count() > 1) {
                $this->warn("Skipping ambiguous email '{$master->email}' (associated with multiple User records).");
                $ambiguousEmailsSkipped++;
                continue;
            }

            $ownerUserId = $master->company?->user_id;
            if (!$ownerUserId) {
                $this->warn("Skipping EmployeeMaster {$master->employee_code} ({$master->email}): Company owner user ID could not be resolved.");
                Log::warning("Reconciliation skipped EmployeeMaster {$master->employee_code}: Unresolvable company owner.");
                $unresolvableOwnersSkipped++;
                continue;
            }

            $existingUser = $existingUsers->first();

            // Check if already linked to Employee
            if ($existingUser && Employee::where('e_user_id', $existingUser->id)->exists()) {
                continue;
            }

            $proposedMasterLinks++;
            $this->line("Unlinked Master found: Code {$master->employee_code} ({$master->email})");

            if ($isApply) {
                DB::transaction(function () use ($master, $existingUser, $ownerUserId, &$appliedWrites) {
                    $targetUser = $existingUser;

                    if (!$targetUser) {
                        // Create User with a random unusable password (NEVER default password like password123!)
                        $targetUser = User::create([
                            'name'       => $master->full_name,
                            'email'      => $master->email,
                            'password'   => Hash::make(Str::random(32)),
                            'role_id'    => 4,
                            'company_id' => $master->company_id,
                            'phone'      => $master->phone ?? 'N/A',
                            'status'     => 1,
                            'emp_id'     => $master->id,
                        ]);
                        $appliedWrites++;
                    }

                    if (!Employee::where('e_user_id', $targetUser->id)->exists()) {
                        Employee::create([
                            'owner_user_id' => $ownerUserId,
                            'e_user_id'     => $targetUser->id,
                            'designation'   => $master->position ?? 'Employee',
                            'id_number'     => $master->employee_code ?? Employee::generateIdNumber(),
                            'address'       => 'N/A',
                            'status'        => 1,
                        ]);
                        $appliedWrites++;
                    }

                    Log::info("Reconciliation applied: Linked EmployeeMaster {$master->employee_code} to User ID {$targetUser->id}.");
                });
            }
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Proposed Unlinked User Links', $proposedUserLinks],
                ['Proposed Master Record Links', $proposedMasterLinks],
                ['Ambiguous Emails Skipped', $ambiguousEmailsSkipped],
                ['Unresolvable Owners Skipped', $unresolvableOwnersSkipped],
                ['Database Writes Applied', $appliedWrites],
            ]
        );

        if ($isDryRun) {
            $this->info("Dry-run complete. Run with --apply option to execute these changes.");
        } else {
            $this->info("Reconciliation complete. Applied {$appliedWrites} database write operations.");
        }

        return self::SUCCESS;
    }
}
