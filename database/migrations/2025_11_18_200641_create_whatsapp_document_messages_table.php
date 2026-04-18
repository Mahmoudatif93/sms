<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    use SafeMigration;

    public function up(): void
    {
        // Create a table safely
        $this->safeCreateTable('whatsapp_document_messages', function (Blueprint $table) {
            $table->id();

            // FK to whatsapp_messages → Meta message ID (string)
            $table->string('whatsapp_message_id', 255)
                ->comment('Foreign key to whatsapp_messages');

            // Document fields
            $table->string('media_id')->nullable()
                ->comment('Uploaded media ID from WhatsApp Cloud API');

            $table->string('link')->nullable()
                ->comment('Direct link to document (OSS/external)');

            $table->string('filename')->nullable()
                ->comment('Original file name');

            $table->text('caption')->nullable()
                ->comment('Caption for the document');

            $table->timestamps();
        });

        // Add foreign key safely
        $this->safeAddForeignKey(
            table: 'whatsapp_document_messages',
            column: 'whatsapp_message_id',
            foreignTable: 'whatsapp_messages',
            foreignColumn: 'id',
            constraintName: 'whatsapp_document_messages_whatsapp_message_id_fk',
            onDelete: 'cascade'
        );
    }

    public function down(): void
    {
        // Drop FK safely
        $this->safeDropForeignKey(
            table: 'whatsapp_document_messages',
            constraintName: 'whatsapp_document_messages_whatsapp_message_id_fk'
        );

        // Drop table safely
        $this->safeDropTable('whatsapp_document_messages');
    }
};
