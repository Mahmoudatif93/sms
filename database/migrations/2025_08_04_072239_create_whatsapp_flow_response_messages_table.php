<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    use SafeMigration;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->safeCreateTable('whatsapp_flow_response_messages', function (Blueprint $table) {
            $table->id();

            $table->string('whatsapp_message_id');
            $table->string('flow_token')->nullable(); // Flow session identifier
            $table->string('name')->nullable(); // e.g. "flow"
            $table->string('body')->nullable(); // e.g. button label or confirmation
            $table->json('response_json')->nullable(); // Full response_json from webhook

            $table->timestamps();
        });

        $this->safeAddForeignKey(
            table: 'whatsapp_flow_response_messages',
            column: 'whatsapp_message_id',
            foreignTable: 'whatsapp_messages',
            foreignColumn: 'id',
            constraintName: 'whatsapp_flow_response_messages_message_id_fk'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->safeDropForeignKey('whatsapp_flow_response_messages', 'whatsapp_flow_response_messages_message_id_fk');
        Schema::dropIfExists('whatsapp_flow_response_messages');
    }
};
