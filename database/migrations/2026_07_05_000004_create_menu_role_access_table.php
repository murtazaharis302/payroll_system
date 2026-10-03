<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_role_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->onDelete('cascade');
            $table->string('role'); // admin, company, hr, accounting, employee, user
            $table->boolean('can_access')->default(true);
            $table->timestamps();
            
            $table->unique(['menu_id', 'role']);
            $table->index('menu_id');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_role_access');
    }
};
