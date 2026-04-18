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
        Schema::create('contact_list', function (Blueprint $table) {
            $table->id();
            $table->uuid('list_id')->foreign('list_id')->references('id')->on('contacts')->constrained()->onDelete('cascade');
            $table->uuid('contact_id')->foreign('contact_id')->references('id')->on('lists')->constrained()->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('iam_contact_group');
    }
};
