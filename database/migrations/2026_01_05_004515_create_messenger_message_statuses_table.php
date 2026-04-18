<?php

use App\Http\Meta\Constants;
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
        Schema::create('messenger_message_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('messenger_message_id', 255);
            $table->foreign('messenger_message_id')
                ->references('id')
                ->on('messenger_messages')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->enum('status', Constants::MESSAGE_STATUS)
                ->comment('Status of the message (e.g., sent, delivered, read, failed).');
            $table->integer('timestamp')->comment('Time when this status was recorded.');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messenger_message_statuses');
    }
};
