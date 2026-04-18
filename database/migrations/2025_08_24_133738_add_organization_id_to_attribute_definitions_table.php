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
        // ✅ Add organization_id column
        $this->safeAddColumn('attribute_definitions', 'organization_id', function (Blueprint $table) {
            $table->uuid('organization_id')->nullable()->after('id');
        });

        // ✅ Add a foreign key (if it doesn’t already exist)
        $this->safeAddForeignKey(
            'attribute_definitions',
            'organization_id',
            'organizations',
            'id',
            'attr_definitions_org_fk'
        );
    }

    public function down(): void
    {
        // ✅ Drop foreign key first
        $this->safeDropForeignKey('attribute_definitions', 'attr_definitions_org_fk');

        // ✅ Drop column
        $this->safeDropColumn('attribute_definitions', 'organization_id');
    }
};
