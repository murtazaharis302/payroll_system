<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('company_id')->nullable()->constrained('companies')->onDelete('set null');
            $table->string('action'); // create, update, delete, login, logout, approve, reject
            $table->string('module'); // payroll, employee, vale_loan, etc.
            $table->string('record_type')->nullable(); // Model class name
            $table->unsignedBigInteger('record_id')->nullable(); // ID of affected record
            $table->text('old_value')->nullable(); // JSON of old values
            $table->text('new_value')->nullable(); // JSON of new values
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->text('description')->nullable(); // Human-readable description
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('company_id');
            $table->index('action');
            $table->index('module');
            $table->index(['record_type', 'record_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
