<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        $this->safeAddColumn('whatsapp_messages', 'conversation_id', function (Blueprint $table) {
            $table->uuid('conversation_id')->nullable()->after('whatsapp_conversation_id')->index();
        });

        $this->safeAddForeignKey(
            table: 'whatsapp_messages',
            column: 'conversation_id',
            foreignTable: 'conversations',
            constraintName: 'whatsapp_messages_conversation_id_fk'
        );
    }

    public function down(): void
    {
        $this->safeDropForeignKey('whatsapp_messages', 'whatsapp_messages_conversation_id_fk');
        $this->safeDropColumn('whatsapp_messages', 'conversation_id');
    }
};
