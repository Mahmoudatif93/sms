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
        Schema::create('whatsapp_consumer_phone_numbers', function (Blueprint $table) {
            $table->id()->comment('Primary key ID.');
            $table->unsignedBigInteger('whatsapp_business_account_id')->comment('The WhatsApp business account ID.');
            $table->string('wa_id')->nullable()->comment('The customer\'s WhatsApp Phone Number ID.');
            $table->string('phone_number')->comment('The actual phone number of the customer.');
            $table->string('name')->nullable()->comment('The name associated with the customer\'s phone number.');
            $table->boolean('is_active')->default(false)->comment('Whether the customer\'s phone number is active or not.');
            $table->timestamps();

            // Foreign key to whatsapp_business_accounts table
            $table->foreign('whatsapp_business_account_id', 'fk_consumer_phone_business_account')
                ->references('id')
                ->on('whatsapp_business_accounts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_consumer_phone_numbers', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_business_account_id']);
        });
        Schema::dropIfExists('whatsapp_consumer_phone_numbers');
    }
};
