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
        Schema::create('tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('ticket_number')->unique();
            $table->uuid('workspace_id');
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('status')->default('open');
            $table->string('priority')->default('medium');
            $table->string('source')->comment('conversation, email, iframe');
            $table->uuid('contact_id')->nullable();
            $table->uuid('channel_id')->nullable();
            $table->uuid('conversation_id')->nullable();
            $table->string('email')->nullable();
            $table->integer('assigned_to')->nullable();
            $table->timestamp('due_date')->nullable();
            $table->boolean('send_notification')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('workspace_id')->references('id')->on('workspaces');
            $table->foreign('contact_id')->references('id')->on('contacts');
            $table->foreign('channel_id')->references('id')->on('channels');
            $table->foreign('conversation_id')->references('id')->on('conversations');
            $table->foreign('assigned_to')->references('id')->on('user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
