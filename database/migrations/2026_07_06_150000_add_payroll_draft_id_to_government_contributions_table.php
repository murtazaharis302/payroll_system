<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('government_contributions', function (Blueprint $table) {
            if (!Schema::hasColumn('government_contributions', 'payroll_draft_id')) {
                $table->foreignId('payroll_draft_id')->nullable()->after('payroll_period_id')
                    ->constrained('payroll_drafts')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('government_contributions', function (Blueprint $table) {
            if (Schema::hasColumn('government_contributions', 'payroll_draft_id')) {
                $table->dropForeign(['payroll_draft_id']);
                $table->dropColumn('payroll_draft_id');
            }
        });
    }
};
