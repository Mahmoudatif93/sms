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
        $this->safeAddColumn('campaigns', 'channel_id', function (Blueprint $table) {
            $table->uuid('channel_id')->nullable()->after('workspace_id');
        });

        $this->safeAddForeignKey(
            table: 'campaigns',
            column: 'channel_id',
            foreignTable: 'channels',
            foreignColumn: 'id',
            constraintName: 'campaigns_channel_id_fk',
            onDelete: 'set null' // Optional: Adjust behavior as needed
        );
    }

    public function down(): void
    {
        $this->safeDropForeignKey('campaigns', 'campaigns_channel_id_fk');
        $this->safeDropColumn('campaigns', 'channel_id');
    }
};
