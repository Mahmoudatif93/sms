<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Traits\SafeMigration;

return new class extends Migration {
    use SafeMigration;

    public function up(): void
    {
        $this->safeAddColumn('required_actions', 'organization_id', function (Blueprint $table) {
            $table->uuid('organization_id')->nullable()->after('id');
            $table->uuid('workspace_id')->nullable()->after('id');
        });

        $this->safeAddForeignKey(
            'required_actions',
            'organization_id',
            'organizations',
            'id',
            'required_actions_organization_id_fk'
        );

        $this->safeAddForeignKey(
            'required_actions',
            'workspace_id',
            'workspaces',
            'id',
            'required_actions_workspace_id_fk'
        );
    }

    public function down(): void
    {
        $this->safeDropForeignKey('required_actions', 'required_actions_organization_id_fk');
        $this->safeDropForeignKey('required_actions', 'required_actions_workspace_id_fk');
        $this->safeDropColumn('required_actions', 'organization_id');
    }
};
