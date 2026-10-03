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
            if (Schema::hasColumn('pay_headers', 'review_reason')) {
                $table->text('review_reason')->nullable()->change();
            }
            if (Schema::hasColumn('pay_headers', 'attendance_override_reason')) {
                $table->text('attendance_override_reason')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pay_headers', function (Blueprint $table) {
            if (Schema::hasColumn('pay_headers', 'review_reason')) {
                $table->string('review_reason', 255)->nullable()->change();
            }
            if (Schema::hasColumn('pay_headers', 'attendance_override_reason')) {
                $table->string('attendance_override_reason', 255)->nullable()->change();
            }
        });
    }
};
