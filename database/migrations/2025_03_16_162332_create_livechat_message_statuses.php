<?php

use App\Http\Meta\Constants;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('livechat_message_statuses', function (Blueprint $table) {
            $table->id();
            $table->uuid('livechat_message_id');
            $table->foreign('livechat_message_id')->references('id')->on('livechat_messages')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->enum('status', Constants::MESSAGE_STATUS)->comment('Status of the message (e.g., delivered, read, failed).');
            $table->integer('timestamp')->comment('Time when this status was recorded.');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('livechat_message_statuses');
    }
};
