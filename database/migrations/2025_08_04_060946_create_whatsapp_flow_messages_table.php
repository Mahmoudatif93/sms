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
        $this->safeCreateTable('whatsapp_flow_messages', function (Blueprint $table) {
            $table->id();

            // Relationships
            $table->string('whatsapp_message_id');
            $table->unsignedBigInteger('whatsapp_flow_id');

            // Message content
            $table->string('header_text');
            $table->text('body_text');
            $table->string('footer_text')->nullable();
            $table->string('flow_cta');
            $table->string('flow_token')->nullable();
            $table->string('screen_id');

            $table->timestamps();

        });

        $this->safeAddForeignKey(
            table: 'whatsapp_flow_messages',
            column: 'whatsapp_message_id',
            foreignTable: 'whatsapp_messages',
            foreignColumn: 'id',
            constraintName: 'whatsapp_flow_messages_whatsapp_message_id_fk'
        );

        $this->safeAddForeignKey(
            table: 'whatsapp_flow_messages',
            column: 'whatsapp_flow_id',
            foreignTable: 'whatsapp_flows',
            foreignColumn: 'id',
            constraintName: 'whatsapp_flow_messages_flow_id_fk'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        $this->safeDropForeignKey('whatsapp_flow_messages', 'whatsapp_flow_messages_whatsapp_message_id_fk');
        $this->safeDropForeignKey('whatsapp_flow_messages', 'whatsapp_flow_messages_flow_id_fk');
        Schema::dropIfExists('whatsapp_flow_messages');
    }
};
