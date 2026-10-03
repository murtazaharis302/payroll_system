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
        Schema::table('pay_headers', function (Blueprint $table) {
            if (!Schema::hasColumn('pay_headers', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('posted_at');
            }
            if (!Schema::hasColumn('pay_headers', 'submitted_by')) {
                $table->foreignId('submitted_by')->nullable()->after('submitted_at')->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('pay_headers', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('submitted_by');
            }
            if (!Schema::hasColumn('pay_headers', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('approved_at');
            }
            if (!Schema::hasColumn('pay_headers', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('rejected_at')->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('pay_headers', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_by');
            }
            if (!Schema::hasColumn('pay_headers', 'notes')) {
                $table->text('notes')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('pay_headers', 'policy_version')) {
                $table->string('policy_version')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('pay_headers', 'lock_version')) {
                $table->unsignedInteger('lock_version')->default(1)->after('policy_version');
            }
        });

        Schema::table('pay_headers', function (Blueprint $table) {
            $table->string('status', 30)->default('draft')->change();
        });

        // Backfill encoded pending_approval records before accessor removal
        DB::table('pay_headers')
            ->where('status', 'draft')
            ->whereNotNull('posted_at')
            ->update([
                'status' => 'pending_approval',
                'submitted_at' => DB::raw('posted_at'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('pay_headers')
            ->where('status', 'pending_approval')
            ->update([
                'status' => 'draft',
            ]);

        Schema::table('pay_headers', function (Blueprint $table) {
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropColumn([
                'submitted_at',
                'submitted_by',
                'approved_at',
                'rejected_at',
                'rejected_by',
                'rejection_reason',
                'notes',
                'policy_version',
                'lock_version',
            ]);
        });
    }
};
