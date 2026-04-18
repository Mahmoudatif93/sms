<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    use SafeMigration;

    public function up(): void
    {
        // ✅ Add organization_id column
        $this->safeAddColumn('contacts', 'organization_id', function (Blueprint $table) {
            $table->uuid('organization_id')->nullable()->after('id');
        });

        // ✅ Add FK to organizations table
        $this->safeAddForeignKey(
            table: 'contacts',
            column: 'organization_id',
            foreignTable: 'organizations',
            foreignColumn: 'id',
            constraintName: 'contacts_organization_id_fk'
        );
    }

    public function down(): void
    {
        // Drop the foreign key if exists
        $this->safeDropForeignKey('contacts', 'contacts_organization_id_fk');

        // Drop the column if exists
        $this->safeDropColumn('contacts', 'organization_id');
    }
};
