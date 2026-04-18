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
        DB::statement("UPDATE whatsapp_business_profiles SET vertical = null ");

        Schema::table('whatsapp_business_profiles', function (Blueprint $table) {
            $table->enum('vertical', Constants::BUSINESS_PROFILE_VERTICAL)
                ->nullable()->comment('The vertical industry that this business profile associates with.')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {

        DB::statement("UPDATE whatsapp_business_profiles SET vertical = null ");
        Schema::table('whatsapp_business_profiles', function (Blueprint $table) {
            $table->enum('vertical', Constants::VERTICAL)
                ->nullable()->comment('The vertical industry that this business profile associates with.')
                ->change();
        });
    }
};
