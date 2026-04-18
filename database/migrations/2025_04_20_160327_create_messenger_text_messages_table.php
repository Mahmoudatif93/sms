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
        $this->safeCreateTable('messenger_text_messages', function (Blueprint $table) {
            $table->id();
            $table->string('messenger_message_id', 255);
            $table->text('text'); // UTF-8, Facebook limit is 2000 characters
            $table->timestamps();
        });

        Schema::table("messenger_text_messages", function (Blueprint $table) {
            $table->foreign('messenger_message_id', 'messenger_text_msg_fk')
                ->references('id')
                ->on('messenger_messages')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });

    }

    public function down(): void
    {
        $this->safeDropForeignKey('messenger_text_messages', 'messenger_text_msg_fk');
        $this->safeDropTable('messenger_text_messages');
    }
};
