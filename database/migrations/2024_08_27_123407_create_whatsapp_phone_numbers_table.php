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
        Schema::create('whatsapp_phone_numbers', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment('The Whatsapp Phone Number ID.');
            $table->unsignedBigInteger('whatsapp_business_account_id')->comment('The Whatsapp business account ID connected to it.');
            $table->string('verified_name')->nullable()->comment('Verified name of the business associated with the phone number.');
            $table->string('code_verification_status')->nullable()->comment('Status of the code verification.');
            $table->string('display_phone_number')->nullable()->comment('The phone number displayed.');
            $table->string('quality_rating')->comment('Quality rating of the phone number.');
            $table->string('platform_type')->nullable()->comment('Platform type of the phone number.');
            //$table->string('throughput_level')->nullable()->comment('Throughput level of the phone number.');
            //$table->string('webhook_application')->nullable()->comment('Webhook application URL.');
            $table->timestamps();

            // Foreign key to the whatsapp_business_accounts table
            $table->foreign('whatsapp_business_account_id')
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
        Schema::table('whatsapp_phone_numbers', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_business_account_id']);
        });

        Schema::dropIfExists('whatsapp_phone_numbers');
    }
};
