<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    use SafeMigration;
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->safeCreateTable('whatsapp_flows', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary()->comment('The Whatsapp Flow ID.');
            $table->foreignUuid('channel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->nullable();
            $table->json('categories')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->safeDropForeignKey('whatsapp_flows', 'whatsapp_flows_channel_id_foreign');
        Schema::dropIfExists('whatsapp_flows');
    }
};
