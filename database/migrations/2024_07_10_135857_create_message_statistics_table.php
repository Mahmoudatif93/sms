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
        Schema::create('message_statistics', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->unsigned()->nullable();
            $table->text('all_numbers');
            $table->string('sender_name', 255);
            $table->string('message',1000);
            $table->enum('send_time_method', ['NOW', 'LATER']);
            $table->timestamp('send_time')->nullable();
            $table->enum('sms_type', ['NORMAL', 'VARIABLES','ADS','VOICE','CALENDAR'])->nullable();
            $table->integer('repeation_times')->unsigned()->nullable();
            $table->string('excle_file',255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_statistics');
    }
};
