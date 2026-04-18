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
        // Add last_message_at column for faster sorting
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('last_message_at')->nullable()->after('status');

            // Composite index for common query patterns
            $table->index(['workspace_id', 'status', 'last_message_at'], 'conv_workspace_status_lastmsg_idx');
            $table->index(['workspace_id', 'platform', 'status'], 'conv_workspace_platform_status_idx');
        });

        // Add indexes for whatsapp_messages
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'direction', 'status'], 'wa_msg_conv_dir_status_idx');
            $table->index(['conversation_id', 'direction', 'type'], 'wa_msg_conv_dir_type_idx');
            $table->index(['conversation_id', 'created_at'], 'wa_msg_conv_created_idx');
        });

        // Add indexes for livechat_messages
        Schema::table('livechat_messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'sender_type', 'status'], 'lc_msg_conv_sender_status_idx');
            $table->index(['conversation_id', 'created_at'], 'lc_msg_conv_created_idx');
        });

        // Add indexes for messenger_messages
        Schema::table('messenger_messages', function (Blueprint $table) {
            $table->index(['conversation_id', 'direction', 'status'], 'ms_msg_conv_dir_status_idx');
            $table->index(['conversation_id', 'created_at'], 'ms_msg_conv_created_idx');
        });

        // Add indexes for conversation_agents
        Schema::table('conversation_agents', function (Blueprint $table) {
            $table->index(['conversation_id', 'inbox_agent_id', 'removed_at'], 'conv_agents_conv_agent_removed_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conv_workspace_status_lastmsg_idx');
            $table->dropIndex('conv_workspace_platform_status_idx');
            $table->dropColumn('last_message_at');
        });

        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropIndex('wa_msg_conv_dir_status_idx');
            $table->dropIndex('wa_msg_conv_dir_type_idx');
            $table->dropIndex('wa_msg_conv_created_idx');
        });

        Schema::table('livechat_messages', function (Blueprint $table) {
            $table->dropIndex('lc_msg_conv_sender_status_idx');
            $table->dropIndex('lc_msg_conv_created_idx');
        });

        Schema::table('messenger_messages', function (Blueprint $table) {
            $table->dropIndex('ms_msg_conv_dir_status_idx');
            $table->dropIndex('ms_msg_conv_created_idx');
        });

        Schema::table('conversation_agents', function (Blueprint $table) {
            $table->dropIndex('conv_agents_conv_agent_removed_idx');
        });
    }
};
