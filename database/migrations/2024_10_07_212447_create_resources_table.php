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
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('version')->default('v1');  // API version (v1, v2, etc.)
            $table->string('method');  // HTTP method (GET, POST, PUT, DELETE, etc.)
            $table->string('uri');  // The URI (e.g., /organizations/{organization}/iam-policies)
            $table->boolean('is_active')->default(false); // Add is_active with a default value of true
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
