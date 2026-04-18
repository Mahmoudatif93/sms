<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('channel_flow_keys', function (Blueprint $table) {
            $table->id();
            $table->uuid('channel_id')->index();
            $table->text('public_key');
            $table->text('private_key'); // should be encrypted before saving
            $table->timestamps();

            $table->unique('channel_id', 'channel_flow_keys_channel_id_unique');
        });

        $this->safeAddForeignKey(
            'channel_flow_keys',
            'channel_id',
            'channels',
            'id',
            'channel_flow_keys_channel_id_fk'
        );
    }

    public function down(): void
    {
        $this->safeDropForeignKey('channel_flow_keys', 'channel_flow_keys_channel_id_fk');
        $this->safeDropTable('channel_flow_keys');
    }
};
