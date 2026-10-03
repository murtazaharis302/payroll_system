<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('deduction_types', 'is_loan_enabled')) {
            Schema::table('deduction_types', function (Blueprint $table) {
                $table->boolean('is_loan_enabled')->default(false)->after('is_active');
            });
        }

        // Set is_loan_enabled = true ONLY for deduction types explicitly linked to existing loan_types records
        DB::table('deduction_types')
            ->whereIn('id', function ($query) {
                $query->select('deduction_type_id')
                    ->from('loan_types')
                    ->whereNotNull('deduction_type_id');
            })
            ->update(['is_loan_enabled' => true]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deduction_types', function (Blueprint $table) {
            $table->dropColumn('is_loan_enabled');
        });
    }
};
