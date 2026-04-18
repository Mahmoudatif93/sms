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
        Schema::create('interactive_message_drafts', function (Blueprint $table) {
            $table->id();
            $table->uuid('workspace_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('interactive_type', ['button', 'list'])->default('button');

            // Header (optional)
            $table->json('header')->nullable()->comment('Header object: {type: text|image|video|document, text?: string, media_id?: string}');

            // Body (required)
            $table->text('body')->comment('Main message body text (max 1024 chars)');

            // Footer (optional)
            $table->string('footer', 60)->nullable()->comment('Footer text (max 60 chars)');

            // Action content based on type
            $table->json('buttons')->nullable()->comment('For button type: array of {id, title} (max 3 buttons)');
            $table->string('list_button_text', 20)->nullable()->comment('For list type: button text to open list');
            $table->json('sections')->nullable()->comment('For list type: array of sections with rows');

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('workspace_id');
            $table->index('interactive_type');
            $table->index('is_active');

            // Foreign key
            $table->foreign('workspace_id')
                ->references('id')
                ->on('workspaces')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interactive_message_drafts');
    }
};

