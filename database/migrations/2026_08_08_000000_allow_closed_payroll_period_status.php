<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->enum('status', ['open', 'processing', 'closed', 'completed', 'cancelled'])
                ->default('open')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->enum('status', ['open', 'processing', 'completed', 'cancelled'])
                ->default('open')
                ->change();
        });
    }
};
