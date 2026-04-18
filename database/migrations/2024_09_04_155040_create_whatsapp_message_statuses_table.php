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
        Schema::create('whatsapp_message_statuses', function (Blueprint $table) {
            $table->id()->comment('Unique identifier for each status update.');
            $table->string('whatsapp_message_id', 255)->comment('References the Whatsapp messages');
            $table->enum('status', Constants::MESSAGE_STATUS)->comment('Status of the message (e.g., delivered, read, failed).');
            $table->integer('timestamp')->comment('Time when this status was recorded.');

            $table->timestamps();

            $table->foreign('whatsapp_message_id')
                ->references('id')
                ->on('whatsapp_messages')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        Schema::table('whatsapp_message_statuses', function (Blueprint $table) {
            // Drop the foreign key constraint if it was added
            $table->dropForeign(['whatsapp_message_id']);

        });

        Schema::dropIfExists('whatsapp_message_statuses');
    }
};
