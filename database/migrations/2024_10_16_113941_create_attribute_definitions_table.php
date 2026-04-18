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
        Schema::create('attribute_definitions', function (Blueprint $table) {
            $table->uuid('id')->primary(); // UUID for internal reference
            $table->uuid('workspace_id')->nullable(); // Foreign key to the workspace
            $table->string('key')->unique(); // The key for the attribute
            $table->string('display_name'); // User-friendly name for the attribute
            $table->enum('cardinality', ['one', 'many']); // One or many values per attribute
            $table->enum('type', ['boolean', 'datetime', 'number', 'string']); // Data type
            $table->boolean('pii')->default(false); // Is it personally identifiable information?
            $table->boolean('read_only')->default(false); // Can it be updated?
            $table->boolean('builtin')->default(false); // Is it built-in or user-defined?
            $table->timestamps();

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
        Schema::table('attribute_definitions', function (Blueprint $table) {
            // Drop the foreign key constraint on 'contact_id'
            $table->dropForeign(['workspace_id']);
        });
        Schema::dropIfExists('attribute_definitions');
    }
};
