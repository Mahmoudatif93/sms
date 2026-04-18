<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create ticket_email_configurations table
        Schema::create('ticket_email_configurations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('email_address')->unique();
            $table->string('mail_server');
            $table->string('mail_port');
            $table->string('mail_username');
            $table->text('mail_password');
            $table->string('mail_encryption')->default('tls');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_email_configurations');
    }
};
