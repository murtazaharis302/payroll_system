<?php

use App\Models\EmpIncome;
use App\Models\IncomeType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Deletes reintroduced BASIC EmpIncome records and deactivates legacy duplicate custom Basic Pay types.
     */
    public function up(): void
    {
        $canonicalBasic = IncomeType::withoutGlobalScopes()
            ->where('code', 'BASIC')
            ->whereNull('company_id')
            ->first();

        $canonicalBasicId = $canonicalBasic?->id;

        // Identify any duplicate custom basic types (strictly excluding canonical system BASIC)
        $duplicateCustomTypes = IncomeType::withoutGlobalScopes()
            ->when($canonicalBasicId, function ($query, $cid) {
                $query->where('id', '!=', $cid);
            })
            ->where(function ($query) {
                $query->whereIn(DB::raw('UPPER(code)'), ['BASIC', 'BASIC_PAY', 'BASICPAY', 'BASIC_SALARY', 'BASICSALARY'])
                    ->orWhereIn(DB::raw('LOWER(TRIM(name))'), ['basic pay', 'basic salary', 'basicpay', 'basicsalary']);
            })
            ->get();

        // Target all basic types (canonical + duplicates) for EmpIncome cleanup
        $allBasicTypeIds = IncomeType::withoutGlobalScopes()
            ->where(function ($query) use ($canonicalBasicId) {
                if ($canonicalBasicId) {
                    $query->where('id', $canonicalBasicId);
                }
                $query->orWhereIn(DB::raw('UPPER(code)'), ['BASIC', 'BASIC_PAY', 'BASICPAY', 'BASIC_SALARY', 'BASICSALARY'])
                    ->orWhereIn(DB::raw('LOWER(TRIM(name))'), ['basic pay', 'basic salary', 'basicpay', 'basicsalary']);
            })
            ->pluck('id');

        if ($allBasicTypeIds->isNotEmpty()) {
            EmpIncome::withoutGlobalScopes()
                ->whereIn('income_type_id', $allBasicTypeIds)
                ->delete();
        }

        // Deactivate all duplicate custom basic types so they cannot be configured
        if ($duplicateCustomTypes->isNotEmpty()) {
            IncomeType::withoutGlobalScopes()
                ->whereIn('id', $duplicateCustomTypes->pluck('id'))
                ->update(['is_active' => false]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Basic Pay is managed strictly via EmployeeMaster core compensation rates; no reversal required.
    }
};
