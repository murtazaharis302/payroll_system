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
        // Clean existing duplicate e_user_id records to ensure migration succeeds on production data
        $duplicates = DB::table('employees')
            ->select('e_user_id', DB::raw('MAX(id) as keep_id'), DB::raw('COUNT(*) as total'))
            ->groupBy('e_user_id')
            ->having('total', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('employees')
                ->where('e_user_id', $duplicate->e_user_id)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->unique('e_user_id', 'employees_e_user_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique('employees_e_user_id_unique');
        });
    }
};
