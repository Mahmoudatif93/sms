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
        // Create unified whatsapp_workflows table
        Schema::create('whatsapp_workflows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('name', 255);
            $table->text('description')->nullable();
            
            // Trigger configuration
            $table->string('trigger_type', 50); // template_status, button_reply, list_reply
            $table->json('trigger_config'); // Flexible config based on trigger_type
            
            // Workflow settings
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->integer('delay_seconds')->default(0);
            
            $table->timestamps();
            
            // Indexes
            $table->index('workspace_id');
            $table->index('trigger_type');
            $table->index('is_active');
            $table->index(['workspace_id', 'trigger_type', 'is_active']);
            
            // Foreign key
            $table->foreign('workspace_id')
                ->references('id')
                ->on('workspaces')
                ->onDelete('cascade');
        });

        // Create unified whatsapp_workflow_actions table
        Schema::create('whatsapp_workflow_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('whatsapp_workflow_id');
            
            $table->string('action_type', 50); // send_template, send_interactive, send_text, etc.
            $table->json('action_config');
            
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('delay_seconds')->default(0);
            
            $table->timestamps();
            
            // Indexes
            $table->index('whatsapp_workflow_id');
            $table->index('action_type');
            $table->index(['whatsapp_workflow_id', 'is_active', 'order'])->name('whatsapp_workflow_id_is_active');
            
            // Foreign key
            $table->foreign('whatsapp_workflow_id')
                ->references('id')
                ->on('whatsapp_workflows')
                ->onDelete('cascade');
        });

        // Create unified whatsapp_workflow_logs table
        Schema::create('whatsapp_workflow_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('whatsapp_workflow_id');
            $table->uuid('whatsapp_workflow_action_id')->nullable();
            
            $table->string('trigger_message_id')->nullable(); // WhatsApp message ID
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed
            $table->json('context')->nullable(); // Additional context data
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('whatsapp_workflow_id');
            $table->index('whatsapp_workflow_action_id');
            $table->index('status');
            $table->index('trigger_message_id');
            
            // Foreign keys
            $table->foreign('whatsapp_workflow_id')
                ->references('id')
                ->on('whatsapp_workflows')
                ->onDelete('cascade');
                
            $table->foreign('whatsapp_workflow_action_id')
                ->references('id')
                ->on('whatsapp_workflow_actions')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_workflow_logs');
        Schema::dropIfExists('whatsapp_workflow_actions');
        Schema::dropIfExists('whatsapp_workflows');
    }
};

