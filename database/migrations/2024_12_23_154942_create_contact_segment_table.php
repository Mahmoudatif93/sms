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
        Schema::create('contact_segment', function (Blueprint $table) {
            $table->id();
            $table->uuid('contact_id'); // Contact ID
            $table->uuid('segment_id'); // Segment ID
            $table->timestamps(); // Timestamps for created_at and updated_at

            // Foreign key constraints
            $table->foreign('contact_id')
                ->references('id')
                ->on('contacts')
                ->onDelete('cascade');

            $table->foreign('segment_id')
                ->references('id')
                ->on('segments')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_segment', function (Blueprint $table) {
            $table->dropForeign(['contact_id']);
            $table->dropForeign(['segment_id']);
        });
        Schema::dropIfExists('contact_segment');
    }
};
