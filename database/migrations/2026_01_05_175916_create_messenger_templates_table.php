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
        Schema::create('messenger_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('meta_page_id');
            $table->foreign('meta_page_id')
                ->references('id')
                ->on('meta_pages')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->string('name')->comment('Template name for identification');
            $table->enum('type', ['generic', 'button', 'media', 'receipt'])
                ->comment('Template type: generic, button, media, receipt');
            $table->json('payload')->comment('The template payload structure');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['meta_page_id', 'type']);
            $table->index(['meta_page_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messenger_templates');
    }
};
