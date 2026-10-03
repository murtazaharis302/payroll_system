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
        if (Schema::hasTable('companies') && !Schema::hasColumn('companies', 'address')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->text('address')->nullable()->after('website');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('companies') && Schema::hasColumn('companies', 'address')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('address');
            });
        }
    }
};
