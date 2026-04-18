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
        Schema::create('whatsapp_business_profiles', function (Blueprint $table) {
            $table->id()->comment('The Whatsapp business account ID.');
            $table->unsignedBigInteger('whatsapp_business_account_id')->comment('The Whatsapp business account ID connected to it.');
            $table->unsignedBigInteger('whatsapp_phone_number_id')->comment('The Whatsapp Phone Number ID connected to it.');

            $table->string('about')->nullable()->comment("The business's About text. This text appears in the business's profile, beneath its profile image, phone number, and contact buttons.");
            $table->string('description', 512)->nullable()->comment('Description of the business. Character limit 512.');
            $table->string('profile_picture_url', 2048)->nullable()->comment('URL of the profile picture that was uploaded to Meta.');
            $table->string('address', 256)->nullable()->comment('Address of the business.');
            $table->string('email', 128)->nullable()->comment('Contact email address of the business.');
            $table->enum('vertical', Constants::VERTICAL)->nullable()->comment('The vertical industry that this business associates with.');
            $table->timestamps();

            $table->foreign('whatsapp_business_account_id')->references('id')->on('whatsapp_business_accounts')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreign('whatsapp_phone_number_id')->references('id')->on('whatsapp_phone_numbers')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_business_profiles', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_business_account_id']);
            $table->dropForeign(['whatsapp_phone_number_id']);

        });

        Schema::dropIfExists('whatsapp_business_profiles');
    }
};
