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
        Schema::create('widgets', function (Blueprint $table) {
            //Basic Configuration
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->string('theme_color')->default('#4CAF50');
            $table->string('logo_url')->nullable();
            $table->string('welcome_message')->default('How can we help you today?');
            $table->string('offline_message')->default('We are currently offline. Please leave a message and we will get back to you.');
            //Display Settings
            $table->boolean('is_active')->default(true);
            $table->boolean('show_agent_avatar')->default(false);
            $table->boolean('show_agent_name')->default(true);
            $table->boolean('show_file_upload')->default(true);
            $table->enum('position', ['right', 'left', 'bottom'])->default('right');
            $table->integer('z_index')->default(999);
            $table->string('language')->default('ar');
            //Behavior Settings
            $table->boolean('working_hours_enabled')->default(false);
            $table->json('working_hours')->nullable();
            $table->boolean('require_name_email')->default(true);
            $table->boolean('sound_enabled')->default(true);
            $table->boolean('auto_open')->default(false);
            $table->integer('auto_open_delay')->default(10);
            $table->boolean('collect_visitor_data')->default(true);
            //Security Settings
            $table->json('allowed_domains')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('widgets');
    }
};
