<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deduction_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // SSS, PhilHealth, Pag-IBIG, Tax, Loan, Vale, Goods
            $table->string('code')->unique(); // SSS, PHILHEALTH, PAGIBIG, TAX, LOAN, VALE, GOODS
            $table->text('description')->nullable();
            $table->enum('type', ['statutory', 'loan', 'other'])->default('other'); // statutory = SSS/PhilHealth/PagIBIG
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deduction_types');
    }
};
