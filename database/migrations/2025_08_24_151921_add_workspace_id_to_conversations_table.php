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
        $this->safeAddColumn('conversations', 'workspace_id', function (Blueprint $table) {
            $table->uuid('workspace_id')->nullable()->after('channel_id');
        });

        $this->safeAddForeignKey(
            'conversations',
            'workspace_id',
            'workspaces',
            'id',
            'conversations_workspace_id_fk',
            'cascade'
        );
    }

    public function down(): void
    {
        $this->safeDropForeignKey('conversations', 'conversations_workspace_id_fk');
        $this->safeDropColumn('conversations', 'workspace_id');
    }
};
