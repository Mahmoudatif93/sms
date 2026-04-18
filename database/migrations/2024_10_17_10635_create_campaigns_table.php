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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->enum('type', ['whatsapp', 'sms', 'email']); 
            $table->enum('send_time_method', ['NOW', 'LATER']);
            $table->timestamp('send_time')->nullable();
            $table->string("time_zone")->nullable(); // Added time zone field
            $table->uuid('list_id')->foreign('list_id')->references('id')->on('contacts')->constrained()->onDelete('cascade');
            $table->foreignId('whatsapp_message_template_id')->constrained();
            $table->uuid('workspace_id');
            $table->timestamps();
            $table->foreign('workspace_id')
            ->references('id')
            ->on('workspaces')
            ->cascadeOnDelete()
            ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
