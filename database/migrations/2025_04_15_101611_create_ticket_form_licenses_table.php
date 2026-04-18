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
        Schema::create('ticket_form_licenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id');
            $table->string('license_key')->unique();
            $table->timestamp('valid_from');
            $table->timestamp('expires_at')->nullable();
            $table->integer('max_forms')->default(1);
            $table->integer('max_submissions_per_month')->nullable();
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
        Schema::dropIfExists('ticket_form_licenses');
    }
};
