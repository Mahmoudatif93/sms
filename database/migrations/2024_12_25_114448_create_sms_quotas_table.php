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
        Schema::create('sms_quotas', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->string('quotable_type')->nullable();
            $table->uuid('quotable_id')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->float('sms_price');
            $table->integer('available_points');
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('user')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sms_quotas');
    }
};
