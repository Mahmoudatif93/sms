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
        Schema::create('message_billings', function (Blueprint $table) {
            $table->id();
            $table->string('messageable_id');
            $table->string('messageable_type');
            $table->float('cost'); // Service cost
            $table->enum('type', ['translation', 'chatbot'])->default('translation');
            $table->boolean('is_billed')->default(false); // Billing status
            $table->json('metadata')->nullable(); // Additional data (e.g., language for translation)
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['messageable_type', 'messageable_id']);
            $table->index('type');
            $table->index('is_billed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_billings');
    }
};
