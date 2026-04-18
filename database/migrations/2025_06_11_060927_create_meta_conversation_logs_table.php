<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('meta_conversation_logs', function (Blueprint $table) {
            $table->id();

            $table->uuid('conversation_id')->nullable();
            $table->string('whatsapp_message_id')->nullable();
            $table->string('whatsapp_conversation_id')->nullable();

            $table->string('decision'); // e.g. csw_closed_failed, template_opened_new
            $table->string('category_attempted')->nullable(); // marketing, utility, etc.
            $table->string('message_type'); // text, template, image, etc.
            $table->string('direction'); // SENT, RECEIVED
            $table->boolean('was_blocked')->default(false);

            $table->integer('meta_error_code')->nullable();
            $table->text('meta_error_message')->nullable();

            $table->text('text_log')->nullable();

            $table->timestamps();

        });

        // Add foreign keys
        $this->safeAddForeignKey('meta_conversation_logs', 'conversation_id', 'conversations');
        $this->safeAddForeignKey('meta_conversation_logs', 'whatsapp_message_id', 'whatsapp_messages', 'id', 'meta_logs_whatsapp_message_id_fk');
        $this->safeAddForeignKey('meta_conversation_logs', 'whatsapp_conversation_id', 'whatsapp_conversations', 'id', 'meta_logs_whatsapp_conversation_id_fk');
    }

    public function down(): void
    {
        $this->safeDropForeignKey('meta_conversation_logs', 'meta_logs_whatsapp_message_id_fk');
        $this->safeDropForeignKey('meta_conversation_logs', 'meta_logs_whatsapp_conversation_id_fk');
        $this->safeDropForeignKey('meta_conversation_logs', 'meta_conversation_logs_conversation_id_fk');

        $this->safeDropTable('meta_conversation_logs');
    }
};
