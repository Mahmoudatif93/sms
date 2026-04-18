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
        Schema::create('ticket_forms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('theme_color', 20)->default('#3498db');
            $table->string('success_message')->default('Thank you for your submission!');
            $table->string('submit_button_text')->default('Submit');
            $table->string('license_id')->nullable();
            $table->timestamp('license_expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('workspace_id')->references('id')->on('workspaces');
            $table->foreign('created_by')->references('id')->on('user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_froms');
    }
};
