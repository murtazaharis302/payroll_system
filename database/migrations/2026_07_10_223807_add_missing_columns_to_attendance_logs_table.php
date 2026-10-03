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
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('employee_id')->constrained('companies')->onDelete('cascade');
            $table->time('log_time')->nullable()->after('log_date');
            $table->string('device_serial')->nullable()->after('device_name');
            $table->string('import_batch')->nullable()->after('import_status');
            $table->foreignId('imported_by')->nullable()->after('import_batch')->constrained('users')->onDelete('set null');
            $table->timestamp('imported_at')->nullable()->after('imported_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['imported_by']);
            $table->dropColumn([
                'company_id',
                'log_time',
                'device_serial',
                'import_batch',
                'imported_by',
                'imported_at'
            ]);
        });
    }
};
