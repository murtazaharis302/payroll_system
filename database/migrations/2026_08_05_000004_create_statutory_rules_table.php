<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('statutory_rules')) {
            Schema::create('statutory_rules', function (Blueprint $table) {
                $table->id();
                $table->string('statutory_type', 30); // sss, philhealth, pagibig, tax
                $table->string('version_code', 50)->unique();
                $table->string('source_document');
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->decimal('salary_floor', 10, 2)->default(0.00);
                $table->decimal('salary_ceiling', 10, 2)->nullable();
                $table->decimal('ee_share_rate', 8, 4)->nullable();
                $table->decimal('er_share_rate', 8, 4)->nullable();
                $table->json('brackets')->nullable();
                $table->integer('rounding_decimals')->default(2);
                $table->string('rounding_mode', 20)->default('half_up');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['statutory_type', 'effective_from', 'effective_to', 'is_active'], 'idx_statutory_rule_lookup');
            });
        }

        if (Schema::hasTable('pay_headers') && !Schema::hasColumn('pay_headers', 'policy_version')) {
            Schema::table('pay_headers', function (Blueprint $table) {
                $table->string('policy_version')->nullable()->after('status');
            });
        }

        (new \Database\Seeders\StatutoryRulesSeeder())->run();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('pay_headers') && Schema::hasColumn('pay_headers', 'policy_version')) {
            Schema::table('pay_headers', function (Blueprint $table) {
                $table->dropColumn('policy_version');
            });
        }

        Schema::dropIfExists('statutory_rules');
    }
};
