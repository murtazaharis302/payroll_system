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
        Schema::create('company_bank_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('bank_name');
            $table->string('branch_name')->nullable();
            $table->string('manager_name')->nullable();
            $table->string('account_number');
            $table->string('corporate_signee_1')->nullable();
            $table->string('corporate_signee_1_title')->nullable();
            $table->string('corporate_signee_2')->nullable();
            $table->string('corporate_signee_2_title')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_bank_settings');
    }
};
