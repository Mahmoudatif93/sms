<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use \App\Traits\SafeMigration;
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->safeCreateTable('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('platform'); // WhatsApp, SMS, LiveChat, etc.
            $table->uuid('channel_id'); // Foreign key to channels
            $table->uuid('contact_id'); // Foreign key to contacts
            $table->string('status')->default('open'); // Default status of the conversation
            $table->timestamps();
            $table->softDeletes();
        });

        // Adding foreign key constraints safely
        $this->safeAddForeignKey('conversations', 'channel_id', 'channels', 'id');
        $this->safeAddForeignKey('conversations', 'contact_id', 'contacts', 'id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->safeDropForeignKey('conversations', 'conversations_channel_id_fk');
        $this->safeDropForeignKey('conversations', 'conversations_contact_id_fk');
        $this->safeDropTable('conversations');
    }
};
