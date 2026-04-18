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
        $this->safeAddColumn('iam_role_user', 'organization_id', function (Blueprint $table) {
            $table->uuid('organization_id')->nullable()->after('iam_role_id');
        });

        $this->safeAddForeignKey(
            table: 'iam_role_user',
            column: 'organization_id',
            foreignTable: 'organizations',
            foreignColumn: 'id',
            constraintName: 'iam_role_user_organization_id_fk',
            onDelete: 'cascade'
        );

    }

    public function down(): void
    {
        $this->safeDropForeignKey('iam_role_user', 'iam_role_user_organization_id_fk');
        $this->safeDropColumn('iam_role_user', 'organization_id');
    }
};
