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
        Schema::create('whatsapp_auth_template_supported_apps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('button_component_id');
            $table->string('package_name'); // The package name of the app
            $table->string('signature_hash'); // The signature hash for the app
            $table->timestamps();

            $table->foreign('button_component_id', 'fk_supported_apps_button_component')
                ->references('id')
                ->on('whatsapp_auth_template_button_components')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_auth_template_supported_apps', function (Blueprint $table) {
            // Drop the foreign key constraint before dropping the table
            $table->dropForeign(['fk_supported_apps_button_component']);
        });
        Schema::dropIfExists('whatsapp_auth_template_supported_apps');
    }
};
