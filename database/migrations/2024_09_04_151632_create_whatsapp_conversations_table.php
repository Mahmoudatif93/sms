<?php

use App\Http\Meta\Constants;
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
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->string('id', 255)->primary()->comment('The unique identifier for the conversation.');
            $table->unsignedBigInteger('whatsapp_phone_number_id')->comment('The Whatsapp Phone Number ID, the Conversation message belongs to.');

            $table->enum('type', Constants::CONVERSATION_CATEGORIES)->comment('Indicates conversation category.');
            $table->integer('expiration_timestamp')->nullable()->comment('Date when the conversation expires.');
            $table->timestamps(); // created_at and updated_at timestamps

            $table->foreign('whatsapp_phone_number_id')
                ->references('id')
                ->on('whatsapp_phone_numbers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_conversations', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_phone_number_id']);
        });
        Schema::dropIfExists('whatsapp_conversations');
    }
};
