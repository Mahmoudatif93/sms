<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    use SafeMigration;

    use SafeMigration;

    public function up(): void
    {
        // ✅ Safely add column
        $this->safeAddColumn('channels', 'default_workspace_id', function (Blueprint $table) {
            $table->uuid('default_workspace_id')->nullable()->after('id');
        });

        // ✅ Safely add foreign key
        $this->safeAddForeignKey(
            table: 'channels',
            column: 'default_workspace_id',
            foreignTable: 'workspaces',
            foreignColumn: 'id',
            constraintName: 'channels_default_workspace_id_foreign',
            onDelete: 'set null'
        );
    }

    public function down(): void
    {
        // ✅ Drop foreign key first
        $this->safeDropForeignKey('channels', 'channels_default_workspace_id_foreign');

        // ✅ Drop the column
        $this->safeDropColumn('channels', 'default_workspace_id');
    }
};
