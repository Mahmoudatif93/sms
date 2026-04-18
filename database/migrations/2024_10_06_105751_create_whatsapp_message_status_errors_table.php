<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_message_status_errors', function (Blueprint $table) {
            $table->id(); // Auto-incrementing primary key
            $table->unsignedBigInteger('whatsapp_message_status_id');
            $table->integer('error_code')->nullable(); // Error code
            $table->string('error_title')->nullable(); // Error title
            $table->string('error_message')->nullable(); // Error message
            $table->text('error_details')->nullable(); // Error details (instead of using JSON)
            $table->timestamps();

            $table->foreign('whatsapp_message_status_id', 'fk_wms_status_id')
                ->references('id')
                ->on('whatsapp_message_statuses')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('whatsapp_message_status_errors')) {
            Schema::table('whatsapp_message_status_errors', function (Blueprint $table) {
                // Drop the foreign key constraint first
                $table->dropForeign(['whatsapp_message_status_id']);
            });
            Schema::dropIfExists('whatsapp_message_status_errors');
        }

    }
};
