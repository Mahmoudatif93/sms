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
        Schema::create('hlr_lookup_his', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('number')->nullable();
            $table->string('live_status')->nullable();
            $table->string('country')->nullable();
            $table->string('telephone_number_type')->nullable();
            $table->string('network')->nullable();
            $table->string('roaming')->nullable();
            $table->timestamp('currentDate')->nullable();
            // $table->foreign('user_id')->references('id')->on('user');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hlr_lookup_his');
    }
};
