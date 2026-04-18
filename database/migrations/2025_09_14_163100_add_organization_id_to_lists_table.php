<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    use SafeMigration;

    public function up(): void
    {
        // ✅ Add organization_id column to lists
        $this->safeAddColumn('lists', 'organization_id', function (Blueprint $table) {
            $table->uuid('organization_id')->nullable()->after('id');
        });

        // ✅ Add FK to organizations table
        $this->safeAddForeignKey(
            table: 'lists',
            column: 'organization_id',
            foreignTable: 'organizations',
            foreignColumn: 'id',
            constraintName: 'lists_organization_id_fk'
        );

        // ❌ (Optional) Drop workspace_id if you’re replacing it
        // $this->safeDropColumn('lists', 'workspace_id');
    }

    public function down(): void
    {
        // Drop the foreign key if exists
        $this->safeDropForeignKey('lists', 'lists_organization_id_fk');

        // Drop the column if exists
        $this->safeDropColumn('lists', 'organization_id');

        // ❌ (Optional) Re-add workspace_id if you dropped it in up()
        // $this->safeAddColumn('lists', 'workspace_id', function (Blueprint $table) {
        //     $table->uuid('workspace_id')->nullable()->after('id');
        // });
    }
};
