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
        Schema::create('workspace_users', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');// Match users.id type (unsigned big integer)
            $table->string('workspace_id');
            $table->foreign('workspace_id')->references('id')->on('workspaces')->onDelete('cascade');
            $table->string('status')->default('active');

             // Foreign key constraints
             $table->foreign('user_id')
             ->references('id')
             ->on('user')
             ->cascadeOnDelete()
             ->cascadeOnUpdate();
             
            // Create a unique constraint to prevent duplicate follows
            $table->unique(['user_id', 'workspace_id']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workspace_users');
    }
};
