<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('income_types', function (Blueprint $table) {
            if (!Schema::hasColumn('income_types', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->onDelete('cascade');
            }
            if (!Schema::hasColumn('income_types', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('is_active')->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('income_types', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->onDelete('set null');
            }
        });

        Schema::table('deduction_types', function (Blueprint $table) {
            if (!Schema::hasColumn('deduction_types', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->onDelete('cascade');
            }
            if (!Schema::hasColumn('deduction_types', 'is_taxable')) {
                $table->boolean('is_taxable')->default(false)->after('type');
            }
            if (!Schema::hasColumn('deduction_types', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('is_active')->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('deduction_types', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('income_types', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['company_id', 'created_by', 'updated_by']);
        });

        Schema::table('deduction_types', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['company_id', 'is_taxable', 'created_by', 'updated_by']);
        });
    }
};
