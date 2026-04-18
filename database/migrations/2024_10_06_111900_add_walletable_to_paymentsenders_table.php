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
        Schema::table('payment_sender', function (Blueprint $table) {
            $table->morphs('walletable');  // Adds walletable_id and walletable_type

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_sender', function (Blueprint $table) {
            $table->dropMorphs('walletable');  // Removes walletable_id and walletable_type

            
        });
    }
};
