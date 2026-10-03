<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Check if company_id column exists, add if it doesn't
        if (!Schema::hasColumn('employees_master', 'company_id')) {
            Schema::table('employees_master', function (Blueprint $table) {
                $table->foreignId('company_id')->after('id')->constrained('companies')->onDelete('cascade');
                $table->index('company_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees_master', 'company_id')) {
            Schema::table('employees_master', function (Blueprint $table) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            });
        }
    }
};
