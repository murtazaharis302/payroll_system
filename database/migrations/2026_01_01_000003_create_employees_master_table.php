<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees_master', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('employee_code')->unique(); // EMP-001
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('email')->unique()->nullable();
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->date('date_hired')->nullable();
            $table->string('position')->nullable();
            $table->string('department')->nullable();
            
            // Government IDs
            $table->string('sss_number')->nullable();
            $table->string('philhealth_number')->nullable();
            $table->string('pagibig_number')->nullable();
            $table->string('tin_number')->nullable();
            
            // Biometric
            $table->string('biometric_id')->nullable(); // Device user ID
            
            $table->enum('status', ['active', 'inactive', 'resigned'])->default('active');
            $table->timestamps();
            
            $table->index('company_id');
            $table->index('biometric_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees_master');
    }
};
