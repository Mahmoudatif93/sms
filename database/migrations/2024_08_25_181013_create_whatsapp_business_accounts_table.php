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
        Schema::create('whatsapp_business_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment('The Whatsapp business account ID.');
            $table->unsignedBigInteger('business_manager_account_id')->comment('The business manager account ID connected to it.');
            $table->string('name')->nullable()->comment('The name of the Whatsapp business account.');
            $table->boolean('is_using_public_test_number')->default(false)->comment('Is using public test number.');
            $table->string('currency')->nullable()->comment('The currency of the Whatsapp business account.');
            $table->string('message_template_namespace')->nullable()->comment('The message_template_namespace of the Whatsapp business account.');

            $table->timestamps();

            $table->foreign('business_manager_account_id')->references('id')->on('business_manager_accounts')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_business_accounts', function (Blueprint $table) {
            $table->dropForeign(['business_manager_account_id']);
        });

        Schema::dropIfExists('whatsapp_business_accounts');
    }
};
