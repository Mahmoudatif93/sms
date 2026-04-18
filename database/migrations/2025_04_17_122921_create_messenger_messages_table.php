<?php

use App\Http\Meta\Constants;
use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('messenger_messages', function (Blueprint $table) {
            $table->string('id', 255)->primary()->comment('The Messenger Message ID.');

            $table->string('meta_page_id');
            $table->uuid('conversation_id')->nullable(); // MessengerConversation FK (optional)

            $table->morphs('sender'); // sender_id and sender_type
            $table->morphs('recipient'); // recipient_id and recipient_type

            $table->enum('sender_role', Constants::SENDER_ROLE)->comment('Role of sender (e.g., business, consumer).');


            $table->string('type')->comment('Type of message (e.g., text, image, video, etc.).');
            $table->enum('direction', Constants::MESSAGE_DIRECTION)->comment('Direction of the message (e.g., sent, received).');
            $table->enum('status', Constants::MESSAGE_STATUS)
                ->default('initiated')
                ->comment('Current status of the message (e.g., initiated, sent, delivered, read, failed, deleted, warning).');


            $table->string('messenger_conversation_id')->nullable();

            $table->unsignedBigInteger('messageable_id')->nullable();
            $table->string('messageable_type')->nullable();


            $table->string('messenger_message_type')->nullable();

            $table->timestamps();

        });

        // Add safe foreign keys
        $this->safeAddForeignKey(
            table: 'messenger_messages',
            column: 'meta_page_id',
            foreignTable: 'meta_pages',
            constraintName: 'messenger_messages_meta_page_id_fk'
        );

        $this->safeAddForeignKey(
            table: 'messenger_messages',
            column: 'conversation_id',
            foreignTable: 'conversations',
            constraintName: 'messenger_messages_conversation_id_fk'
        );
    }

    public function down(): void
    {
        $this->safeDropForeignKey('messenger_messages', 'messenger_messages_meta_page_id_fk');
        $this->safeDropForeignKey('messenger_messages', 'messenger_messages_conversation_id_fk');
        $this->safeDropTable('messenger_messages');
    }
};
