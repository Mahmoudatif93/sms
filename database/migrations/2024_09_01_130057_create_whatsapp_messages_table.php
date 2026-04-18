<?php

use App\Http\Meta\Constants;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {

            $table->string('id', 255)->primary()->comment('The Whatsapp Message ID.');

            $table->unsignedBigInteger('whatsapp_phone_number_id')->comment('The Whatsapp Phone Number ID, the message belongs to.');


            // Polymorphic sender and recipient fields
            $table->morphs('sender'); // sender_id and sender_type
            $table->morphs('recipient'); // recipient_id and recipient_type

            $table->enum('sender_role', Constants::SENDER_ROLE)->comment('Role of sender (e.g., business, consumer).');


            $table->string('type')->comment('Type of message (e.g., text, image, video, etc.).');
            $table->enum('direction', Constants::MESSAGE_DIRECTION)->comment('Direction of the message (e.g., sent, received).');
            $table->enum('status', Constants::MESSAGE_STATUS)
                ->default('initiated')
                ->comment('Current status of the message (e.g., initiated, sent, delivered, read, failed, deleted, warning).');

            $table->foreign('whatsapp_phone_number_id')
                ->references('id')
                ->on('whatsapp_phone_numbers')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();;


            $table->timestamp('created_at')->nullable()->comment('Creation timestamp');
            $table->timestamp('updated_at')->nullable()->comment('Last update timestamp');


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_phone_number_id']);
        });

        Schema::dropIfExists('whatsapp_messages');
    }
};
