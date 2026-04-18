<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Traits\SafeMigration;

return new class extends Migration
{
    use SafeMigration;

    public function up(): void
    {
        $this->safeCreateTable('organization_inbox_agent_settings', function (Blueprint $table) {
            $table->uuid('organization_id')->unique();
            $table->string('automation_technique')->default('load_balancer');
            $table->integer('wait_time_idle')->default(3600);
            $table->integer('max_conversations_per_agent')->default(5);
            $table->integer('available_to_away_time')->default(1800);
            $table->integer('away_to_office_time')->default(3600);
            $table->enum('default_availability', ['available', 'away', 'out_of_office'])->default('available');
            $table->boolean('enable_auto_assign')->default(true);
            $table->integer('auto_archive_delay')->default(60)->nullable();
            $table->integer('reassign_unresponsive_agents_after')->nullable();
            $table->timestamps();
        });

        $this->safeAddForeignKey(
            'organization_inbox_agent_settings',
            'organization_id',
            'organizations',
            'id',
            'org_inbox_agent_settings_org_id_fk'
        );
    }

    public function down(): void
    {
        $this->safeDropForeignKey('organization_inbox_agent_settings', 'org_inbox_agent_settings_org_id_fk');
        $this->safeDropTable('organization_inbox_agent_settings');
    }
};
