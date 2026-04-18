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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->onDelete('cascade'); // Linking permission to a menu
            $table->foreignId('role_id')->nullable()->constrained()->onDelete('cascade'); // Role-based permissions
            $table->unsignedBigInteger('user_id')->nullable(); 
            $table->boolean('can_access')->default(0); // Can the role/user access this menu?
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropForeign(['user_id']); // Drop foreign key constraint
        });
        Schema::dropIfExists('permissions');
    }
};
