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
        Schema::create('whatsapp_conversation_billings', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_id');
            $table->foreign('conversation_id')
                ->references('id')
                ->on('whatsapp_conversations')
                ->onDelete('cascade')
                ->onUpdate('cascade');
            $table->string('type');
            $table->decimal('cost', 10, 2)->default(0.00);
            $table->string('currency', 3)->default('USD');
            $table->boolean('billable')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversation_billings');
    }
};
