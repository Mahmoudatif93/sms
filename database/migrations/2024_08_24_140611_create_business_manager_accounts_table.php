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
        Schema::create('business_manager_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment('The business account ID.');
            $table->integer('user_id')->nullable()->comment('The user ID.');
            $table->string('name')->comment('The name of the business.');
            $table->string('link',2048)->nullable()->comment('URI for business profile page.');
            $table->string('profile_picture_uri',2048)->nullable()->comment('The profile picture URI of the business.');
            $table->enum('two_factor_type', Constants::TWO_FACTOR_TYPE)->default('none')->comment('The two factor type authentication used for this business.');
            $table->enum('verification_status', Constants::VERIFICATION_STATUS)->default('not_set')->comment('Verification status for this business.');
            $table->enum('vertical', Constants::VERTICAL)->nullable()->comment('The vertical industry that this business associates with, or belongs to.');
            $table->unsignedInteger('vertical_id')->nullable()->comment('The ID for the vertical industry.');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('user')->cascadeOnUpdate()->cascadeOnDelete();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_manager_accounts', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::dropIfExists('business_manager_accounts');
    }
};
