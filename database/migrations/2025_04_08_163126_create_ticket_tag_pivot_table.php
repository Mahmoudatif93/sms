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
        Schema::create('ticket_tag_pivot', function (Blueprint $table) {
            $table->uuid('ticket_id');
            $table->uuid('tag_id');
            $table->primary(['ticket_id', 'tag_id']);
            
            $table->foreign('ticket_id')->references('id')->on('tickets');
            $table->foreign('tag_id')->references('id')->on('ticket_tags');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_tag_pivot');
    }
};
