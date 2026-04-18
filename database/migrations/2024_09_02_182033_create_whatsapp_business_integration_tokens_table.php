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
        Schema::create('business_integration_system_user_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_manager_account_id')->comment('References the Business Manager Account it belongs to.');

            $table->string('access_token', 4096)->comment('Business Integration System User Access Token');
            $table->string('token_type')->default('bearer')->comment('Type of the token, e.g., "bearer"');
            $table->integer('expires_in')->comment('Time in seconds until the token expires');

            $table->timestamps();

            $table->foreign('business_manager_account_id', 'fk_biz_mgr_acc_id')
                ->references('id')
                ->on('business_manager_accounts')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        if (Schema::hasTable('business_integration_system_user_access_tokens')) {
            Schema::table('business_integration_system_user_access_tokens', function (Blueprint $table) {
                $table->dropForeign(['business_manager_account_id']);
            });
        }
        Schema::dropIfExists('business_integration_system_user_access_tokens');
    }
};
