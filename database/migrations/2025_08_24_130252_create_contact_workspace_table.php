<?php

use App\Traits\SafeMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use SafeMigration;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->safeCreateTable('contact_workspace', function (Blueprint $table) {
            $table->uuid('contact_id');
            $table->uuid('workspace_id');
            $table->timestamps();

            $table->primary(['contact_id', 'workspace_id']);

            $table->foreign('contact_id', 'contact_workspace_contact_id_fk')
                ->references('id')
                ->on('contacts')
                ->cascadeOnDelete();

            $table->foreign('workspace_id', 'contact_workspace_workspace_id_fk')
                ->references('id')
                ->on('workspaces')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safely drop foreign keys first
        $this->safeDropForeignKey('contact_workspace', 'contact_workspace_contact_id_fk');
        $this->safeDropForeignKey('contact_workspace', 'contact_workspace_workspace_id_fk');

        // Drop the table
        $this->safeDropTable('contact_workspace');
    }
};
