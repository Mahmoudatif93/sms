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
        Schema::create('livechat_text_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('text');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('livechat_file_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('media_id');
            $table->string('link');
            $table->string('caption');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('livechat_text_messages');
        Schema::dropIfExists('livechat_file_messages');
    }
};
