<?php

use App\Models\EmpIncome;
use App\Models\IncomeType;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Deletes existing legacy BASIC EmpIncome records to clean up database duplicates.
     */
    public function up(): void
    {
        $basicTypeIds = IncomeType::withoutGlobalScopes()
            ->where('code', 'BASIC')
            ->pluck('id');

        if ($basicTypeIds->isNotEmpty()) {
            EmpIncome::withoutGlobalScopes()
                ->whereIn('income_type_id', $basicTypeIds)
                ->delete();
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
