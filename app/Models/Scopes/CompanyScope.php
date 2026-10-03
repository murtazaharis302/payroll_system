<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use App\Models\Role;

class CompanyScope implements Scope
{
    /**
     * Recursion guard to prevent infinite loops when retrieving the authenticated user.
     */
    protected static bool $resolvingUser = false;

    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (self::$resolvingUser) {
            return;
        }

        self::$resolvingUser = true;

        try {
            $user = auth()->user();
        } catch (\Throwable $e) {
            $user = null;
        } finally {
            self::$resolvingUser = false;
        }
        
        // Exempt SuperAdmin strictly from company filtering
        if ($user && (int) $user->role_id === Role::SUPER_ADMIN) {
            return;
        }

        $companyId = session('company_id') ?? ($user?->company_id);

        if ($companyId !== null) {
            $table = $model->getTable();
            
            // List of tables that have company_id column directly (strict tenant boundary)
            $directTenantTables = [
                'users',
                'employees_master',
                'payroll_periods',
                'departments',
                'sub_departments',
                'government_contributions',
            ];

            if (in_array($table, ['loan_types', 'income_types', 'deduction_types'], true)) {
                $builder->where(function (Builder $query) use ($table, $companyId) {
                    $query->where($table . '.company_id', $companyId)
                          ->orWhereNull($table . '.company_id');
                });
            } elseif (in_array($table, $directTenantTables, true)) {
                $builder->where($table . '.company_id', $companyId);
            } elseif ($table === 'emp_loans') {
                $builder->whereHas('employee', function (Builder $query) use ($companyId) {
                    $query->where('company_id', $companyId);
                });
            } elseif ($table === 'pay_headers') {
                $builder->whereHas('employee', function (Builder $query) use ($companyId) {
                    $query->where('company_id', $companyId);
                });
            } elseif ($table === 'loan_payments') {
                $builder->whereHas('empLoan.employee', function (Builder $query) use ($companyId) {
                    $query->where('company_id', $companyId);
                });
            } elseif ($table === 'pay_ins' || $table === 'pay_outs') {
                $builder->whereHas('payHeader.employee', function (Builder $query) use ($companyId) {
                    $query->where('company_id', $companyId);
                });
            }
        }
    }
}
