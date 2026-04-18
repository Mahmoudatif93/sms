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
        // ✅ Add organization_id column to segments
        $this->safeAddColumn('segments', 'organization_id', function (Blueprint $table) {
            $table->uuid('organization_id')->nullable()->after('id');
        });

        // ✅ Add FK to organizations table
        $this->safeAddForeignKey(
            table: 'segments',
            column: 'organization_id',
            foreignTable: 'organizations',
            foreignColumn: 'id',
            constraintName: 'segments_organization_id_fk'
        );

        // ❌ Optional: Drop workspace_id if you’re migrating away from it
        // $this->safeDropColumn('segments', 'workspace_id');
    }

    public function down(): void
    {
        // Drop the foreign key if exists
        $this->safeDropForeignKey('segments', 'segments_organization_id_fk');

        // Drop the column if exists
        $this->safeDropColumn('segments', 'organization_id');

        // ❌ Optional: Re-add workspace_id if you dropped it in up()
        // $this->safeAddColumn('segments', 'workspace_id', function (Blueprint $table) {
        //     $table->uuid('workspace_id')->nullable()->after('id');
        // });
    }
};
