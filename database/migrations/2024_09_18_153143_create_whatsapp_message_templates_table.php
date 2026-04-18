<?php

use App\Enums\LanguageCode;
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
        Schema::create('whatsapp_message_templates', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment('The Whatsapp Message Template ID.');

            $table->unsignedBigInteger('whatsapp_business_account_id')->comment('Foreign key to WhatsApp business accounts');

            $table->string('name', 512)->comment('The name of the template, with a maximum of 512 characters.');

            $table->enum('category', Constants::MESSAGE_TEMPLATE_CATEGORY)->comment('The category of the template. Possible values: AUTHENTICATION, MARKETING, UTILITY.');

            $table->enum('status', Constants::MESSAGE_TEMPLATE_STATUS)->comment('The status of the template. Possible values: PENDING, APPROVED, REJECTED.');

            $table->enum('language', LanguageCode::values())->comment('Template language Code (e.g., en_US)');
            $table->boolean('allow_category_change')->default(false)->comment('Allow category change. If set to true, the system may automatically assign a category.');
            $table->string('library_template_name')->nullable()->comment('The optional utility template name, if available.');
            $table->timestamp('created_at')->nullable()->comment('Creation timestamp');
            $table->timestamp('updated_at')->nullable()->comment('Last update timestamp');
            // Foreign key constraint to WhatsApp Business Account
            $table->foreign('whatsapp_business_account_id')
                ->references('id')
                ->on('whatsapp_business_accounts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
        });
    }

    /** * Reverse the migrations. */
    public function down(): void
    {
        Schema::table('whatsapp_message_templates', function (Blueprint $table) {
            // Drop foreign key constraint first
            $table->dropForeign(['whatsapp_business_account_id']);
        });

        Schema::dropIfExists('whatsapp_message_templates');
    }

};
