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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_user_id');
            $table->unsignedBigInteger('e_user_id');
            $table->string('designation')->nullable();
            $table->string('id_number');
            $table->string('age')->nullable();
            $table->string('address')->nullable();
            $table->tinyInteger('status')->default('1')->comment('	1 = Active, 2 = Inactive, 0 = Pending	');
            $table->timestamps();
            $table->foreign('owner_user_id')->references('id')->on('users')->onDelete('NO ACTION')->onUpdate('cascade');
            $table->foreign('e_user_id')->references('id')->on('users')->onDelete('NO ACTION')->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
