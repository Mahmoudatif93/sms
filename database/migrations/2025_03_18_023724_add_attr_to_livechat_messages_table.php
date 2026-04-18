<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Http\Meta\Constants;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('livechat_messages', function (Blueprint $table) {
            $table->enum('status', Constants::MESSAGE_STATUS)
                ->default('initiated')
                ->comment('Current status of the message (e.g., initiated, sent, delivered, read, failed, deleted, warning).')->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('livechat_messages', function (Blueprint $table) {
            //
        });
    }
};
